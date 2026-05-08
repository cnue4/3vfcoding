<?php
/**
 * 课时管理控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\Admin;
use app\backend\model\EduClass;
use app\backend\model\EduClassHour as EduClassHourModel;
use app\backend\model\EduLessonDetail;
use app\backend\model\Student;
use app\common\controller\Backend;
use think\App;
use think\facade\Db;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="课时管理")
 */
class ClassHour extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new EduClassHourModel();
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
                ->with(['eduClass', 'teacher'])
                ->where($where);
            $listQuery = $this->modelClass
                ->with(['eduClass', 'teacher'])
                ->where($where);
            $this->applyTeacherTeacherScope($countQuery, 'teacher_id');
            $this->applyTeacherTeacherScope($listQuery, 'teacher_id');

            if ($keyword !== '') {
                $classIds = EduClass::where('name|class_no', 'like', '%' . $keyword . '%')->column('id');
                $teacherIds = Admin::where('username|realname', 'like', '%' . $keyword . '%')->column('id');

                $countQuery->where(function ($query) use ($keyword, $classIds, $teacherIds) {
                    $query->where('record_no|class_date', 'like', '%' . $keyword . '%');
                    if (!empty($classIds)) {
                        $query->whereOr('class_id', 'in', $classIds);
                    }
                    if (!empty($teacherIds)) {
                        $query->whereOr('teacher_id', 'in', $teacherIds);
                    }
                });

                $listQuery->where(function ($query) use ($keyword, $classIds, $teacherIds) {
                    $query->where('record_no|class_date', 'like', '%' . $keyword . '%');
                    if (!empty($classIds)) {
                        $query->whereOr('class_id', 'in', $classIds);
                    }
                    if (!empty($teacherIds)) {
                        $query->whereOr('teacher_id', 'in', $teacherIds);
                    }
                });
            }

            $count = $countQuery->count();
            $list = $listQuery
                ->order($sort)
                ->page($this->page, $this->pageSize)
                ->select()
                ->toArray();
            foreach ($list as &$item) {
                $item = $this->normalizeClassHourRow($item);
            }
            unset($item);
            return json(['code' => 0, 'msg' => lang('get formData success'), 'data' => $list, 'count' => $count]);
        }
        return view();
    }

    /**
     * @NodeAnnotation(title="添加")
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->buildClassHourData($this->request->post());
            if ($this->isTeacherRole()) {
                $data['teacher_id'] = $this->getCurrentAdminId();
                $this->assertTeacherOwnsClass((int) ($data['class_id'] ?? 0));
            }
            $data['record_no'] = EduClassHourModel::generateRecordNo();
            Db::startTrans();
            try {
                $this->modelClass->save($data);
                $this->rebuildLessonDetails((int) $this->modelClass->id);
                Db::commit();
                return json(['code' => 1, 'msg' => lang('operation success')]);
            } catch (\Throwable $e) {
                Db::rollback();
                return json(['code' => 0, 'msg' => $e->getMessage() ?: lang('operation failed')]);
            }
        }
        View::assign($this->buildFormViewData([]));
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
            $this->error('课时记录不存在');
        }
        $this->assertTeacherOwnsTeacher((int) ($model->teacher_id ?? 0));
        if ($this->request->isPost()) {
            $data = $this->buildClassHourData($this->request->post());
            if ($this->isTeacherRole()) {
                $data['teacher_id'] = $this->getCurrentAdminId();
                $this->assertTeacherOwnsClass((int) ($data['class_id'] ?? 0));
            }
            Db::startTrans();
            try {
                $model->save($data);
                $this->rebuildLessonDetails($id);
                Db::commit();
                return json(['code' => 1, 'msg' => lang('operation success')]);
            } catch (\Throwable $e) {
                Db::rollback();
                return json(['code' => 0, 'msg' => $e->getMessage() ?: lang('operation failed')]);
            }
        }
        View::assign($this->buildFormViewData($this->normalizeClassHourRow($model->toArray())));
        return view('add');
    }

    /**
     * @NodeAnnotation(title="查看")
     */
    public function view()
    {
        $id = (int) $this->request->param('id');
        $model = $this->modelClass->with(['eduClass', 'teacher'])->find($id);
        if (!$model) {
            $this->error('课时记录不存在');
        }
        $this->assertTeacherOwnsTeacher((int) ($model->teacher_id ?? 0));
        $detailList = EduLessonDetail::with(['student'])->where('class_hour_id', $id)->whereNull('delete_time')->order('id asc')->select()->toArray();
        View::assign(['formData' => $this->normalizeClassHourRow($model->toArray()), 'detailList' => $detailList]);
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
        Db::startTrans();
        try {
            foreach ($list as $item) {
                $this->assertTeacherOwnsTeacher((int) ($item->teacher_id ?? 0));
                $studentIds = EduLessonDetail::where('class_hour_id', (int) $item->id)->whereNull('delete_time')->column('student_id');
                EduLessonDetail::where('class_hour_id', (int) $item->id)->delete();
                $item->delete();
                $this->refreshClassHourStats((int) $item->class_id);
                foreach (array_unique(array_map('intval', $studentIds)) as $studentId) {
                    $this->refreshStudentHourSnapshot($studentId);
                }
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            $this->error(lang($e->getMessage()));
        }
        $this->success(lang('operation success'));
    }

    protected function buildClassHourData(array $post): array
    {
        $post['status'] = (!isset($post['status']) || $post['status'] === '') ? 1 : $post['status'];
        $post['attendance_mode'] = (!isset($post['attendance_mode']) || $post['attendance_mode'] === '') ? 1 : $post['attendance_mode'];
        $rule = [
            'class_id|班级' => 'require|number',
            'teacher_id|上课老师' => 'require|number',
            'class_date|上课日期' => 'require|date',
            'start_time|开始时间' => 'require',
            'end_time|结束时间' => 'require',
            'actual_hours|实际课时' => 'require|regex:/^\d+(\.\d+)?$/',
            'attendance_mode|扣课模式' => 'require|in:1,2',
            'status|状态' => 'in:0,1',
        ];
        $this->validate($post, $rule);
        $classId = (int) ($post['class_id'] ?? 0);
        $teacherId = (int) ($post['teacher_id'] ?? 0);
        $class = EduClass::find($classId);
        if (!$class) {
            $this->error('班级不存在');
        }
        $this->assertTeacherOwnsClass($classId);
        if (!Admin::find($teacherId)) {
            $this->error('上课老师不存在');
        }
        $leaveStudentIds = $this->normalizeLeaveStudentIds((string) ($post['leave_student_ids'] ?? ''), $classId);
        return [
            'class_id' => $classId,
            'teacher_id' => $teacherId,
            'class_date' => trim((string) ($post['class_date'] ?? '')),
            'start_time' => trim((string) ($post['start_time'] ?? '')),
            'end_time' => trim((string) ($post['end_time'] ?? '')),
            'actual_hours' => round((float) ($post['actual_hours'] ?? 0), 2),
            'attendance_mode' => (int) ($post['attendance_mode'] ?? 1),
            'leave_student_ids' => $leaveStudentIds,
            'status' => (int) ($post['status'] ?? 1),
        ];
    }

    protected function buildFormViewData(array $formData): array
    {
        $classQuery = EduClass::where('status', 1)->order('id asc');
        if ($this->isTeacherRole()) {
            $classQuery->where('teacher_id', $this->getCurrentAdminId());
        }
        $teacherQuery = Admin::where('status', 1)->order('id asc')->field('id,username,realname');
        if ($this->isTeacherRole()) {
            $teacherQuery->where('id', $this->getCurrentAdminId());
        }
        if (!isset($formData['status']) || $formData['status'] === '') {
            $formData['status'] = 1;
        }
        if (!isset($formData['attendance_mode']) || $formData['attendance_mode'] === '') {
            $formData['attendance_mode'] = 1;
        }
        return [
            'formData' => $formData,
            'classList' => $classQuery->column('name', 'id'),
            'teacherList' => $teacherQuery->select()->toArray(),
            'attendanceModeList' => EduClassHourModel::ATTENDANCE_MODE_LIST,
            'title' => isset($formData['id']) ? lang('Edit') : lang('Add'),
        ];
    }

    protected function normalizeClassHourRow(array $item): array
    {
        $classData = $item['eduClass'] ?? ($item['edu_class'] ?? []);
        $teacherData = $item['teacher'] ?? [];
        $item['class_name'] = $classData['name'] ?? ($item['class_name'] ?? '');
        $item['teacher_name'] = $teacherData['realname'] ?? ($teacherData['username'] ?? ($item['teacher_name'] ?? ''));
        $item['status_text'] = EduClassHourModel::STATUS_LIST[$item['status'] ?? null] ?? '';
        $item['attendance_mode_text'] = EduClassHourModel::ATTENDANCE_MODE_LIST[$item['attendance_mode'] ?? null] ?? '';
        $item['actual_hours'] = (float) ($item['actual_hours'] ?? 0);
        $item['leave_student_names'] = $this->buildLeaveStudentNames((int) ($item['class_id'] ?? 0), (string) ($item['leave_student_ids'] ?? ''));
        return $item;
    }

    protected function rebuildLessonDetails(int $classHourId): void
    {
        $record = $this->modelClass->find($classHourId);
        if (!$record) {
            return;
        }
        $classId = (int) $record->class_id;
        $oldStudentIds = EduLessonDetail::where('class_hour_id', $classHourId)->whereNull('delete_time')->column('student_id');
        EduLessonDetail::where('class_hour_id', $classHourId)->delete();
        $students = Student::where('class_id', $classId)->where('student_status', 1)->where('status', 1)->whereNull('delete_time')->select();
        $studentIds = array_values(array_unique(array_merge(array_map('intval', $oldStudentIds), array_map('intval', $students->column('id')))));
        $leaveIds = array_filter(array_map('intval', explode(',', (string) $record->leave_student_ids)));
        foreach ($students as $student) {
            $attendanceStatus = 1;
            $deductHours = (float) $record->actual_hours;
            $remark = '正常扣课';
            if ((int) $record->attendance_mode === 2 && in_array((int) $student->id, $leaveIds, true)) {
                $attendanceStatus = 2;
                $deductHours = 0;
                $remark = '请假未扣课';
            }
            EduLessonDetail::create([
                'class_hour_id' => $classHourId,
                'student_id' => (int) $student->id,
                'class_id' => $classId,
                'teacher_id' => (int) $record->teacher_id,
                'class_date' => $record->class_date,
                'deduct_hours' => $deductHours,
                'student_total_hours' => (float) $student->total_hours,
                'student_remaining_hours' => max((float) $student->remaining_hours - $deductHours, 0),
                'attendance_status' => $attendanceStatus,
                'remark' => $remark,
                'status' => (int) $record->status,
            ]);
        }
        $this->refreshClassHourStats($classId);
        foreach ($studentIds as $studentId) {
            $this->refreshStudentHourSnapshot($studentId);
        }
    }

    protected function refreshStudentHourSnapshot(int $studentId): void
    {
        if ($studentId <= 0) {
            return;
        }

        $student = Student::find($studentId);
        if (!$student) {
            return;
        }

        $classId = (int) ($student->class_id ?? 0);
        if ($classId <= 0) {
            $student->save([
                'total_hours' => 0,
                'remaining_hours' => 0,
            ]);
            return;
        }

        Student::syncHourSnapshotByStudent($studentId);
    }

    protected function normalizeLeaveStudentIds(string $value, int $classId = 0): string
    {
        $rawValues = array_values(array_filter(array_map('trim', preg_split('/[，,、\s]+/u', $value))));
        if (empty($rawValues)) {
            return '';
        }

        $nameToIds = [];
        if ($classId > 0) {
            $students = Student::where('class_id', $classId)->whereNull('delete_time')->field('id,name')->select()->toArray();
            foreach ($students as $student) {
                $studentName = trim((string) ($student['name'] ?? ''));
                if ($studentName === '') {
                    continue;
                }
                $nameToIds[$studentName][] = (int) ($student['id'] ?? 0);
            }
        }

        $ids = [];
        foreach ($rawValues as $rawValue) {
            if (preg_match('/^\d+$/', $rawValue)) {
                $ids[] = (int) $rawValue;
                continue;
            }
            if (isset($nameToIds[$rawValue])) {
                foreach ($nameToIds[$rawValue] as $studentId) {
                    $ids[] = (int) $studentId;
                }
            }
        }

        $ids = array_values(array_unique(array_filter($ids)));
        return implode(',', $ids);
    }

    protected function buildLeaveStudentNames(int $classId, string $leaveStudentIds): string
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $leaveStudentIds)))));
        if ($classId <= 0 || empty($ids)) {
            return '';
        }

        $nameMap = Student::where('class_id', $classId)
            ->whereIn('id', $ids)
            ->whereNull('delete_time')
            ->column('name', 'id');

        $names = [];
        foreach ($ids as $studentId) {
            $name = trim((string) ($nameMap[$studentId] ?? ''));
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return implode('，', $names);
    }

    protected function refreshClassHourStats(int $classId): void
    {
        if ($classId <= 0) {
            return;
        }
        $class = EduClass::find($classId);
        if (!$class) {
            return;
        }
        $usedHours = (float) EduClassHourModel::where('class_id', $classId)->where('status', 1)->sum('actual_hours');
        $totalHours = (float) ($class->total_hours ?? 0);
        $class->save(['used_hours' => $usedHours, 'remaining_hours' => max($totalHours - $usedHours, 0)]);
    }

    protected function checkToken()
    {
        return true;
    }
}
