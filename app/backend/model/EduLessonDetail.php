<?php
/**
 * 消课明细模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class EduLessonDetail extends BaseModel
{
    protected $name = 'edu_lesson_detail';

    protected $autoWriteTimestamp = true;

    protected $deleteTime = 'delete_time';

    protected $append = ['attendance_text', 'status_text'];

    const ATTENDANCE_LIST = [
        1 => '已到课',
        2 => '请假跳过',
    ];

    const STATUS_LIST = [
        1 => '正常',
        0 => '取消',
    ];

    public function classHour()
    {
        return $this->belongsTo(EduClassHour::class, 'class_hour_id', 'id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'id');
    }

    public function eduClass()
    {
        return $this->belongsTo(EduClass::class, 'class_id', 'id');
    }

    public function teacher()
    {
        return $this->belongsTo(Admin::class, 'teacher_id', 'id');
    }

    public function getAttendanceTextAttr($value, $data)
    {
        $attendance = $data['attendance_status'] ?? null;
        return self::ATTENDANCE_LIST[$attendance] ?? '';
    }

    public function getStatusTextAttr($value, $data)
    {
        $status = $data['status'] ?? null;
        return self::STATUS_LIST[$status] ?? '';
    }
}
