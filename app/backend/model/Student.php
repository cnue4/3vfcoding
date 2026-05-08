<?php
/**
 * 学生管理模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;
use think\facade\Db;

class Student extends BaseModel
{
    protected $name = 'student';

    protected $autoWriteTimestamp = true;

    protected $deleteTime = 'delete_time';

    protected $append = ['gender_text', 'student_status_text', 'status_text', 'class_name', 'course_name'];

    const GENDER_LIST = [
        0 => '女',
        1 => '男',
    ];

    const STUDENT_STATUS_LIST = [
        1 => '在读',
        2 => '休学',
        3 => '退学',
        4 => '请假',
    ];

    const STATUS_LIST = [
        1 => '正常',
        2 => '禁用',
    ];

    public function getGenderTextAttr($value, $data)
    {
        $gender = $data['gender'] ?? null;
        return self::GENDER_LIST[$gender] ?? '';
    }

    public function getStudentStatusTextAttr($value, $data)
    {
        $studentStatus = $data['student_status'] ?? null;
        return self::STUDENT_STATUS_LIST[$studentStatus] ?? '';
    }

    public function getStatusTextAttr($value, $data)
    {
        $status = $data['status'] ?? null;
        return self::STATUS_LIST[$status] ?? '';
    }

    public function getClassNameAttr($value, $data)
    {
        $classId = $data['class_id'] ?? 0;
        if (!$classId) {
            return '';
        }
        return EduClass::where('id', $classId)->value('name') ?: '';
    }

    public function getCourseNameAttr($value, $data)
    {
        $courseId = $data['course_id'] ?? 0;
        if (!$courseId) {
            return '';
        }
        return EduCourse::where('id', $courseId)->value('name') ?: '';
    }

    public function eduClass()
    {
        return $this->belongsTo(EduClass::class, 'class_id', 'id');
    }

    public function eduCourse()
    {
        return $this->belongsTo(EduCourse::class, 'course_id', 'id');
    }

    public static function generateStudentNo()
    {
        $prefix = 'S' . date('Y');
        $last = self::where('student_no', 'like', $prefix . '%')->order('id', 'desc')->value('student_no');
        if ($last) {
            $num = intval(substr($last, strlen($prefix))) + 1;
        } else {
            $num = 1;
        }
        return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
    }

    public static function buildHourTemplateByClass(int $classId, array $studentData = []): array
    {
        if ($classId <= 0) {
            return [
                'total_hours' => 0,
                'remaining_hours' => 0,
            ];
        }

        $class = EduClass::find($classId);
        $classTotalHours = (float) ($class->total_hours ?? 0);
        $classRemainingHours = max((float) ($class->remaining_hours ?? 0), 0);
        $currentClassId = (int) ($studentData['class_id'] ?? 0);
        $currentTotalHours = isset($studentData['total_hours']) ? (float) $studentData['total_hours'] : null;
        $currentRemainingHours = isset($studentData['remaining_hours']) ? (float) $studentData['remaining_hours'] : null;

        if ($currentClassId === $classId && $currentTotalHours !== null && $currentRemainingHours !== null) {
            return [
                'total_hours' => round(max($currentTotalHours, 0), 2),
                'remaining_hours' => round(max($currentRemainingHours, 0), 2),
            ];
        }

        return [
            'total_hours' => round(max($classTotalHours, 0), 2),
            'remaining_hours' => round($classRemainingHours, 2),
        ];
    }

    public static function syncLeaveStatusByStudent(int $studentId): void
    {
        if ($studentId <= 0) {
            return;
        }

        $student = self::find($studentId);
        if (!$student) {
            return;
        }

        $today = date('Y-m-d');
        $hasActiveLeave = Db::name('student_change')
            ->where('student_id', $studentId)
            ->where('change_type', 1)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->where('leave_start_time', '<=', $today)
            ->where('leave_end_time', '>=', $today)
            ->count() > 0;

        if ($hasActiveLeave && (int) $student->student_status !== 4) {
            $student->save(['student_status' => 4]);
            return;
        }

        if ((int) $student->student_status === 4 && !$hasActiveLeave) {
            $student->save(['student_status' => 1]);
        }
    }

    public static function syncLeaveStatusBatch(): void
    {
        $studentIds = Db::name('student_change')
            ->where('change_type', 1)
            ->whereNull('delete_time')
            ->distinct(true)
            ->column('student_id');

        foreach ($studentIds as $studentId) {
            self::syncLeaveStatusByStudent((int) $studentId);
        }
    }

    public static function getDeleteGiftHoursByStudent(int $studentId): float
    {
        if ($studentId <= 0) {
            return 0;
        }

        return round(max((float) Db::name('student_change')
            ->where('student_id', $studentId)
            ->where('change_type', 5)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->sum('gift_hours'), 0), 2);
    }

    public static function getEnrollmentGiftHoursByStudent(int $studentId): float
    {
        if ($studentId <= 0) {
            return 0;
        }

        return round(max((float) Db::name('edu_enrollment')
            ->where('student_id', $studentId)
            ->whereIn('status', [2, 3])
            ->whereNull('delete_time')
            ->sum('gift_hours'), 0), 2);
    }

    public static function getChangeGiftHoursByStudent(int $studentId): float
    {
        if ($studentId <= 0) {
            return 0;
        }

        return round(max((float) Db::name('student_change')
            ->where('student_id', $studentId)
            ->where('change_type', 4)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->sum('gift_hours'), 0), 2);
    }

    public static function getExtraHoursByStudent(int $studentId): float
    {
        if ($studentId <= 0) {
            return 0;
        }

        $enrollmentGiftHours = self::getEnrollmentGiftHoursByStudent($studentId);
        $changeGiftHours = self::getChangeGiftHoursByStudent($studentId);
        $deleteGiftHours = self::getDeleteGiftHoursByStudent($studentId);

        return round(max($enrollmentGiftHours + $changeGiftHours - $deleteGiftHours, 0), 2);
    }

    public static function syncHourSnapshotByStudent(int $studentId): void
    {
        if ($studentId <= 0) {
            return;
        }

        $student = self::find($studentId);
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

        $class = EduClass::find($classId);
        $classTotalHours = (float) ($class->total_hours ?? 0);
        $totalHours = round(max((float) ($student->total_hours ?? 0), 0), 2);
        if ($totalHours <= 0) {
            $totalHours = round(max($classTotalHours, 0), 2);
        }

        $details = Db::name('edu_lesson_detail')
            ->where('student_id', $studentId)
            ->where('class_id', $classId)
            ->where('status', 1)
            ->whereNull('delete_time')
            ->order('class_date asc, id asc')
            ->select()
            ->toArray();

        $usedHours = 0;
        foreach ($details as $detail) {
            $deductHours = max((float) ($detail['deduct_hours'] ?? 0), 0);
            $attendanceStatus = (int) ($detail['attendance_status'] ?? 1);
            if ($attendanceStatus !== 2) {
                $usedHours += $deductHours;
            }
            Db::name('edu_lesson_detail')
                ->where('id', (int) ($detail['id'] ?? 0))
                ->update([
                    'student_total_hours' => $totalHours,
                    'student_remaining_hours' => round(max($totalHours - $usedHours, 0), 2),
                ]);
        }

        $student->save([
            'total_hours' => $totalHours,
            'remaining_hours' => round(max($totalHours - $usedHours, 0), 2),
        ]);
    }

    public static function syncHoursByClassTemplate(int $classId): void
    {
        if ($classId <= 0) {
            return;
        }

        $students = self::where('class_id', $classId)->whereNull('delete_time')->select();
        foreach ($students as $student) {
            self::syncHourSnapshotByStudent((int) ($student->id ?? 0));
        }
    }
}
