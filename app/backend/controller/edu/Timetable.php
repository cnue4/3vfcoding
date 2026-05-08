<?php
/**
 * 课程表控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\Admin;
use app\backend\model\EduClass as EduClassModel;
use app\common\controller\Backend;
use think\App;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="课程表")
 * Class Timetable
 * @package app\backend\controller\edu
 */
class Timetable extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    /**
     * @NodeAnnotation(title="列表")
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $teacher = $this->resolveTeacherFromRequest();
            if (!$teacher) {
                return json(['code' => 0, 'msg' => '未找到该教师账号', 'data' => []]);
            }

            $teacherData = $teacher->toArray();
            $scheduleData = $this->buildTeacherTimetable((int) $teacherData['id']);

            return json([
                'code' => 1,
                'msg' => '获取成功',
                'data' => [
                    'teacher' => $this->buildTeacherPayload($teacherData),
                    'can_switch_teacher' => $this->canSwitchTeacher(),
                    'current_username' => $this->getCurrentAdminUsername(),
                    'weekday_list' => EduClassModel::WEEKDAY_LIST,
                    'summary' => $scheduleData['summary'],
                    'today_summary' => $scheduleData['today_summary'],
                    'conflicts' => $scheduleData['conflicts'],
                    'days' => $scheduleData['days'],
                ],
            ]);
        }

        View::assign([
            'currentUsername' => $this->getCurrentAdminUsername(),
            'canSwitchTeacher' => $this->canSwitchTeacher(),
            'weekdayList' => EduClassModel::WEEKDAY_LIST,
        ]);
        return view();
    }

    /**
     * @NodeAnnotation(title="导出Excel")
     */
    public function export()
    {
        $teacher = $this->resolveTeacherFromRequest();
        if (!$teacher) {
            $this->error('未找到该教师账号');
        }

        $teacherData = $teacher->toArray();
        $scheduleData = $this->buildTeacherTimetable((int) $teacherData['id']);
        $filename = 'timetable_' . ($teacherData['username'] ?? 'teacher') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $content = "\xEF\xBB\xBF";
        $content .= $this->buildCsvLine(['教师账号', '教师姓名', '星期', '时间', '班级编号', '班级名称', '课程', '教室', '人数', '冲突状态']);
        foreach ($scheduleData['days'] as $day) {
            foreach (($day['items'] ?? []) as $item) {
                $content .= $this->buildCsvLine([
                    $teacherData['username'] ?? '',
                    $teacherData['realname'] ?? '',
                    $day['weekday_name'] ?? '',
                    $item['time_range'] ?? '',
                    $item['class_no'] ?? '',
                    $item['class_name'] ?? '',
                    $item['course_name'] ?? '',
                    $item['classroom'] ?? '',
                    ($item['current_students'] ?? 0) . '/' . ($item['max_students'] ?? 0),
                    !empty($item['has_conflict']) ? '时间冲突' : '正常',
                ]);
            }
        }

        return response($content, 200, $headers);
    }

    /**
     * @NodeAnnotation(title="打印版")
     */
    public function printView()
    {
        $teacher = $this->resolveTeacherFromRequest();
        if (!$teacher) {
            $this->error('未找到该教师账号');
        }

        $teacherData = $teacher->toArray();
        $scheduleData = $this->buildTeacherTimetable((int) $teacherData['id']);

        View::assign([
            'teacher' => $this->buildTeacherPayload($teacherData),
            'summary' => $scheduleData['summary'],
            'todaySummary' => $scheduleData['today_summary'],
            'conflicts' => $scheduleData['conflicts'],
            'days' => $scheduleData['days'],
        ]);
        return view('print');
    }

    protected function resolveTeacherFromRequest()
    {
        $currentUsername = $this->getCurrentAdminUsername();
        $username = trim((string) $this->request->param('username', $currentUsername));
        if ($username === '') {
            $username = $currentUsername;
        }
        if (!$this->canSwitchTeacher()) {
            $username = $currentUsername;
        }

        return Admin::where('status', 1)
            ->where('username', $username)
            ->field('id,username,realname,email,mobile')
            ->find();
    }

    protected function buildTeacherPayload(array $teacherData): array
    {
        return [
            'id' => (int) ($teacherData['id'] ?? 0),
            'username' => $teacherData['username'] ?? '',
            'realname' => $teacherData['realname'] ?? '',
            'email' => $teacherData['email'] ?? '',
            'mobile' => $teacherData['mobile'] ?? '',
        ];
    }

    protected function buildTeacherTimetable(int $teacherId): array
    {
        $classList = EduClassModel::with(['eduCourse'])
            ->where('teacher_id', $teacherId)
            ->where('status', 1)
            ->order(['class_weekday' => 'asc', 'class_start_time' => 'asc', 'id' => 'asc'])
            ->select()
            ->toArray();

        $normalized = [];
        foreach ($classList as $item) {
            $normalized[] = $this->normalizeTimetableRow($item);
        }

        $conflictMap = $this->buildConflictMap($normalized);
        $days = [];
        $conflictRows = [];
        $totalClassCount = 0;
        $conflictClassCount = 0;
        $todayWeekday = (int) date('N');
        $todayItems = [];

        foreach (EduClassModel::WEEKDAY_LIST as $weekday => $weekdayName) {
            $days[$weekday] = ['weekday' => (int) $weekday, 'weekday_name' => $weekdayName, 'items' => []];
        }

        foreach ($normalized as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            $conflictIds = $conflictMap[$itemId] ?? [];
            $item['conflict_ids'] = array_values($conflictIds);
            $item['has_conflict'] = !empty($conflictIds);
            $item['conflict_text'] = $item['has_conflict'] ? '时间冲突' : '正常';

            $totalClassCount++;
            if ($item['has_conflict']) {
                $conflictClassCount++;
                $conflictRows[] = [
                    'id' => $itemId,
                    'class_name' => $item['class_name'],
                    'course_name' => $item['course_name'],
                    'weekday_name' => $item['weekday_name'],
                    'time_range' => $item['time_range'],
                    'classroom' => $item['classroom'],
                ];
            }

            $weekday = (int) ($item['class_weekday'] ?? 1);
            $days[$weekday]['items'][] = $item;
            if ($weekday === $todayWeekday) {
                $todayItems[] = $item;
            }
        }

        usort($todayItems, function ($a, $b) {
            return strcmp((string) ($a['class_start_time'] ?? ''), (string) ($b['class_start_time'] ?? ''));
        });

        return [
            'summary' => [
                'total_classes' => $totalClassCount,
                'conflict_classes' => $conflictClassCount,
                'normal_classes' => max($totalClassCount - $conflictClassCount, 0),
            ],
            'today_summary' => [
                'weekday' => $todayWeekday,
                'weekday_name' => EduClassModel::WEEKDAY_LIST[$todayWeekday] ?? '今天',
                'total_classes' => count($todayItems),
                'first_start_time' => $todayItems[0]['class_start_time'] ?? '',
                'last_end_time' => $todayItems ? $todayItems[count($todayItems) - 1]['class_end_time'] : '',
            ],
            'conflicts' => $conflictRows,
            'days' => array_values($days),
        ];
    }

    protected function normalizeTimetableRow(array $item): array
    {
        $weekday = isset($item['class_weekday']) ? (int) $item['class_weekday'] : $this->parseClassWeekday($item['class_time'] ?? '');
        $startTime = $item['class_start_time'] ?? $this->parseClassTimePart($item['class_time'] ?? '', 'start');
        $endTime = $item['class_end_time'] ?? $this->parseClassTimePart($item['class_time'] ?? '', 'end');
        $courseData = $item['eduCourse'] ?? ($item['edu_course'] ?? []);

        return [
            'id' => (int) ($item['id'] ?? 0),
            'class_no' => $item['class_no'] ?? '',
            'class_name' => $item['name'] ?? '',
            'course_name' => $courseData['name'] ?? ($item['course_name'] ?? ''),
            'classroom' => $item['classroom'] ?? '',
            'class_weekday' => $weekday,
            'weekday_name' => EduClassModel::WEEKDAY_LIST[$weekday] ?? '',
            'class_start_time' => $startTime,
            'class_end_time' => $endTime,
            'time_range' => $startTime . '-' . $endTime,
            'current_students' => (int) ($item['current_students'] ?? 0),
            'max_students' => (int) ($item['max_students'] ?? 0),
        ];
    }

    protected function buildConflictMap(array $rows): array
    {
        $map = [];
        $grouped = [];

        foreach ($rows as $row) {
            $weekday = (int) ($row['class_weekday'] ?? 0);
            $grouped[$weekday][] = $row;
            $map[(int) ($row['id'] ?? 0)] = [];
        }

        foreach ($grouped as $weekdayRows) {
            $count = count($weekdayRows);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $first = $weekdayRows[$i];
                    $second = $weekdayRows[$j];
                    if ($this->isTimeOverlap((string) $first['class_start_time'], (string) $first['class_end_time'], (string) $second['class_start_time'], (string) $second['class_end_time'])) {
                        $firstId = (int) ($first['id'] ?? 0);
                        $secondId = (int) ($second['id'] ?? 0);
                        $map[$firstId][$secondId] = $secondId;
                        $map[$secondId][$firstId] = $firstId;
                    }
                }
            }
        }

        return $map;
    }

    protected function isTimeOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        return $startA < $endB && $endA > $startB;
    }

    protected function parseClassWeekday(string $classTime): int
    {
        foreach (EduClassModel::WEEKDAY_LIST as $value => $label) {
            if ($classTime !== '' && mb_strpos($classTime, $label) !== false) {
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
        return $part === 'end' ? '00:00' : '00:00';
    }

    protected function buildCsvLine(array $fields): string
    {
        $escaped = array_map(function ($value) {
            $value = (string) $value;
            $value = str_replace('"', '""', $value);
            return '"' . $value . '"';
        }, $fields);

        return implode(',', $escaped) . "\r\n";
    }

    protected function getCurrentAdminUsername(): string
    {
        $adminId = (int) session('admin.id');
        if ($adminId <= 0) {
            return '';
        }

        return (string) (Admin::where('id', $adminId)->value('username') ?: '');
    }

    protected function canSwitchTeacher(): bool
    {
        $adminId = (int) session('admin.id');
        $groupIds = array_filter(explode(',', (string) session('admin.group')));

        return $adminId === 1 || in_array('1', $groupIds, true);
    }
}
