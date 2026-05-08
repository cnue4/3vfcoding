<?php
/**
 * 学生异动控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\EduCourse;
use app\backend\model\Student;
use app\backend\model\StudentChange as StudentChangeModel;
use app\common\controller\Backend;
use think\App;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="学生异动")
 * Class StudentChange
 * @package app\backend\controller\edu
 */
class StudentChange extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new StudentChangeModel();
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

            $query = $this->modelClass->with(['student', 'oldCourse', 'newCourse'])->order(['id' => 'asc']);
            $this->applyTeacherStudentScope($query, 'student_id');

            if ($keyword !== '') {
                $studentIds = Student::where('name|student_no|contact_name|phone', 'like', '%' . $keyword . '%')->column('id');
                $matchedIds = $this->modelClass->where('remark|refund_reason|gift_reason', 'like', '%' . $keyword . '%')->column('id');
                if (!empty($studentIds)) {
                    $matchedIds = array_merge($matchedIds, $this->modelClass->where('student_id', 'in', $studentIds)->column('id'));
                }
                $matchedIds = array_values(array_unique(array_filter($matchedIds)));
                $query->where('id', 'in', !empty($matchedIds) ? $matchedIds : [0]);
            }

            $count = $query->count();
            $list = $query->page($page, $limit)->select()->toArray();
            foreach ($list as &$item) {
                $item = $this->normalizeChangeRow($item);
            }
            unset($item);

            return json(['code' => 0, 'msg' => lang('get formData success'), 'data' => $list, 'count' => $count]);
        }

        View::assign([
            'changeTypeList' => StudentChangeModel::CHANGE_TYPE_LIST,
        ]);
        return view();
    }

    /**
     * @NodeAnnotation(title="添加")
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->buildChangeData($this->request->post());
            $this->assertTeacherOwnsStudent((int) ($data['student_id'] ?? 0));
            $result = $this->modelClass->save($data);
            if ($result) {
                $studentId = (int) ($data['student_id'] ?? 0);
                Student::syncLeaveStatusByStudent($studentId);
                $this->syncStudentGiftHours($studentId);
                $this->success(lang('operation success'));
            }
            $this->error(lang('operation failed'));
        }

        View::assign($this->buildFormViewData([
            'change_type' => 1,
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
        $model = $this->modelClass->with(['student', 'oldCourse', 'newCourse'])->find($id);
        if (!$model) {
            $this->error('学生异动记录不存在');
        }

        if ($this->request->isPost()) {
            $data = $this->buildChangeData($this->request->post());
            $this->assertTeacherOwnsStudent((int) ($data['student_id'] ?? $model->student_id));
            $studentId = (int) ($data['student_id'] ?? $model->student_id);
            $oldStudentId = (int) ($model->student_id ?? 0);
            $result = $model->save($data);
            if ($result) {
                Student::syncLeaveStatusByStudent($oldStudentId);
                $this->syncStudentGiftHours($oldStudentId);
                if ($studentId !== $oldStudentId) {
                    Student::syncLeaveStatusByStudent($studentId);
                    $this->syncStudentGiftHours($studentId);
                } else {
                    $this->syncStudentGiftHours($studentId);
                }
                $this->success(lang('operation success'));
            }
            $this->error(lang('operation failed'));
        }

        View::assign($this->buildFormViewData($this->normalizeChangeRow($model->toArray())));
        return view('add');
    }

    /**
     * @NodeAnnotation(title="查看")
     */
    public function view()
    {
        $id = (int) $this->request->param('id');
        $model = $this->modelClass->with(['student', 'oldCourse', 'newCourse'])->find($id);
        if (!$model) {
            $this->error('学生异动记录不存在');
        }
        $this->assertTeacherOwnsStudent((int) ($model->student_id ?? 0));

        View::assign(['formData' => $this->normalizeChangeRow($model->toArray())]);
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
            $this->error('学生异动记录不存在');
        }

        try {
            foreach ($list as $item) {
                $this->assertTeacherOwnsStudent((int) ($item->student_id ?? 0));
                $studentId = (int) ($item->student_id ?? 0);
                $item->delete();
                Student::syncLeaveStatusByStudent($studentId);
                $this->syncStudentGiftHours($studentId);
            }
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }

        $this->success(lang('operation success'));
    }

    protected function buildChangeData(array $post): array
    {
        $rule = [
            'student_id|学生' => 'require|number',
            'change_type|学员状态' => 'require|in:1,2,3,4,5',
            'remark|备注' => 'max:500',
            'status|状态' => 'require|in:1,2',
        ];
        $this->validate($post, $rule);

        $studentId = (int) ($post['student_id'] ?? 0);
        $student = Student::with(['eduClass', 'eduCourse'])->find($studentId);
        if (!$student) {
            $this->error('学生不存在');
        }

        $studentData = $student->toArray();
        $changeType = (int) ($post['change_type'] ?? 1);
        $data = [
            'student_id' => $studentId,
            'student_name' => $studentData['name'] ?? '',
            'class_name' => $studentData['eduClass']['name'] ?? ($studentData['class_name'] ?? ''),
            'change_type' => $changeType,
            'leave_start_time' => '',
            'leave_end_time' => '',
            'old_course_id' => 0,
            'new_course_id' => 0,
            'refund_amount' => 0,
            'refund_detail' => '',
            'refund_reason' => '',
            'gift_hours' => 0,
            'gift_reason' => '',
            'delete_gift_hours' => 0,
            'delete_gift_reason' => '',
            'remark' => trim((string) ($post['remark'] ?? '')),
            'status' => (int) ($post['status'] ?? 1),
        ];

        if ($changeType === 1) {
            $leaveRule = [
                'leave_start_time|请假开始时间' => 'require|date',
                'leave_end_time|请假结束时间' => 'require|date',
            ];
            $this->validate($post, $leaveRule);
            $data['leave_start_time'] = trim((string) ($post['leave_start_time'] ?? ''));
            $data['leave_end_time'] = trim((string) ($post['leave_end_time'] ?? ''));
            if ($data['leave_start_time'] > $data['leave_end_time']) {
                $this->error('请假结束时间不能早于请假开始时间');
            }
        } elseif ($changeType === 2) {
            $scheduleRule = [
                'old_course_id|旧课程' => 'require|number',
                'new_course_id|新课程' => 'require|number',
            ];
            $this->validate($post, $scheduleRule);
            $data['old_course_id'] = (int) ($post['old_course_id'] ?? 0);
            $data['new_course_id'] = (int) ($post['new_course_id'] ?? 0);
        } elseif ($changeType === 3) {
            $refundRule = [
                'refund_amount|退费金额' => 'require|number|gt:0',
                'refund_detail|退费明细' => 'require|max:500',
                'refund_reason|退费原因' => 'require|max:500',
            ];
            $this->validate($post, $refundRule);
            $data['refund_amount'] = round((float) ($post['refund_amount'] ?? 0), 2);
            $data['refund_detail'] = trim((string) ($post['refund_detail'] ?? ''));
            $data['refund_reason'] = trim((string) ($post['refund_reason'] ?? ''));
        } elseif ($changeType === 4) {
            $giftRule = [
                'gift_hours|赠送课时数量' => 'require|number|gt:0',
                'gift_reason|赠课原因' => 'require|max:500',
            ];
            $this->validate($post, $giftRule);
            $data['gift_hours'] = round((float) ($post['gift_hours'] ?? 0), 2);
            $data['gift_reason'] = trim((string) ($post['gift_reason'] ?? ''));
        } elseif ($changeType === 5) {
            $deleteGiftRule = [
                'delete_gift_hours|删除课时' => 'require|number|gt:0',
                'delete_gift_reason|删除原因' => 'require|max:500',
            ];
            $this->validate($post, $deleteGiftRule);
            $data['gift_hours'] = round((float) ($post['delete_gift_hours'] ?? 0), 2);
            $data['gift_reason'] = trim((string) ($post['delete_gift_reason'] ?? ''));
        }

        return $data;
    }

    protected function normalizeChangeRow(array $item): array
    {
        $studentData = $item['student'] ?? [];
        $oldCourseData = $item['old_course'] ?? ($item['oldCourse'] ?? []);
        $newCourseData = $item['new_course'] ?? ($item['newCourse'] ?? []);

        $item['student_name'] = $item['student_name'] ?? ($studentData['name'] ?? '');
        $item['class_name'] = $item['class_name'] ?? ($studentData['class_name'] ?? '');
        $item['change_type_text'] = StudentChangeModel::CHANGE_TYPE_LIST[$item['change_type'] ?? null] ?? '';
        $item['old_course_name'] = $oldCourseData['name'] ?? '';
        $item['new_course_name'] = $newCourseData['name'] ?? '';
        $item['refund_amount'] = round((float) ($item['refund_amount'] ?? 0), 2);
        $item['gift_hours'] = round((float) ($item['gift_hours'] ?? 0), 2);
        $item['delete_gift_hours'] = (int) ($item['change_type'] ?? 0) === 5 ? $item['gift_hours'] : 0;
        $item['delete_gift_reason'] = (int) ($item['change_type'] ?? 0) === 5 ? ($item['gift_reason'] ?? '') : '';
        return $item;
    }

    protected function buildFormViewData(array $formData): array
    {
        return [
            'formData' => $formData,
            'changeTypeList' => StudentChangeModel::CHANGE_TYPE_LIST,
            'courseList' => EduCourse::where('status', 1)->order('id asc')->column('name', 'id'),
            'title' => isset($formData['id']) ? lang('Edit') : lang('Add'),
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
