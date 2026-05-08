<?php
/**
 * 教案分类模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class EduPlanCategory extends BaseModel
{
    protected $name = 'edu_plan_category';
    
    protected $autoWriteTimestamp = true;
    
    // 状态列表
    const STATUS_LIST = [
        0 => '禁用',
        1 => '正常',
    ];
    
    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        return self::STATUS_LIST[$data['status']] ?? '未知';
    }
}
