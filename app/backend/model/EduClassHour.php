<?php
/**
 * 课时记录模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class EduClassHour extends BaseModel
{
    protected $name = 'edu_class_hour';

    protected $autoWriteTimestamp = true;

    protected $deleteTime = 'delete_time';

    protected $append = ['status_text', 'class_name', 'teacher_name', 'attendance_mode_text'];

    const STATUS_LIST = [
        0 => '取消',
        1 => '正常',
    ];

    const ATTENDANCE_MODE_LIST = [
        1 => '全班到齐',
        2 => '跳过请假学生',
    ];

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

    public function getTeacherNameAttr($value, $data)
    {
        $teacherId = $data['teacher_id'] ?? 0;
        if (!$teacherId) {
            return '';
        }
        return Admin::where('id', $teacherId)->value('username') ?: '';
    }

    public function getAttendanceModeTextAttr($value, $data)
    {
        $mode = $data['attendance_mode'] ?? null;
        return self::ATTENDANCE_MODE_LIST[$mode] ?? '';
    }

    public function eduClass()
    {
        return $this->belongsTo(EduClass::class, 'class_id', 'id');
    }

    public function teacher()
    {
        return $this->belongsTo(Admin::class, 'teacher_id', 'id');
    }

    public static function generateRecordNo(): string
    {
        $prefix = 'CH' . date('Ymd');
        $last = self::where('record_no', 'like', $prefix . '%')->order('id', 'desc')->value('record_no');
        if ($last) {
            $num = (int) substr($last, strlen($prefix)) + 1;
        } else {
            $num = 1;
        }
        return $prefix . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
    }
}
