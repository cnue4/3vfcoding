<?php
/**
 * 学生考勤模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class StudentAttendance extends BaseModel
{
    protected $name = 'student_attendance';

    protected $autoWriteTimestamp = true;

    protected $deleteTime = 'delete_time';

    protected $append = ['attendance_status_text'];

    const ATTENDANCE_STATUS_LIST = [
        1 => '到课',
        2 => '请假',
        3 => '缺勤',
        4 => '补课',
        5 => '迟到',
        6 => '早退',
    ];

    public function eduClass()
    {
        return $this->belongsTo(EduClass::class, 'class_id', 'id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'id');
    }

    public function teacher()
    {
        return $this->belongsTo(Admin::class, 'teacher_id', 'id');
    }

    public function classHour()
    {
        return $this->belongsTo(EduClassHour::class, 'class_hour_id', 'id');
    }

    public function getAttendanceStatusTextAttr($value, $data)
    {
        return self::ATTENDANCE_STATUS_LIST[$data['attendance_status'] ?? null] ?? '';
    }

    public static function generateAttendanceNo(): string
    {
        $prefix = 'AT' . date('Ymd');
        $last = self::where('attendance_no', 'like', $prefix . '%')->order('id', 'desc')->value('attendance_no');
        $num = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;
        return $prefix . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
    }
}
