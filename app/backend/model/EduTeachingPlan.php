<?php
/**
 * 教案资料库模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class EduTeachingPlan extends BaseModel
{
    protected $name = 'edu_teaching_plan';
    

    
    protected $autoWriteTimestamp = true;
    
    protected $deleteTime = 'delete_time';
    
    // 分类列表
    const CATEGORY_LIST = [
        1 => '课程教案',
        2 => '课程大纲',
        3 => '综合资料',
    ];
    
    // 状态列表
    const STATUS_LIST = [
        0 => '禁用',
        1 => '正常',
    ];
    
    /**
     * 获取分类文本
     */
    public function getCategoryTextAttr($value, $data)
    {
        $categoryId = $data['category_id'] ?? $data['category'] ?? null;
        return self::CATEGORY_LIST[$categoryId] ?? '未知';
    }
    
    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        return self::STATUS_LIST[$data['status']] ?? '未知';
    }
    
    /**
     * 关联课程
     */
    public function course()
    {
        return $this->belongsTo(EduCourse::class, 'course_id', 'id');
    }
    
    /**
     * 关联教师
     */
    public function teacher()
    {
        return $this->belongsTo(Admin::class, 'teacher_id', 'id');
    }
    
    /**
     * 格式化文件大小
     */
    public function getFileSizeTextAttr($value, $data)
    {
        $size = $data['file_size'];
        if ($size < 1024) {
            return $size . ' B';
        } elseif ($size < 1024 * 1024) {
            return round($size / 1024, 2) . ' KB';
        } else {
            return round($size / (1024 * 1024), 2) . ' MB';
        }
    }
}