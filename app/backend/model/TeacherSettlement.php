<?php
/**
 * 课时结算主表模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class TeacherSettlement extends BaseModel
{
    protected $name = 'teacher_settlement';

    protected $autoWriteTimestamp = true;

    protected $deleteTime = 'delete_time';

    protected $append = ['settlement_status_text'];

    const SETTLEMENT_STATUS_LIST = [
        1 => '待生成',
        2 => '待确认',
        3 => '已确认',
        4 => '已发放',
        5 => '已作废',
    ];

    public function teacher()
    {
        return $this->belongsTo(Admin::class, 'teacher_id', 'id');
    }

    public function details()
    {
        return $this->hasMany(TeacherSettlementDetail::class, 'settlement_id', 'id');
    }

    public function getSettlementStatusTextAttr($value, $data)
    {
        return self::SETTLEMENT_STATUS_LIST[$data['settlement_status'] ?? null] ?? '';
    }

    public static function generateSettlementNo(): string
    {
        $prefix = 'TS' . date('Ymd');
        $last = self::where('settlement_no', 'like', $prefix . '%')->order('id', 'desc')->value('settlement_no');
        $num = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;
        return $prefix . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
    }
}
