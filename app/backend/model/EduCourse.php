<?php
/**
 * 课程管理模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class EduCourse extends BaseModel
{
    protected $name = 'edu_course';
    
    protected $autoWriteTimestamp = true;
    
    protected $deleteTime = 'delete_time';

    protected $append = ['status_text', 'category_name', 'course_type_text', 'difficulty_text'];
    
    // 状态列表
    const STATUS_LIST = [
        0 => '停用',
        1 => '正常',
    ];
    
    // 课程类型
    const TYPE_LIST = [
        1 => '常规课',
        2 => '体验课',
        3 => '集训课',
    ];
    
    // 难度等级
    const DIFFICULTY_LIST = [
        1 => '初级',
        2 => '中级',
        3 => '高级',
    ];
    
    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data)
    {
        $status = $data['status'] ?? null;
        return self::STATUS_LIST[$status] ?? '未知';
    }

    /**
     * 获取课程分类名称
     */
    public function getCategoryNameAttr($value, $data)
    {
        $categoryId = $data['category_id'] ?? 0;
        if (!$categoryId) {
            return '';
        }
        return EduCourseCategory::where('id', $categoryId)->value('name') ?: '';
    }
    
    /**
     * 获取课程类型文本
     */
    public function getCourseTypeTextAttr($value, $data)
    {
        $courseType = $data['course_type'] ?? null;
        return self::TYPE_LIST[$courseType] ?? '未知';
    }
    
    /**
     * 获取难度文本
     */
    public function getDifficultyTextAttr($value, $data)
    {
        $difficulty = $data['difficulty'] ?? null;
        return self::DIFFICULTY_LIST[$difficulty] ?? '未知';
    }

    /**
     * 获取课时单价
     */
    public function getUnitPriceAttr($value, $data)
    {
        return $value;
    }

    /**
     * 获取总课时
     */
    public function getTotalHoursAttr($value, $data)
    {
        return $value;
    }
    
    /**
     * 关联分类
     */
    public function category()
    {
        return $this->belongsTo(EduCourseCategory::class, 'category_id', 'id');
    }
    
    /**
     * 生成课程编号
     */
    public static function generateCourseNo()
    {
        $prefix = 'CO' . date('Y');
        $last = self::where('course_no', 'like', $prefix . '%')->order('id', 'desc')->value('course_no');
        if ($last) {
            $num = intval(substr($last, strlen($prefix))) + 1;
        } else {
            $num = 1;
        }
        return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
    }
}
