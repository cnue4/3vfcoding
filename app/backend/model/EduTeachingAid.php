<?php
/**
 * 教具申请模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class EduTeachingAid extends BaseModel
{
    protected $name = 'edu_teaching_aid';
    
    protected $autoWriteTimestamp = true;
    
    // 状态列表
    const STATUS_LIST = [
        0 => '待审批',
        1 => '已通过',
        2 => '已拒绝',
        3 => '已采购',
        4 => '已领取',
    ];
    
    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        return self::STATUS_LIST[$data['status']] ?? '未知';
    }
    
    /**
     * 关联教师
     */
    public function teacher()
    {
        return $this->belongsTo(Admin::class, 'teacher_id', 'id');
    }
    
    /**
     * 关联审批人
     */
    public function approver()
    {
        return $this->belongsTo(Admin::class, 'approver_id', 'id');
    }
    
    /**
     * 生成申请编号
     */
    public static function generateApplyNo()
    {
        $prefix = 'TA' . date('Ymd');
        $last = self::where('apply_no', 'like', $prefix . '%')->order('id', 'desc')->value('apply_no');
        if ($last) {
            $num = intval(substr($last, strlen($prefix))) + 1;
        } else {
            $num = 1;
        }
        return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
    }
}
