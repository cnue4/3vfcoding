<?php
/**
 * 调课补课停课模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class ScheduleAdjust extends BaseModel
{
    protected $name = 'schedule_adjust';

    protected $autoWriteTimestamp = true;

    protected $deleteTime = 'delete_time';

    protected $append = ['adjust_type_text', 'business_status_text'];

    const ADJUST_TYPE_LIST = [
        1 => '调课',
        2 => '补课',
        3 => '停课',
        4 => '代课',
        5 => '顺延',
    ];

    const BUSINESS_STATUS_LIST = [
        1 => '待审核',
        2 => '已通过',
        3 => '已驳回',
        4 => '已执行',
        5 => '已取消',
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

    public function replaceTeacher()
    {
        return $this->belongsTo(Admin::class, 'replace_teacher_id', 'id');
    }

    public function classHour()
    {
        return $this->belongsTo(EduClassHour::class, 'origin_class_hour_id', 'id');
    }

    public function getAdjustTypeTextAttr($value, $data)
    {
        return self::ADJUST_TYPE_LIST[$data['adjust_type'] ?? null] ?? '';
    }

    public function getBusinessStatusTextAttr($value, $data)
    {
        return self::BUSINESS_STATUS_LIST[$data['business_status'] ?? null] ?? '';
    }

    public static function generateAdjustNo(): string
    {
        $prefix = 'SA' . date('Ymd');
        $last = self::where('adjust_no', 'like', $prefix . '%')->order('id', 'desc')->value('adjust_no');
        $num = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;
        return $prefix . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
    }
}
