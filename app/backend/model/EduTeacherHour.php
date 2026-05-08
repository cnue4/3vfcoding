<?php
/**
 * 教师课时统计模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class EduTeacherHour extends BaseModel
{
    protected $name = 'edu_teacher_hour';
    
    protected $autoWriteTimestamp = true;
    
    // 状态列表
    const STATUS_LIST = [
        0 => '未结算',
        1 => '已结算',
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
}
