<?php
/**
 * 费用预警控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\Admin;
use app\backend\model\EduClass;
use app\backend\model\FeeWarning as FeeWarningModel;
use app\backend\model\Student;
use app\common\controller\Backend;
use think\App;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="费用预警")
 * Class FeeWarning
 * @package app\backend\controller\edu
 */
class FeeWarning extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new FeeWarningModel();
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
            $page = max((int) $this->request->param('page', 1), 1);
            $limit = max((int) $this->request->param('limit', 10), 1);
            $keyword = trim((string) $this->request->param('keyword', ''));

            $query = $this->modelClass->with(['student', 'eduClass'])->order(['id' => 'asc']);
            $this->applyTeacherClassScope($query, 'class_id');
            if ($keyword !== '') {
                $query->where('warning_no|student_name|class_name|course_name|warning_content|follow_remark', 'like', '%' . $keyword . '%');
            }

            $count = $query->count();
            $list = $query->page($page, $limit)->select()->toArray();
            foreach ($list as &$item) {
                $item = $this->normalizeRow($item);
            }
            unset($item);

            return json(['code' => 0, 'msg' => lang('get formData success'), 'data' => $list, 'count' => $count]);
        }

        View::assign([
            'warningTypeList' => FeeWarningModel::WARNING_TYPE_LIST,
            'warningLevelList' => FeeWarningModel::WARNING_LEVEL_LIST,
            'followStatusList' => FeeWarningModel::FOLLOW_STATUS_LIST,
        ]);
        return view();
    }

    /**
     * @NodeAnnotation(title="添加")
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->buildSaveData($this->request->post());
            $this->assertTeacherOwnsStudent((int) ($data['student_id'] ?? 0));
            $data['warning_no'] = FeeWarningModel::generateWarningNo();
            if ($this->modelClass->save($data)) {
                $this->success(lang('operation success'));
            }
            $this->error(lang('operation failed'));
        }

        View::assign($this->buildFormViewData([
            'warning_type' => 1,
            'warning_level' => 2,
            'follow_status' => 1,
            'status' => 1,
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
            $this->error('费用预警记录不存在');
        }
        $this->assertTeacherOwnsStudent((int) ($model->student_id ?? 0));

        if ($this->request->isPost()) {
            $data = $this->buildSaveData($this->request->post());
            $this->assertTeacherOwnsStudent((int) ($data['student_id'] ?? $model->student_id));
            if ($model->save($data)) {
                $this->success(lang('operation success'));
            }
            $this->error(lang('operation failed'));
        }

        View::assign($this->buildFormViewData($this->normalizeRow($model->toArray())));
        return view('add');
    }

    /**
     * @NodeAnnotation(title="查看")
     */
    public function view()
    {
        $id = (int) $this->request->param('id');
        $model = $this->modelClass->with(['student', 'eduClass'])->find($id);
        if (!$model) {
            $this->error('费用预警记录不存在');
        }
        $this->assertTeacherOwnsStudent((int) ($model->student_id ?? 0));

        View::assign(['formData' => $this->normalizeRow($model->toArray())]);
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
        if ($list->isEmpty()) {
            $this->error('费用预警记录不存在');
        }

        try {
            foreach ($list as $item) {
                $this->assertTeacherOwnsStudent((int) ($item->student_id ?? 0));
                $item->delete();
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

    protected function buildSaveData(array $post): array
    {
        $rule = [
            'student_id|学生' => 'require|number',
            'warning_type|预警类型' => 'require|in:1,2,3,4,5',
            'warning_level|预警等级' => 'require|in:1,2,3,4',
            'trigger_value|触发值' => 'number',
            'threshold_value|阈值' => 'number',
            'last_attendance_date|最近到课日期' => 'date',
            'warning_content|预警内容' => 'require|max:500',
            'follow_status|跟进状态' => 'require|in:1,2,3,4',
            'follow_admin_id|跟进人' => 'number',
            'follow_remark|跟进备注' => 'max:500',
            'status|状态' => 'require|in:1,2',
        ];
        $this->validate($post, $rule);

        $studentId = (int) ($post['student_id'] ?? 0);
        $student = Student::find($studentId);
        if (!$student) {
            $this->error('学生不存在');
        }

        $classId = (int) ($student->class_id ?? 0);
        $class = $classId > 0 ? EduClass::find($classId) : null;
        if (!$class) {
            $this->error('该学生未绑定班级');
        }

        $followAdminId = (int) ($post['follow_admin_id'] ?? 0);
        $followAdmin = $followAdminId > 0 ? Admin::find($followAdminId) : null;

        return [
            'student_id' => $studentId,
            'student_name' => (string) ($student->name ?? ''),
            'class_id' => $classId,
            'class_name' => (string) ($class->name ?? ''),
            'course_id' => (int) ($class->course_id ?? 0),
            'course_name' => (string) ($class->course_name ?? ''),
            'warning_type' => (int) ($post['warning_type'] ?? 1),
            'warning_level' => (int) ($post['warning_level'] ?? 1),
            'trigger_value' => round((float) ($post['trigger_value'] ?? 0), 2),
            'threshold_value' => round((float) ($post['threshold_value'] ?? 0), 2),
            'last_attendance_date' => trim((string) ($post['last_attendance_date'] ?? '')),
            'warning_content' => trim((string) ($post['warning_content'] ?? '')),
            'follow_status' => (int) ($post['follow_status'] ?? 1),
            'follow_admin_id' => $followAdminId,
            'follow_remark' => trim((string) ($post['follow_remark'] ?? '')),
            'resolved_time' => (int) ($post['follow_status'] ?? 1) >= 3 ? time() : 0,
            'status' => (int) ($post['status'] ?? 1),
        ];
    }

    protected function buildFormViewData(array $formData): array
    {
        $adminQuery = Admin::where('status', 1)->order('id asc');
        if ($this->isTeacherRole()) {
            $adminQuery->where('id', $this->getCurrentAdminId());
        }

        return [
            'formData' => $formData,
            'warningTypeList' => FeeWarningModel::WARNING_TYPE_LIST,
            'warningLevelList' => FeeWarningModel::WARNING_LEVEL_LIST,
            'followStatusList' => FeeWarningModel::FOLLOW_STATUS_LIST,
            'adminList' => $adminQuery->column('username', 'id'),
            'title' => isset($formData['id']) ? lang('Edit') : lang('Add'),
        ];
    }

    protected function normalizeRow(array $item): array
    {
        $item['warning_type_text'] = FeeWarningModel::WARNING_TYPE_LIST[$item['warning_type'] ?? null] ?? '';
        $item['warning_level_text'] = FeeWarningModel::WARNING_LEVEL_LIST[$item['warning_level'] ?? null] ?? '';
        $item['follow_status_text'] = FeeWarningModel::FOLLOW_STATUS_LIST[$item['follow_status'] ?? null] ?? '';
        $item['trigger_value'] = round((float) ($item['trigger_value'] ?? 0), 2);
        $item['threshold_value'] = round((float) ($item['threshold_value'] ?? 0), 2);
        return $item;
    }
}
