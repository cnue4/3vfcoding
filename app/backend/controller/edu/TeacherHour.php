<?php
/**
 * 教师课时统计控制器
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
 * @ControllerAnnotation(title="教师课时统计")
 * Class TeacherHour
 * @package app\backend\controller\edu
 */
class TeacherHour extends Backend
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
        $currentUsername = $this->getCurrentAdminUsername();
        $savedHourPrice = $this->getSavedHourPrice();
        $canSwitchTeacher = $this->canSwitchTeacher();

        if ($this->request->isAjax()) {
            try {
                $page = max((int) $this->request->param('page', 1), 1);
                $limit = max((int) $this->request->param('limit', 10), 1);
                $teacher = $this->resolveTeacherFromRequest();
                $year = $this->parseYearParam($this->request->param('year', '')) ?: (int) date('Y');
                $month = $this->parseMonthParam($this->request->param('month', '')) ?: (int) date('n');
                $classDate = trim((string) $this->request->param('class_date', '')) ?: date('Y-m-d');
                $hourPrice = $this->parseMoneyParam($this->request->param('hour_price', $savedHourPrice));

                if (!$teacher) {
                    return json([
                        'code' => 0,
                        'msg' => '未找到该教师账号',
                        'data' => [],
                        'count' => 0,
                    ]);
                }

                $count = 1;
                $teachers = [
                    $teacher->toArray(),
                ];

                $teacherIds = array_column($teachers, 'id');
                $classSummaryMap = [];
                $hourSummaryMap = [];
                if (!empty($teacherIds)) {
                    $classSummaryMap = $this->buildClassSummaryMap($teacherIds);
                    $hourSummaryMap = $this->buildHourSummaryMap($teacherIds, $year, $month, $classDate);
                }

                $list = [];
                foreach ($teachers as $teacher) {
                    $teacherId = (int) ($teacher['id'] ?? 0);
                    $classSummary = $classSummaryMap[$teacherId] ?? ['class_count' => 0, 'total_class_hours' => 0, 'current_students' => 0];
                    $hourSummary = $hourSummaryMap[$teacherId] ?? ['total_actual_hours' => 0, 'year_actual_hours' => 0, 'month_actual_hours' => 0, 'day_actual_hours' => 0];
                    $teacherName = $teacher['realname'] ?: ($teacher['username'] ?? '');

                    $list[] = [
                        'id' => $teacherId,
                        'teacher_id' => $teacherId,
                        'teacher_name' => $teacherName,
                        'teacher_username' => $teacher['username'] ?? '',
                        'year' => $year,
                        'month' => $month,
                        'class_date' => $classDate,
                        'hour_price' => $hourPrice,
                        'class_count' => (int) ($classSummary['class_count'] ?? 0),
                        'current_students' => (int) ($classSummary['current_students'] ?? 0),
                        'total_class_hours' => round((float) ($classSummary['total_class_hours'] ?? 0), 2),
                        'total_actual_hours' => round((float) ($hourSummary['total_actual_hours'] ?? 0), 2),
                        'year_actual_hours' => round((float) ($hourSummary['year_actual_hours'] ?? 0), 2),
                        'month_actual_hours' => round((float) ($hourSummary['month_actual_hours'] ?? 0), 2),
                        'day_actual_hours' => round((float) ($hourSummary['day_actual_hours'] ?? 0), 2),
                        'year_fee' => round((float) ($hourSummary['year_actual_hours'] ?? 0) * $hourPrice, 2),
                        'month_fee' => round((float) ($hourSummary['month_actual_hours'] ?? 0) * $hourPrice, 2),
                        'day_fee' => round((float) ($hourSummary['day_actual_hours'] ?? 0) * $hourPrice, 2),
                    ];
                }

                return json([
                    'code' => 0,
                    'msg' => lang('get formData success'),
                    'data' => $list,
                    'count' => $count,
                    'extra' => [
                        'summary' => $this->buildPageSummary($list),
                        'filters' => [
                            'year' => $year,
                            'month' => $month,
                            'class_date' => $classDate,
                            'hour_price' => $hourPrice,
                        ],
                    ],
                ]);
            } catch (\Throwable $e) {
                return json([
                    'code' => 0,
                    'msg' => '查询失败：' . $e->getMessage(),
                    'data' => [],
                    'count' => 0,
                ]);
            }
        }

        View::assign([
            'currentUsername' => $currentUsername,
            'savedHourPrice' => $savedHourPrice,
            'canSwitchTeacher' => $canSwitchTeacher,
        ]);
        return view();
    }

    /**
     * @NodeAnnotation(title="保存课时费")
     */
    public function savePrice()
    {
        $hourPrice = $this->parseMoneyParam($this->request->post('hour_price', 0));
        $this->saveHourPrice($hourPrice);
        $this->success('课时费保存成功');
    }

    protected function buildClassSummaryMap(array $teacherIds): array
    {
        $rows = Db::name('edu_class')
            ->where('status', 1)
            ->whereIn('teacher_id', $teacherIds)
            ->field('teacher_id, COUNT(id) as class_count, COALESCE(SUM(total_hours), 0) as total_class_hours, COALESCE(SUM(current_students), 0) as current_students')
            ->group('teacher_id')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) ($row['teacher_id'] ?? 0)] = $row;
        }

        return $map;
    }

    protected function buildHourSummaryMap(array $teacherIds, int $year, int $month, string $classDate): array
    {
        $baseRows = Db::name('edu_class_hour')
            ->alias('h')
            ->join('edu_class c', 'c.id = h.class_id')
            ->where('h.status', 1)
            ->whereIn('c.teacher_id', $teacherIds)
            ->field('c.teacher_id, h.class_date, h.actual_hours')
            ->select()
            ->toArray();

        $map = [];
        foreach ($teacherIds as $teacherId) {
            $map[(int) $teacherId] = [
                'total_actual_hours' => 0,
                'year_actual_hours' => 0,
                'month_actual_hours' => 0,
                'day_actual_hours' => 0,
            ];
        }

        foreach ($baseRows as $row) {
            $teacherId = (int) ($row['teacher_id'] ?? 0);
            $actualHours = (float) ($row['actual_hours'] ?? 0);
            $dateValue = (string) ($row['class_date'] ?? '');
            if (!isset($map[$teacherId])) {
                continue;
            }

            $map[$teacherId]['total_actual_hours'] += $actualHours;
            if ($year > 0 && strpos($dateValue, (string) $year . '-') === 0) {
                $map[$teacherId]['year_actual_hours'] += $actualHours;
                if ($month > 0 && (int) date('n', strtotime($dateValue)) === $month) {
                    $map[$teacherId]['month_actual_hours'] += $actualHours;
                }
            }
            if ($classDate !== '' && $dateValue === $classDate) {
                $map[$teacherId]['day_actual_hours'] += $actualHours;
            }
        }

        return $map;
    }

    protected function buildPageSummary(array $list): array
    {
        $teacherCount = count($list);
        $classCount = 0;
        $yearHours = 0;
        $monthHours = 0;
        $dayHours = 0;
        $yearFee = 0;
        $monthFee = 0;
        $dayFee = 0;

        foreach ($list as $row) {
            $classCount += (int) ($row['class_count'] ?? 0);
            $yearHours += (float) ($row['year_actual_hours'] ?? 0);
            $monthHours += (float) ($row['month_actual_hours'] ?? 0);
            $dayHours += (float) ($row['day_actual_hours'] ?? 0);
            $yearFee += (float) ($row['year_fee'] ?? 0);
            $monthFee += (float) ($row['month_fee'] ?? 0);
            $dayFee += (float) ($row['day_fee'] ?? 0);
        }

        return [
            'teacher_count' => $teacherCount,
            'class_count' => $classCount,
            'year_hours' => round($yearHours, 2),
            'month_hours' => round($monthHours, 2),
            'day_hours' => round($dayHours, 2),
            'year_fee' => round($yearFee, 2),
            'month_fee' => round($monthFee, 2),
            'day_fee' => round($dayFee, 2),
        ];
    }

    protected function parseYearParam($value): int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        return max((int) $value, 2000);
    }

    protected function parseMonthParam($value): int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        return max(min((int) $value, 12), 1);
    }

    protected function parseMoneyParam($value): float
    {
        return round(max((float) $value, 0), 2);
    }

    protected function getCurrentAdminUsername(): string
    {
        $adminId = (int) session('admin.id');
        if ($adminId <= 0) {
            return '';
        }

        return (string) (Admin::where('id', $adminId)->value('username') ?: '');
    }

    protected function getCurrentAdminId(): int
    {
        return (int) session('admin.id');
    }

    protected function getSavedHourPrice(): float
    {
        $adminId = $this->getCurrentAdminId();
        if ($adminId <= 0) {
            return 0;
        }

        return (float) (Db::name('teacher_hour_price')
            ->where('admin_id', $adminId)
            ->value('hour_price') ?? 0);
    }

    protected function saveHourPrice(float $hourPrice): void
    {
        $adminId = $this->getCurrentAdminId();
        if ($adminId <= 0) {
            $this->error('用户未登录');
        }

        $exists = Db::name('teacher_hour_price')
            ->where('admin_id', $adminId)
            ->find();

        $data = [
            'admin_id' => $adminId,
            'hour_price' => $hourPrice,
            'update_time' => time(),
        ];

        if ($exists) {
            Db::name('teacher_hour_price')
                ->where('admin_id', $adminId)
                ->update($data);
            return;
        }

        $data['create_time'] = time();
        try {
            Db::name('teacher_hour_price')->insert($data);
        } catch (\Throwable $e) {
            Db::name('teacher_hour_price')
                ->where('admin_id', $adminId)
                ->update([
                    'hour_price' => $hourPrice,
                    'update_time' => time(),
                ]);
        }
    }

    protected function resolveTeacherFromRequest()
    {
        $currentUsername = $this->getCurrentAdminUsername();
        $username = trim((string) $this->request->param('keyword', $currentUsername));
        if ($username === '') {
            $username = $currentUsername;
        }
        if (!$this->canSwitchTeacher()) {
            $username = $currentUsername;
        }

        return Admin::where('status', 1)
            ->where('username', $username)
            ->field('id,username,realname')
            ->find();
    }

    protected function canSwitchTeacher(): bool
    {
        $adminId = (int) session('admin.id');
        $groupIds = array_filter(explode(',', (string) session('admin.group')));

        return $adminId === 1 || in_array('1', $groupIds, true);
    }
}

