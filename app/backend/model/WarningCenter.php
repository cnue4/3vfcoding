<?php
/**
 * 教务预警中心模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class WarningCenter extends BaseModel
{
    protected $name = 'warning_center';

    protected $autoWriteTimestamp = true;

    protected $deleteTime = 'delete_time';

    protected $append = ['warning_category_text', 'warning_level_text', 'warning_status_text'];

    const WARNING_CATEGORY_LIST = [
        1 => '排课异常',
        2 => '考勤异常',
        3 => '费用异常',
        4 => '结算异常',
        5 => '教师异常',
        6 => '班级异常',
    ];

    const WARNING_LEVEL_LIST = [
        1 => '提示',
        2 => '一般',
        3 => '重要',
        4 => '紧急',
    ];

    const WARNING_STATUS_LIST = [
        1 => '待处理',
        2 => '处理中',
        3 => '已处理',
        4 => '已忽略',
    ];

    public function getWarningCategoryTextAttr($value, $data)
    {
        return self::WARNING_CATEGORY_LIST[$data['warning_category'] ?? null] ?? '';
    }

    public function getWarningLevelTextAttr($value, $data)
    {
        return self::WARNING_LEVEL_LIST[$data['warning_level'] ?? null] ?? '';
    }

    public function getWarningStatusTextAttr($value, $data)
    {
        return self::WARNING_STATUS_LIST[$data['warning_status'] ?? null] ?? '';
    }

    public static function generateWarningNo(): string
    {
        $prefix = 'WC' . date('Ymd');
        $last = self::where('warning_no', 'like', $prefix . '%')->order('id', 'desc')->value('warning_no');
        $num = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;
        return $prefix . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
    }
}
