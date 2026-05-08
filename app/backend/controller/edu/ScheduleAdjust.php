<?php
/**
 * 调课补课停课控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\Admin;
use app\backend\model\EduClass;
use app\backend\model\EduClassHour;
use app\backend\model\ScheduleAdjust as ScheduleAdjustModel;
use app\backend\model\Student;
use app\common\controller\Backend;
use think\App;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="调课补课停课")
 * Class ScheduleAdjust
 * @package app\backend\controller\edu
 */
class ScheduleAdjust extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new ScheduleAdjustModel();
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

            $query = $this->modelClass->with(['eduClass', 'student', 'teacher', 'replaceTeacher', 'classHour'])->order(['id' => 'asc']);
            $this->applyTeacherClassScope($query, 'class_id');
            if ($keyword !== '') {
                $query->where('adjust_no|class_name|student_name|teacher_name|replace_teacher_name|reason', 'like', '%' . $keyword . '%');
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
            'adjustTypeList' => ScheduleAdjustModel::ADJUST_TYPE_LIST,
            'businessStatusList' => ScheduleAdjustModel::BUSINESS_STATUS_LIST,
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
            $this->assertTeacherOwnsClass((int) ($data['class_id'] ?? 0));
            $data['adjust_no'] = ScheduleAdjustModel::generateAdjustNo();
            $result = $this->modelClass->save($data);
            if ($result) {
                $this->success(lang('operation success'));
            }
            $this->error(lang('operation failed'));
        }

        View::assign($this->buildFormViewData([
            'adjust_type' => 1,
            'business_status' => 1,
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
            $this->error('调课记录不存在');
        }
        $this->assertTeacherOwnsClass((int) ($model->class_id ?? 0));

        if ($this->request->isPost()) {
            $data = $this->buildSaveData($this->request->post());
            $result = $model->save($data);
            if ($result) {
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
        $model = $this->modelClass->with(['eduClass', 'student', 'teacher', 'replaceTeacher', 'classHour'])->find($id);
        if (!$model) {
            $this->error('调课记录不存在');
        }
        $this->assertTeacherOwnsClass((int) ($model->class_id ?? 0));

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
            $this->error('调课记录不存在');
        }

        try {
            foreach ($list as $item) {
                $this->assertTeacherOwnsClass((int) ($item->class_id ?? 0));
                $item->delete();
            }
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }

        $this->success(lang('operation success'));
    }

    protected function buildSaveData(array $post): array
    {
        $rule = [
            'adjust_type|调整类型' => 'require|in:1,2,3,4,5',
            'business_status|业务状态' => 'require|in:1,2,3,4,5',
            'class_id|班级' => 'require|number',
            'target_scope|调整对象' => 'require|in:1,2',
            'teacher_id|原教师' => 'require|number',
            'replace_teacher_id|代课教师' => 'number',
            'origin_class_hour_id|原课时记录' => 'number',
            'origin_class_date|原上课日期' => 'date',
            'target_class_date|目标上课日期' => 'date',
            'deduct_hours|扣减课时' => 'number',
            'compensate_hours|补回课时' => 'number',
            'reason|申请原因' => 'max:500',
            'approve_remark|审核备注' => 'max:500',
            'status|状态' => 'require|in:1,2',
        ];
        $this->validate($post, $rule);

        $classId = (int) ($post['class_id'] ?? 0);
        $class = EduClass::find($classId);
        if (!$class) {
            $this->error('班级不存在');
        }
        $this->assertTeacherOwnsClass($classId);

        $targetScope = (int) ($post['target_scope'] ?? 1);
        $studentIds = [];
        $studentNames = [];
        if ($targetScope === 2) {
            $studentKeyword = trim((string) ($post['student_keyword'] ?? ''));
            if ($studentKeyword === '') {
                $this->error('请输入要调整的学生姓名');
            }

            $studentKeywords = preg_split('/[，,]+/u', $studentKeyword);
            $studentKeywords = array_values(array_unique(array_filter(array_map('trim', $studentKeywords), static function ($name) {
                return $name !== '';
            })));
            if (empty($studentKeywords)) {
                $this->error('请输入有效的学生姓名');
            }

            foreach ($studentKeywords as $studentName) {
                $studentQuery = Student::where('class_id', $classId)
                    ->whereNull('delete_time')
                    ->where('name', $studentName);
                $studentCount = $studentQuery->count();
                if ($studentCount === 0) {
                    $this->error('未找到学生“' . $studentName . '”，请检查姓名和班级是否一致');
                }
                if ($studentCount > 1) {
                    $this->error('班级下学生“' . $studentName . '”存在重名，请先区分后再调整');
                }

                $student = $studentQuery->find();
                $studentIds[] = (int) ($student->id ?? 0);
                $studentNames[] = (string) ($student->name ?? '');
            }
        }

        $teacherId = (int) ($post['teacher_id'] ?? 0);
        $teacher = Admin::find($teacherId);
        if (!$teacher) {
            $this->error('原教师不存在');
        }

        $replaceTeacherId = (int) ($post['replace_teacher_id'] ?? 0);
        $replaceTeacher = $replaceTeacherId > 0 ? Admin::find($replaceTeacherId) : null;
        $originClassHourId = (int) ($post['origin_class_hour_id'] ?? 0);
        $classHour = $originClassHourId > 0 ? EduClassHour::find($originClassHourId) : null;

        $approveAdminId = (int) ($post['approve_admin_id'] ?? 0);
        $approveAdmin = $approveAdminId > 0 ? Admin::find($approveAdminId) : null;
        $applyAdminId = (int) session('admin.id');

        return [
            'adjust_type' => (int) ($post['adjust_type'] ?? 1),
            'business_status' => (int) ($post['business_status'] ?? 1),
            'class_id' => $classId,
            'class_name' => (string) ($class->name ?? ''),
            'course_id' => (int) ($class->course_id ?? 0),
            'course_name' => (string) ($class->course_name ?? ''),
            'student_id' => empty($studentIds) ? '0' : implode(',', $studentIds),
            'student_name' => empty($studentNames) ? '整班调整' : implode('，', $studentNames),
            'teacher_id' => $teacherId,
            'teacher_name' => (string) ($teacher->realname ?: $teacher->username),
            'replace_teacher_id' => $replaceTeacherId,
            'replace_teacher_name' => (string) ($replaceTeacher ? ($replaceTeacher->realname ?: $replaceTeacher->username) : ''),
            'origin_class_hour_id' => $originClassHourId,
            'origin_class_date' => trim((string) ($post['origin_class_date'] ?? ($classHour->class_date ?? ''))),
            'origin_start_time' => trim((string) ($post['origin_start_time'] ?? ($classHour->start_time ?? ''))),
            'origin_end_time' => trim((string) ($post['origin_end_time'] ?? ($classHour->end_time ?? ''))),
            'target_class_date' => trim((string) ($post['target_class_date'] ?? '')),
            'target_start_time' => trim((string) ($post['target_start_time'] ?? '')),
            'target_end_time' => trim((string) ($post['target_end_time'] ?? '')),
            'target_classroom' => trim((string) ($post['target_classroom'] ?? '')),
            'deduct_hours' => round((float) ($post['deduct_hours'] ?? 0), 2),
            'compensate_hours' => round((float) ($post['compensate_hours'] ?? 0), 2),
            'reason' => trim((string) ($post['reason'] ?? '')),
            'approve_remark' => trim((string) ($post['approve_remark'] ?? '')),
            'apply_admin_id' => $applyAdminId,
            'approve_admin_id' => $approveAdminId,
            'approve_time' => $approveAdmin ? time() : 0,
            'execute_time' => (int) ($post['business_status'] ?? 1) === 4 ? time() : 0,
            'status' => (int) ($post['status'] ?? 1),
        ];
    }

    protected function buildFormViewData(array $formData): array
    {
        $formData['target_scope'] = !empty(trim((string) ($formData['student_name'] ?? '')))
            && trim((string) ($formData['student_name'] ?? '')) !== '整班调整'
            ? 2
            : (int) ($formData['target_scope'] ?? 1);
        $formData['student_keyword'] = $formData['target_scope'] === 2
            ? ($formData['student_name'] ?? ($formData['student_keyword'] ?? ''))
            : '';

        $classQuery = EduClass::where('status', 1)->order('id asc');
        if ($this->isTeacherRole()) {
            $classQuery->where('teacher_id', $this->getCurrentAdminId());
        }
        $teacherQuery = Admin::where('status', 1)->order('id asc')->field('id,username,realname');
        if ($this->isTeacherRole()) {
            $teacherQuery->where('id', $this->getCurrentAdminId());
        }
        $classHourQuery = EduClassHour::where('status', 1)->order('id desc');
        if ($this->isTeacherRole()) {
            $classHourQuery->where('teacher_id', $this->getCurrentAdminId());
        }

        return [
            'formData' => $formData,
            'adjustTypeList' => ScheduleAdjustModel::ADJUST_TYPE_LIST,
            'businessStatusList' => ScheduleAdjustModel::BUSINESS_STATUS_LIST,
            'classList' => $classQuery->field('id,name')->select()->toArray(),
            'teacherList' => $teacherQuery->select()->toArray(),
            'classHourList' => $classHourQuery->field('id,record_no')->select()->toArray(),
            'title' => isset($formData['id']) ? lang('Edit') : lang('Add'),
        ];
    }

    protected function normalizeRow(array $item): array
    {
        $teacherData = $item['teacher'] ?? [];
        $replaceTeacherData = $item['replace_teacher'] ?? ($item['replaceTeacher'] ?? []);

        $item['teacher_name'] = $item['teacher_name'] ?: ($teacherData['realname'] ?? ($teacherData['username'] ?? ''));
        $item['replace_teacher_name'] = $item['replace_teacher_name'] ?: ($replaceTeacherData['realname'] ?? ($replaceTeacherData['username'] ?? ''));
        $item['student_name'] = trim((string) ($item['student_name'] ?? '')) ?: '整班调整';
        $item['adjust_type_text'] = ScheduleAdjustModel::ADJUST_TYPE_LIST[$item['adjust_type'] ?? null] ?? '';
        $item['business_status_text'] = ScheduleAdjustModel::BUSINESS_STATUS_LIST[$item['business_status'] ?? null] ?? '';

        $belongsToReplaceTeacher = (int) ($item['adjust_type'] ?? 0) === 4
            && (int) ($item['replace_teacher_id'] ?? 0) > 0
            && in_array((int) ($item['business_status'] ?? 0), [2, 4], true);

        $item['lesson_owner_type'] = $belongsToReplaceTeacher ? '代课教师' : '原教师';
        $item['lesson_owner_name'] = $belongsToReplaceTeacher
            ? ($item['replace_teacher_name'] ?: '代课教师')
            : ($item['teacher_name'] ?: '原教师');
        $item['lesson_owner_text'] = $item['lesson_owner_type'] . '：' . $item['lesson_owner_name'];
        $item['deduct_hours'] = round((float) ($item['deduct_hours'] ?? 0), 2);
        $item['compensate_hours'] = round((float) ($item['compensate_hours'] ?? 0), 2);
        return $item;
    }
}
