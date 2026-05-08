<?php
/**
 * 报名管理控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\EduClass;
use app\backend\model\EduEnrollment as EduEnrollmentModel;
use app\backend\model\Student;
use app\common\controller\Backend;
use think\App;
use think\facade\Db;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="续费报名")
 */
class EduEnrollment extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new EduEnrollmentModel();
    }

    protected function checkToken()
    {
        return true;
    }

    /**
     * @NodeAnnotation(title="列表")
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();
            $sort = ['id' => 'asc'];
            $keyword = trim((string) $this->request->param('keyword', ''));

            $countQuery = $this->modelClass
                ->with(['student', 'sourceClass', 'targetClass'])
                ->where($where);
            $listQuery = $this->modelClass
                ->with(['student', 'sourceClass', 'targetClass'])
                ->where($where);
            $this->applyTeacherStudentScope($countQuery, 'student_id');
            $this->applyTeacherStudentScope($listQuery, 'student_id');

            if ($keyword !== '') {
                $countQuery->where('enroll_no|student_name|contact_name|phone|source_class_name|target_class_name|course_name', 'like', '%' . $keyword . '%');
                $listQuery->where('enroll_no|student_name|contact_name|phone|source_class_name|target_class_name|course_name', 'like', '%' . $keyword . '%');
            }

            $count = $countQuery->count();
            $list = $listQuery
                ->order($sort)
                ->page($this->page, $this->pageSize)
                ->select()
                ->toArray();
            foreach ($list as &$item) {
                $item = $this->normalizeEnrollmentRow($item);
            }
            unset($item);

            return json([
                'code' => 0,
                'msg' => lang('get formData success'),
                'data' => $list,
                'count' => $count,
                'extra' => ['summary' => $this->buildSummary($keyword)],
            ]);
        }

        View::assign([
            'enrollTypeList' => EduEnrollmentModel::ENROLL_TYPE_LIST,
            'statusList' => EduEnrollmentModel::STATUS_LIST,
        ]);
        return view();
    }

    /**
     * @NodeAnnotation(title="添加")
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->buildEnrollmentData($this->request->post());
            $this->assertTeacherOwnsStudent((int) ($data['student_id'] ?? 0));
            $this->assertTeacherOwnsClass((int) ($data['target_class_id'] ?? 0));
            $data['enroll_no'] = EduEnrollmentModel::generateEnrollNo();
            $result = $this->modelClass->save($data);
            if ($result) {
                $this->syncStudentGiftHours((int) ($data['student_id'] ?? 0));
                $this->success(lang('operation success'));
            }
            $this->error(lang('operation failed'));
        }

        View::assign($this->buildFormViewData([
            'enroll_type' => 1,
            'pay_status' => 1,
            'status' => 1,
            'enroll_date' => date('Y-m-d'),
        ]));
        return view();
    }

    /**
     * @NodeAnnotation(title="编辑")
     */
    public function edit()
    {
        $id = (int) $this->request->param('id');
        $model = $this->modelClass->find($id);
        if (!$model) {
            $this->error('报名记录不存在');
        }
        $this->assertTeacherOwnsStudent((int) ($model->student_id ?? 0));

        if ($this->request->isPost()) {
            $data = $this->buildEnrollmentData($this->request->post(), $model->toArray());
            $this->assertTeacherOwnsStudent((int) ($data['student_id'] ?? $model->student_id));
            $this->assertTeacherOwnsClass((int) ($data['target_class_id'] ?? 0));
            $result = $model->save($data);
            if ($result) {
                $this->syncStudentGiftHours((int) ($data['student_id'] ?? $model->student_id));
                $this->success(lang('operation success'));
            }
            $this->error(lang('operation failed'));
        }

        View::assign($this->buildFormViewData($this->normalizeEnrollmentRow($model->toArray())));
        return view('add');
    }

    /**
     * @NodeAnnotation(title="查看")
     */
    public function view()
    {
        $id = (int) $this->request->param('id');
        $model = $this->modelClass->with(['student', 'sourceClass', 'targetClass'])->find($id);
        if (!$model) {
            $this->error('报名记录不存在');
        }
        $this->assertTeacherOwnsStudent((int) ($model->student_id ?? 0));

        View::assign(['formData' => $this->normalizeEnrollmentRow($model->toArray())]);
        return view();
    }

    /**
     * @NodeAnnotation(title="删除")
     */
    public function delete()
    {
        $ids = $this->request->param('ids') ?: $this->request->param('id');
        if (empty($ids)) {
            $this->error(lang('Ids can not empty'));
        }

        $list = $this->modelClass->where('id', 'in', $ids)->select();
        try {
            foreach ($list as $item) {
                $this->assertTeacherOwnsStudent((int) ($item->student_id ?? 0));
                $studentId = (int) ($item->student_id ?? 0);
                $item->delete();
                $this->syncStudentGiftHours($studentId);
            }
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }

        $this->success(lang('operation success'));
    }

    /**
     * @NodeAnnotation(title="搜索学生")
     */
    public function searchStudent()
    {
        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword === '') {
            return json(['code' => 0, 'msg' => 'ok', 'data' => []]);
        }

        $escapedKeyword = addcslashes($keyword, '%_');
        $exactLike = $escapedKeyword;
        $prefixLike = $escapedKeyword . '%';
        $containLike = '%' . $escapedKeyword . '%';

        $list = Student::alias('s')
            ->leftJoin('edu_class c', 'c.id = s.class_id')
            ->where('s.status', 1)
            ->whereNull('s.delete_time');
        if ($this->isTeacherRole()) {
            $list->where('c.teacher_id', $this->getCurrentAdminId());
        }
        $list = $list
            ->where(function ($query) use ($containLike) {
                $query->whereLike('s.name', $containLike)
                    ->whereOrLike('s.student_no', $containLike)
                    ->whereOrLike('s.contact_name', $containLike)
                    ->whereOrLike('s.phone', $containLike);
            })
            ->field([
                's.id',
                's.name',
                's.student_no',
                's.class_id',
                's.contact_name',
                's.phone',
                'c.name' => 'class_name',
            ])
            ->orderRaw(
                "CASE " .
                "WHEN s.name LIKE '{$exactLike}' THEN 1 " .
                "WHEN s.student_no LIKE '{$exactLike}' THEN 2 " .
                "WHEN s.name LIKE '{$prefixLike}' THEN 3 " .
                "WHEN s.student_no LIKE '{$prefixLike}' THEN 4 " .
                "WHEN s.name LIKE '{$containLike}' THEN 5 " .
                "WHEN s.student_no LIKE '{$containLike}' THEN 6 " .
                "WHEN s.contact_name LIKE '{$containLike}' THEN 7 " .
                "WHEN s.phone LIKE '{$containLike}' THEN 8 " .
                "ELSE 9 END ASC, s.id ASC"
            )
            ->limit(20)
            ->select()
            ->toArray();

        return json(['code' => 0, 'msg' => 'ok', 'data' => $list]);
    }

    protected function buildEnrollmentData(array $post, array $current = []): array
    {
        $studentId = (int) ($post['student_id'] ?? ($current['student_id'] ?? 0));
        $sourceClassId = (int) ($post['source_class_id'] ?? ($current['source_class_id'] ?? 0));
        $targetClassId = (int) ($post['target_class_id'] ?? ($current['target_class_id'] ?? 0));
        $previousGiftHours = round((float) ($current['gift_hours'] ?? 0), 2);

        $rule = [
            'student_id|学生' => 'require|number',
            'enroll_type|报名类型' => 'require|in:1,2,3',
            'target_class_id|目标班级' => 'require|number',
            'enroll_amount|报名金额' => 'require|regex:/^\d+(\.\d+)?$/',
            'gift_hours|赠送课时' => 'regex:/^\d+(\.\d+)?$/',
            'pay_status|付款状态' => 'require|in:1,2,3',
            'enroll_date|报名日期' => 'require|date',
            'remark|备注' => 'max:500',
            'status|状态' => 'require|in:1,2,3,4',
        ];
        $this->validate($post, $rule);

        $student = Student::find($studentId);
        if (!$student) {
            $this->error('学生不存在');
        }

        $sourceClassId = $sourceClassId ?: (int) ($student->class_id ?? 0);
        $sourceClass = $sourceClassId ? EduClass::find($sourceClassId) : null;
        $targetClass = EduClass::find($targetClassId);
        if (!$sourceClass) {
            $this->error('原班级不存在');
        }
        if (!$targetClass) {
            $this->error('目标班级不存在');
        }

        $giftHours = round((float) ($post['gift_hours'] ?? 0), 2);
        $status = (int) ($post['status'] ?? 1);
        return [
            'student_id' => $studentId,
            'student_name' => (string) ($student->name ?? ''),
            'contact_name' => trim((string) ($post['contact_name'] ?? ($student->contact_name ?? ''))),
            'phone' => trim((string) ($post['phone'] ?? ($student->phone ?? ''))),
            'enroll_type' => (int) ($post['enroll_type'] ?? 1),
            'source_class_id' => $sourceClassId,
            'target_class_id' => $targetClassId,
            'source_class_name' => (string) ($sourceClass->name ?? ''),
            'target_class_name' => (string) ($targetClass->name ?? ''),
            'course_name' => (string) ($targetClass->course_name ?? EduClass::where('id', $targetClassId)->value('course_name') ?? ''),
            'enroll_amount' => round((float) ($post['enroll_amount'] ?? 0), 2),
            'gift_hours' => $giftHours,
            'pay_status' => (int) ($post['pay_status'] ?? 1),
            'enroll_date' => trim((string) ($post['enroll_date'] ?? date('Y-m-d'))),
            'remark' => trim((string) ($post['remark'] ?? '')),
            'status' => $status,
        ];
    }

    protected function normalizeEnrollmentRow(array $item): array
    {
        $item['enroll_amount'] = round((float) ($item['enroll_amount'] ?? 0), 2);
        $item['gift_hours'] = round((float) ($item['gift_hours'] ?? 0), 2);
        $item['pay_status_text'] = [1 => '待付款', 2 => '部分付款', 3 => '已付款'][(int) ($item['pay_status'] ?? 1)] ?? '';
        $item['enroll_type_text'] = EduEnrollmentModel::ENROLL_TYPE_LIST[$item['enroll_type'] ?? null] ?? '';
        $item['status_text'] = EduEnrollmentModel::STATUS_LIST[$item['status'] ?? null] ?? '';
        return $item;
    }

    protected function buildFormViewData(array $formData): array
    {
        $classQuery = EduClass::where('status', 1)->order('id asc');
        if ($this->isTeacherRole()) {
            $classQuery->where('teacher_id', $this->getCurrentAdminId());
        }

        return [
            'formData' => $formData,
            'enrollTypeList' => EduEnrollmentModel::ENROLL_TYPE_LIST,
            'statusList' => EduEnrollmentModel::STATUS_LIST,
            'payStatusList' => [
                ['value' => 1, 'name' => '待付款'],
                ['value' => 2, 'name' => '部分付款'],
                ['value' => 3, 'name' => '已付款'],
            ],
            'classList' => $classQuery->field('id,name')->select()->toArray(),
            'title' => isset($formData['id']) ? lang('Edit') : lang('Add'),
        ];
    }

    protected function buildSummary(string $keyword = ''): array
    {
        $query = Db::name('edu_enrollment')->whereNull('delete_time');
        if ($this->isTeacherRole()) {
            $studentIds = $this->getTeacherOwnedStudentIds();
            $query->whereIn('student_id', !empty($studentIds) ? $studentIds : [0]);
        }
        if ($keyword !== '') {
            $query->where('enroll_no|student_name|contact_name|phone|source_class_name|target_class_name|course_name', 'like', '%' . $keyword . '%');
        }

        $rows = $query->field('COUNT(id) as total_count, COALESCE(SUM(enroll_amount), 0) as total_amount, COALESCE(SUM(gift_hours), 0) as total_gift_hours')->find();
        return [
            'total_count' => (int) ($rows['total_count'] ?? 0),
            'total_amount' => round((float) ($rows['total_amount'] ?? 0), 2),
            'total_gift_hours' => round((float) ($rows['total_gift_hours'] ?? 0), 2),
        ];
    }

    protected function syncStudentGiftHours(int $studentId): void
    {
        if ($studentId <= 0) {
            return;
        }

        $student = Student::find($studentId);
        if (!$student) {
            return;
        }

        $classId = (int) ($student->class_id ?? 0);
        if ($classId > 0) {
            Student::syncHoursByClassTemplate($classId);
            return;
        }

        $student->save([
            'total_hours' => 0,
            'remaining_hours' => 0,
        ]);
    }
}
