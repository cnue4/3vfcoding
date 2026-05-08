<?php
/**
 * 费用预警模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class FeeWarning extends BaseModel
{
    protected $name = 'fee_warning';

    protected $autoWriteTimestamp = true;

    protected $deleteTime = 'delete_time';

    protected $append = ['warning_type_text', 'warning_level_text', 'follow_status_text'];

    const WARNING_TYPE_LIST = [
        1 => '余额不足',
        2 => '剩余课时不足',
        3 => '合同即将到期',
        4 => '欠费',
        5 => '长期未到课',
    ];

    const WARNING_LEVEL_LIST = [
        1 => '提示',
        2 => '一般',
        3 => '重要',
        4 => '紧急',
    ];

    const FOLLOW_STATUS_LIST = [
        1 => '待跟进',
        2 => '跟进中',
        3 => '已处理',
        4 => '已关闭',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'id');
    }

    public function eduClass()
    {
        return $this->belongsTo(EduClass::class, 'class_id', 'id');
    }

    public function getWarningTypeTextAttr($value, $data)
    {
        return self::WARNING_TYPE_LIST[$data['warning_type'] ?? null] ?? '';
    }

    public function getWarningLevelTextAttr($value, $data)
    {
        return self::WARNING_LEVEL_LIST[$data['warning_level'] ?? null] ?? '';
    }

    public function getFollowStatusTextAttr($value, $data)
    {
        return self::FOLLOW_STATUS_LIST[$data['follow_status'] ?? null] ?? '';
    }

    public static function generateWarningNo(): string
    {
        $prefix = 'FW' . date('Ymd');
        $last = self::where('warning_no', 'like', $prefix . '%')->order('id', 'desc')->value('warning_no');
        $num = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;
        return $prefix . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
    }
}
