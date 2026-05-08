<?php
/**
 * 数据排行控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\Admin;
use app\common\controller\Backend;
use think\App;
use think\facade\Db;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="数据排行")
 * Class TeacherRanking
 * @package app\backend\controller\edu
 */
class TeacherRanking extends Backend
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
            $keyword = trim((string) $this->request->param('keyword', ''));
            $rows = $this->buildRankingRows($keyword);

            return json([
                'code' => 0,
                'msg' => lang('get formData success'),
                'data' => $rows,
                'count' => count($rows),
                'extra' => [
                    'top3' => array_slice($rows, 0, 3),
                    'summary' => $this->buildSummary($rows),
                ],
            ]);
        }

        return view();
    }

    protected function buildRankingRows(string $keyword = ''): array
    {
        $teacherQuery = Admin::where('status', 1)->order('id', 'asc');
        if ($keyword !== '') {
            $teacherQuery->where(function ($query) use ($keyword) {
                $query->whereLike('username', "%{$keyword}%")
                    ->whereOrLike('realname', "%{$keyword}%");
            });
        }

        $teachers = $teacherQuery->field('id,username,realname')->select()->toArray();
        if (empty($teachers)) {
            return [];
        }

        $teacherIds = array_column($teachers, 'id');
        $classMap = $this->buildClassStatMap($teacherIds);
        $weekdayMap = $this->buildWeekdayStatMap($teacherIds);
        $changeMap = $this->buildChangeStatMap($teacherIds);

        $rows = [];
        foreach ($teachers as $teacher) {
            $teacherId = (int) ($teacher['id'] ?? 0);
            $classRow = $classMap[$teacherId] ?? [];
            $weekdayRow = $weekdayMap[$teacherId] ?? [];
            $changeRow = $changeMap[$teacherId] ?? [];

            $classCount = (int) ($classRow['class_count'] ?? 0);
            $studentCount = (int) ($classRow['student_count'] ?? 0);
            $weeklyCourseCount = 0;
            $weekdayCounts = [];
            for ($weekday = 1; $weekday <= 7; $weekday++) {
                $count = (int) ($weekdayRow[$weekday] ?? 0);
                $weekdayCounts[$weekday] = $count;
                $weeklyCourseCount += $count;
            }

            $refundCount = (int) ($changeRow['refund_count'] ?? 0);
            $leaveCount = (int) ($changeRow['leave_count'] ?? 0);
            $refundRate = $studentCount > 0 ? round($refundCount / $studentCount * 100, 2) : 0;
            $leaveRate = $studentCount > 0 ? round($leaveCount / $studentCount * 100, 2) : 0;

            $rows[] = [
                'teacher_id' => $teacherId,
                'teacher_name' => $teacher['realname'] ?: ($teacher['username'] ?? ''),
                'teacher_username' => $teacher['username'] ?? '',
                'class_count' => $classCount,
                'student_count' => $studentCount,
                'weekly_course_count' => $weeklyCourseCount,
                'monday_count' => $weekdayCounts[1],
                'tuesday_count' => $weekdayCounts[2],
                'wednesday_count' => $weekdayCounts[3],
                'thursday_count' => $weekdayCounts[4],
                'friday_count' => $weekdayCounts[5],
                'saturday_count' => $weekdayCounts[6],
                'sunday_count' => $weekdayCounts[7],
                'refund_count' => $refundCount,
                'leave_count' => $leaveCount,
                'refund_rate' => $refundRate,
                'leave_rate' => $leaveRate,
                'score' => $this->calculateScore($classCount, $studentCount, $weeklyCourseCount, $refundRate, $leaveRate),
            ];
        }

        usort($rows, function ($a, $b) {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            if ($a['student_count'] !== $b['student_count']) {
                return $b['student_count'] <=> $a['student_count'];
            }
            return $b['class_count'] <=> $a['class_count'];
        });

        foreach ($rows as $index => &$row) {
            $row['rank_no'] = $index + 1;
        }
        unset($row);

        return $rows;
    }

    protected function buildClassStatMap(array $teacherIds): array
    {
        $rows = Db::name('edu_class')
            ->where('status', 1)
            ->whereIn('teacher_id', $teacherIds)
            ->field('teacher_id, COUNT(id) as class_count, COALESCE(SUM(current_students), 0) as student_count')
            ->group('teacher_id')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['teacher_id']] = $row;
        }
        return $map;
    }

    protected function buildWeekdayStatMap(array $teacherIds): array
    {
        $rows = Db::name('edu_class')
            ->where('status', 1)
            ->whereIn('teacher_id', $teacherIds)
            ->field('teacher_id,class_weekday,COUNT(id) as total_count')
            ->group('teacher_id,class_weekday')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $teacherId = (int) ($row['teacher_id'] ?? 0);
            $weekday = (int) ($row['class_weekday'] ?? 0);
            if (!isset($map[$teacherId])) {
                $map[$teacherId] = [];
            }
            $map[$teacherId][$weekday] = (int) ($row['total_count'] ?? 0);
        }
        return $map;
    }

    protected function buildChangeStatMap(array $teacherIds): array
    {
        $rows = Db::name('student_change')
            ->alias('sc')
            ->join('student s', 's.id = sc.student_id')
            ->join('edu_class c', 'c.id = s.class_id')
            ->whereNull('sc.delete_time')
            ->whereIn('c.teacher_id', $teacherIds)
            ->field('c.teacher_id, SUM(CASE WHEN sc.change_type = 3 THEN 1 ELSE 0 END) as refund_count, SUM(CASE WHEN sc.change_type = 1 THEN 1 ELSE 0 END) as leave_count')
            ->group('c.teacher_id')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['teacher_id']] = [
                'refund_count' => (int) ($row['refund_count'] ?? 0),
                'leave_count' => (int) ($row['leave_count'] ?? 0),
            ];
        }
        return $map;
    }

    protected function calculateScore(int $classCount, int $studentCount, int $weeklyCourseCount, float $refundRate, float $leaveRate): float
    {
        $score = $classCount * 20 + $studentCount * 3 + $weeklyCourseCount * 8;
        $score -= $refundRate * 1.5;
        $score -= $leaveRate * 0.8;
        return round($score, 2);
    }

    protected function buildSummary(array $rows): array
    {
        $teacherCount = count($rows);
        $classCount = 0;
        $studentCount = 0;
        $weeklyCourseCount = 0;

        foreach ($rows as $row) {
            $classCount += (int) ($row['class_count'] ?? 0);
            $studentCount += (int) ($row['student_count'] ?? 0);
            $weeklyCourseCount += (int) ($row['weekly_course_count'] ?? 0);
        }

        return [
            'teacher_count' => $teacherCount,
            'class_count' => $classCount,
            'student_count' => $studentCount,
            'weekly_course_count' => $weeklyCourseCount,
        ];
    }
}
