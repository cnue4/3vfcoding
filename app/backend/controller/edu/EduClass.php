<?php
/**
 * 班级管理控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\EduClass as EduClassModel;
use app\backend\model\EduCourse;
use app\backend\model\EduClassHour;
use app\backend\model\Admin;
use app\backend\model\Student;
use app\common\controller\Backend;
use think\App;
use think\facade\Cache;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="班级管理")
 * Class EduClass
 * @package app\backend\controller\edu
 */
class EduClass extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new EduClassModel();
    }

    /**
     * @NodeAnnotation(title="列表")
     * @return mixed|\think\response\Json|\think\response\View
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            if ($this->request->param('selectFields')) {
                $this->selectList();
            }

            list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();
            $sort = ['id' => 'asc'];
            $keyword = trim((string) $this->request->param('keyword', ''));

            $query = $this->modelClass
                ->with(['eduCourse', 'teacher'])
                ->where($where);
            $this->applyTeacherTeacherScope($query, 'teacher_id');

            if ($keyword !== '') {
                $courseIds = EduCourse::where('name|course_no', 'like', '%' . $keyword . '%')->column('id');
                $teacherIds = Admin::where('username|realname', 'like', '%' . $keyword . '%')->column('id');
                $matchedIds = $this->modelClass->where('name|class_no|classroom|class_time', 'like', '%' . $keyword . '%')->column('id');

                if (!empty($courseIds)) {
                    $matchedIds = array_merge($matchedIds, $this->modelClass->where('course_id', 'in', $courseIds)->column('id'));
                }
                if (!empty($teacherIds)) {
                    $matchedIds = array_merge($matchedIds, $this->modelClass->where('teacher_id', 'in', $teacherIds)->column('id'));
                }

                $matchedIds = array_values(array_unique(array_filter($matchedIds)));
                $query->where('id', 'in', !empty($matchedIds) ? $matchedIds : [0]);
            }

            $count = $query->count();

            $list = $query
                ->order($sort)
                ->page($this->page, $this->pageSize)
                ->select()
                ->toArray();

            foreach ($list as &$item) {
                $item = $this->normalizeClassRow($item);
            }
            unset($item);

            $result = $this->sanitizeUtf8([
                'code' => 0,
                'msg' => lang('get formData success'),
                'data' => $list,
                'count' => $count,
            ]);
            return json($result);
        }

        return view();
    }

    /**
     * @NodeAnnotation(title="添加")
     * @return \think\response\View
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();

            $rule = [
                'name|班级名称' => 'require|max:100',
                'course_id|课程' => 'require',
                'teacher_id|教师' => 'require',
                'classroom|教室' => 'require|max:100',
                'max_students|最大人数' => 'require|number',
                'total_hours|总课时' => 'require|regex:/^\d+(\.\d+)?$/',
                'class_weekday|上课星期' => 'require|in:1,2,3,4,5,6,7',
                'class_start_time|开始时间' => 'require',
                'class_end_time|结束时间' => 'require',
            ];
            $this->validate($post, $rule);
            if (($post['class_start_time'] ?? '') >= ($post['class_end_time'] ?? '')) {
                $this->error('结束时间必须大于开始时间');
            }

            $post['class_no'] = EduClassModel::generateClassNo();
            if ($this->isTeacherRole()) {
                $post['teacher_id'] = $this->getCurrentAdminId();
            }
            $data = array_merge($this->transformClassData($post), ['class_no' => $post['class_no']]);

            $result = $this->modelClass->save($data);

            if ($result) {
                $this->refreshClassHourStats((int) $this->modelClass->id);
                Student::syncHoursByClassTemplate((int) $this->modelClass->id);
                $this->refreshBackendCache();
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('operation failed'));
            }
        }

        $courseList = EduCourse::order('id asc')->field('id,name')->select()->toArray();
        $teacherListQuery = Admin::where('status', 1)->order('id asc')->field('id,username');
        if ($this->isTeacherRole()) {
            $teacherListQuery->where('id', $this->getCurrentAdminId());
        }
        $teacherList = $teacherListQuery->select()->toArray();

        $view = [
            'formData' => [],
            'courseList' => $courseList,
            'teacherList' => $teacherList,
            'statusList' => EduClassModel::STATUS_LIST,
            'weekdayList' => EduClassModel::WEEKDAY_LIST,
            'title' => lang('Add'),
        ];
        View::assign($view);
        return view();
    }

    /**
     * @NodeAnnotation(title="编辑")
     * @return \think\response\View
     */
    public function edit()
    {
        $id = $this->request->param('id');

        if ($this->request->isPost()) {
            $post = $this->request->post();

            $rule = [
                'name|班级名称' => 'require|max:100',
                'course_id|课程' => 'require',
                'teacher_id|教师' => 'require',
                'classroom|教室' => 'require|max:100',
                'max_students|最大人数' => 'require|number',
                'total_hours|总课时' => 'require|regex:/^\d+(\.\d+)?$/',
                'class_weekday|上课星期' => 'require|in:1,2,3,4,5,6,7',
                'class_start_time|开始时间' => 'require',
                'class_end_time|结束时间' => 'require',
            ];
            $this->validate($post, $rule);
            if (($post['class_start_time'] ?? '') >= ($post['class_end_time'] ?? '')) {
                $this->error('结束时间必须大于开始时间');
            }

            $model = $this->modelClass->find($id);
            if (!$model) {
                $this->error('班级不存在');
            }
            $this->assertTeacherOwnsTeacher((int) ($model->teacher_id ?? 0));
            $oldTotalHours = (float) ($model->total_hours ?? 0);
            if ($this->isTeacherRole()) {
                $post['teacher_id'] = $this->getCurrentAdminId();
            }
            $data = $this->transformClassData($post);
            $result = $model->save($data);

            if ($result) {
                $this->refreshClassHourStats((int) $model->id);
                if ($oldTotalHours !== (float) ($data['total_hours'] ?? 0)) {
                    Student::syncHoursByClassTemplate((int) $model->id);
                }
                $this->refreshBackendCache();
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('operation failed'));
            }
        }

        $formDataModel = $this->modelClass->find($id);
        if (!$formDataModel) {
            $this->error('班级不存在');
        }
        $this->assertTeacherOwnsTeacher((int) ($formDataModel->teacher_id ?? 0));
        $formData = $this->normalizeClassRow($formDataModel->toArray());
        $courseList = EduCourse::order('id asc')->field('id,name')->select()->toArray();
        $teacherListQuery = Admin::where('status', 1)->order('id asc')->field('id,username');
        if ($this->isTeacherRole()) {
            $teacherListQuery->where('id', $this->getCurrentAdminId());
        }
        $teacherList = $teacherListQuery->select()->toArray();

        $view = [
            'formData' => $formData,
            'courseList' => $courseList,
            'teacherList' => $teacherList,
            'statusList' => EduClassModel::STATUS_LIST,
            'weekdayList' => EduClassModel::WEEKDAY_LIST,
            'title' => lang('Edit'),
        ];
        View::assign($view);
        return view('add');
    }

    /**
     * 班级管理临时关闭 token 校验
     */
    protected function checkToken()
    {
        return true;
    }

    /**
     * 兼容当前数据库班级字段，并向最终字段结构收口
     */
    protected function transformClassData(array $post): array
    {
        $weekday = isset($post['class_weekday']) ? (int) $post['class_weekday'] : 0;
        $startTime = (string) ($post['class_start_time'] ?? '');
        $endTime = (string) ($post['class_end_time'] ?? '');
        $classTime = '';
        if ($weekday && $startTime !== '' && $endTime !== '') {
            $classTime = (EduClassModel::WEEKDAY_LIST[$weekday] ?? '') . ' ' . $startTime . '-' . $endTime;
        }

        return [
            'name' => $post['name'] ?? '',
            'course_id' => isset($post['course_id']) ? (int) $post['course_id'] : 0,
            'teacher_id' => isset($post['teacher_id']) ? (int) $post['teacher_id'] : 0,
            'classroom' => $post['classroom'] ?? '',
            'max_students' => isset($post['max_students']) ? (int) $post['max_students'] : 0,
            'total_hours' => (float) ($post['total_hours'] ?? 0),
            'class_weekday' => $weekday,
            'class_start_time' => $startTime,
            'class_end_time' => $endTime,
            'class_time' => $classTime,
            'current_students' => isset($post['current_students']) ? (int) $post['current_students'] : 0,
            'status' => isset($post['status']) ? (int) $post['status'] : 1,
        ];
    }

    /**
     * 统一列表/表单展示字段
     */
    protected function normalizeClassRow(array $item): array
    {
        $courseData = $item['eduCourse'] ?? ($item['edu_course'] ?? []);
        $teacherData = $item['teacher'] ?? [];
        $currentStudents = Student::where('class_id', $item['id'])->where('status', 1)->count();

        $item['course_name'] = $courseData['name'] ?? ($item['course_name'] ?? '');
        $item['teacher_name'] = $teacherData['username'] ?? ($item['teacher_name'] ?? '');
        $item['classroom'] = $item['classroom'] ?? '';
        $item['max_students'] = (int) ($item['max_students'] ?? 0);
        $item['current_students'] = $currentStudents;
        $item['total_hours'] = (float) ($item['total_hours'] ?? 0);
        $item['used_hours'] = $this->calculateUsedHours((int) ($item['id'] ?? 0));
        $item['remaining_hours'] = max($item['total_hours'] - $item['used_hours'], 0);
        $item['class_weekday'] = isset($item['class_weekday']) ? (int) $item['class_weekday'] : $this->parseClassWeekday($item['class_time'] ?? '');
        $item['class_start_time'] = $item['class_start_time'] ?? $this->parseClassTimePart($item['class_time'] ?? '', 'start');
        $item['class_end_time'] = $item['class_end_time'] ?? $this->parseClassTimePart($item['class_time'] ?? '', 'end');
        $item['class_time'] = $item['class_time'] ?? '';

        return $this->sanitizeUtf8($item);
    }

    protected function parseClassWeekday(string $classTime): int
    {
        foreach (EduClassModel::WEEKDAY_LIST as $value => $label) {
            if (mb_strpos($classTime, $label) !== false) {
                return (int) $value;
            }
        }
        return 1;
    }

    protected function parseClassTimePart(string $classTime, string $part): string
    {
        if (preg_match('/(\d{2}:\d{2})-(\d{2}:\d{2})/', $classTime, $match)) {
            return $part === 'end' ? $match[2] : $match[1];
        }
        return $part === 'end' ? '01:00' : '00:00';
    }

    protected function calculateUsedHours(int $classId): float
    {
        if ($classId <= 0) {
            return 0;
        }

        return (float) EduClassHour::where('class_id', $classId)
            ->where('status', 1)
            ->sum('actual_hours');
    }

    protected function refreshClassHourStats(int $classId): void
    {
        if ($classId <= 0) {
            return;
        }

        $class = $this->modelClass->find($classId);
        if (!$class) {
            return;
        }

        $totalHours = (float) ($class->total_hours ?? 0);
        $usedHours = $this->calculateUsedHours($classId);
        $remainingHours = max($totalHours - $usedHours, 0);

        $class->save([
            'used_hours' => $usedHours,
            'remaining_hours' => $remainingHours,
        ]);
    }

    /**
     * 清理数组中的异常编码，避免 JSON 输出时报 UTF-8 错误
     */
    protected function sanitizeUtf8($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->sanitizeUtf8($value);
            }
            return $data;
        }

        if (is_string($data) && !mb_check_encoding($data, 'UTF-8')) {
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8,GBK,GB2312,BIG5');
        }

        return $data;
    }

    /**
     * 刷新后台缓存，避免手动刷新才能看到最新数据
     */
    protected function refreshBackendCache(): void
    {
        Cache::clear();
    }

    /**
     * @NodeAnnotation(title="删除")
     */
    public function delete()
    {
        $ids = $this->request->param('ids') ? $this->request->param('ids') : $this->request->param('id');
        if (empty($ids)) {
            $this->error(lang('Ids can not empty'));
        }

        $list = $this->modelClass->where('id', 'in', $ids)->select();
        foreach ($list as $item) {
            $classId = (int) ($item->id ?? 0);
            $studentCount = Student::where('class_id', $classId)
                ->whereNull('delete_time')
                ->count();
            if ($studentCount > 0) {
                $this->error('该班级下仍有学生，无法删除，请先转班或移出学生后再删除');
            }
        }

        try {
            foreach ($list as $item) {
                $item->delete();
            }
            $this->refreshBackendCache();
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }

        $this->success(lang('operation success'));
    }

    /**
     * @NodeAnnotation(title="查看")
     */
    public function view()
    {
        $id = (int) $this->request->param('id');
        $classModel = $this->modelClass->with(['eduCourse', 'teacher'])->find($id);
        if (!$classModel) {
            $this->error('班级不存在');
        }

        $formData = $this->normalizeClassRow($classModel->toArray());
        $studentList = Student::with(['eduClass', 'eduCourse'])
            ->where('class_id', $id)
            ->where('status', 1)
            ->order('id asc')
            ->select()
            ->toArray();

        foreach ($studentList as &$student) {
            $student['gender_text'] = Student::GENDER_LIST[$student['gender'] ?? null] ?? '';
            $student['student_status_text'] = Student::STUDENT_STATUS_LIST[$student['student_status'] ?? null] ?? '';
            $student['status_text'] = Student::STATUS_LIST[$student['status'] ?? null] ?? '';
            $student['class_name'] = $student['eduClass']['name'] ?? $formData['name'] ?? '';
            $student['course_name'] = $student['eduCourse']['name'] ?? $formData['course_name'] ?? '';
            $student['total_hours'] = round((float) ($student['total_hours'] ?? 0), 2);
            $student['remaining_hours'] = round((float) ($student['remaining_hours'] ?? 0), 2);
        }
        unset($student);

        $view = [
            'formData' => $formData,
            'studentList' => $studentList,
            'statusList' => EduClassModel::STATUS_LIST,
        ];
        View::assign($view);
        return view();
    }
}
