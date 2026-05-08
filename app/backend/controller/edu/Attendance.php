<?php
/**
 * 学生考勤控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\Admin;
use app\backend\model\EduClass;
use app\backend\model\EduClassHour;
use app\backend\model\ScheduleAdjust;
use app\backend\model\Student;
use app\backend\model\StudentAttendance as StudentAttendanceModel;
use app\common\controller\Backend;
use think\App;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="学生考勤")
 * Class Attendance
 * @package app\backend\controller\edu
 */
class Attendance extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new StudentAttendanceModel();
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

            $query = $this->modelClass->with(['eduClass', 'student', 'teacher', 'classHour'])->order(['id' => 'desc']);
            if ($keyword !== '') {
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->whereLike('attendance_no', "%{$keyword}%")
                        ->whereOrLike('class_name', "%{$keyword}%")
                        ->whereOrLike('student_name', "%{$keyword}%")
                        ->whereOrLike('teacher_name', "%{$keyword}%")
                        ->whereOrLike('remark', "%{$keyword}%");
                });
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
            'attendanceStatusList' => StudentAttendanceModel::ATTENDANCE_STATUS_LIST,
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
            $data['attendance_no'] = StudentAttendanceModel::generateAttendanceNo();
            if ($this->modelClass->save($data)) {
                $this->success(lang('operation success'));
            }
            $this->error(lang('operation failed'));
        }

        View::assign($this->buildFormViewData([
            'attendance_status' => 1,
            'status' => 1,
            'deduct_hours' => 0,
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
            $this->error('考勤记录不存在');
        }

        if ($this->request->isPost()) {
            $data = $this->buildSaveData($this->request->post(), $id);
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
        $model = $this->modelClass->with(['eduClass', 'student', 'teacher', 'classHour'])->find($id);
        if (!$model) {
            $this->error('考勤记录不存在');
        }

        View::assign(['formData' => $this->normalizeRow($model->toArray())]);
        return view();
    }

    /**
     * @NodeAnnotation(title="删除")
     */
    public function delete()
    {
        $id = (int) $this->request->param('id');
        if (!$id) {
            $this->error(lang('id error'));
        }

        $model = $this->modelClass->find($id);
        if (!$model) {
            $this->error('考勤记录不存在');
        }

        if ($model->delete()) {
            $this->success(lang('operation success'));
        }
        $this->error(lang('operation failed'));
    }

    protected function buildSaveData(array $post, int $id = 0): array
    {
        $rule = [
            'class_hour_id|课时记录' => 'require|number',
            'class_id|班级' => 'require|number',
            'student_id|学生' => 'require|number',
            'teacher_id|教师' => 'require|number',
            'attendance_date|考勤日期' => 'require|date',
            'attendance_status|考勤状态' => 'require|in:1,2,3,4,5,6',
            'minutes_late|迟到分钟' => 'number',
            'minutes_leave_early|早退分钟' => 'number',
            'deduct_hours|扣减课时' => 'number',
            'makeup_schedule_adjust_id|补课调整单' => 'number',
            'remark|备注' => 'max:500',
            'status|状态' => 'require|in:1,2',
        ];
        $this->validate($post, $rule);

        $classHourId = (int) ($post['class_hour_id'] ?? 0);
        $classHour = EduClassHour::find($classHourId);
        if (!$classHour) {
            $this->error('课时记录不存在');
        }

        $classId = (int) ($post['class_id'] ?? 0);
        $class = EduClass::find($classId);
        if (!$class) {
            $this->error('班级不存在');
        }

        $studentId = (int) ($post['student_id'] ?? 0);
        $student = Student::find($studentId);
        if (!$student) {
            $this->error('学生不存在');
        }

        $teacherId = (int) ($post['teacher_id'] ?? 0);
        $teacher = Admin::find($teacherId);
        if (!$teacher) {
            $this->error('教师不存在');
        }

        $makeupId = (int) ($post['makeup_schedule_adjust_id'] ?? 0);
        if ($makeupId > 0 && !ScheduleAdjust::find($makeupId)) {
            $this->error('补课调整单不存在');
        }

        $existsQuery = $this->modelClass->where('class_hour_id', $classHourId)->where('student_id', $studentId);
        if ($id > 0) {
            $existsQuery->where('id', '<>', $id);
        }
        if ($existsQuery->find()) {
            $this->error('同一课时下该学生考勤已存在');
        }

        return [
            'class_hour_id' => $classHourId,
            'class_id' => $classId,
            'class_name' => (string) ($class->name ?? ''),
            'course_id' => (int) ($class->course_id ?? 0),
            'course_name' => (string) ($class->course_name ?? ''),
            'student_id' => $studentId,
            'student_name' => (string) ($student->name ?? ''),
            'teacher_id' => $teacherId,
            'teacher_name' => (string) ($teacher->realname ?: $teacher->username),
            'attendance_date' => trim((string) ($post['attendance_date'] ?? '')),
            'attendance_status' => (int) ($post['attendance_status'] ?? 1),
            'minutes_late' => max((int) ($post['minutes_late'] ?? 0), 0),
            'minutes_leave_early' => max((int) ($post['minutes_leave_early'] ?? 0), 0),
            'deduct_hours' => round((float) ($post['deduct_hours'] ?? 0), 2),
            'makeup_schedule_adjust_id' => $makeupId,
            'remark' => trim((string) ($post['remark'] ?? '')),
            'operator_id' => (int) session('admin.id'),
            'sign_time' => time(),
            'status' => (int) ($post['status'] ?? 1),
        ];
    }

    protected function buildFormViewData(array $formData): array
    {
        return [
            'formData' => $formData,
            'attendanceStatusList' => StudentAttendanceModel::ATTENDANCE_STATUS_LIST,
            'classHourList' => EduClassHour::where('status', 1)->order('id desc')->column('record_no', 'id'),
            'classList' => EduClass::where('status', 1)->order('id asc')->column('name', 'id'),
            'studentList' => Student::where('status', 1)->order('id asc')->column('name', 'id'),
            'teacherList' => Admin::where('status', 1)->order('id asc')->column('username', 'id'),
            'scheduleAdjustList' => ScheduleAdjust::where('adjust_type', 2)->where('status', 1)->order('id desc')->column('adjust_no', 'id'),
            'title' => isset($formData['id']) ? lang('Edit') : lang('Add'),
        ];
    }

    protected function normalizeRow(array $item): array
    {
        $item['attendance_status_text'] = StudentAttendanceModel::ATTENDANCE_STATUS_LIST[$item['attendance_status'] ?? null] ?? '';
        $item['deduct_hours'] = round((float) ($item['deduct_hours'] ?? 0), 2);
        return $item;
    }
}
