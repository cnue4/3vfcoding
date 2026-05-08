<?php
/**
 * 班级管理模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class EduClass extends BaseModel
{
    protected $name = 'edu_class';
    
    protected $autoWriteTimestamp = true;
    
    protected $deleteTime = 'delete_time';

    protected $append = ['status_text', 'course_name', 'teacher_name'];

    const WEEKDAY_LIST = [
        1 => '星期一',
        2 => '星期二',
        3 => '星期三',
        4 => '星期四',
        5 => '星期五',
        6 => '星期六',
        7 => '星期天',
    ];

    const TIME_OPTIONS = [
        '00:00','00:30','01:00','01:30','02:00','02:30','03:00','03:30',
        '04:00','04:30','05:00','05:30','06:00','06:30','07:00','07:30',
        '08:00','08:30','09:00','09:30','10:00','10:30','11:00','11:30',
        '12:00','12:30','13:00','13:30','14:00','14:30','15:00','15:30',
        '16:00','16:30','17:00','17:30','18:00','18:30','19:00','19:30',
        '20:00','20:30','21:00','21:30','22:00','22:30','23:00','23:30','24:00'
    ];
    
    // 状态列表
    const STATUS_LIST = [
        0 => '结课',
        1 => '正常',
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
     * 获取课程名称
     */
    public function getCourseNameAttr($value, $data)
    {
        $courseId = $data['course_id'] ?? 0;
        if (!$courseId) {
            return '';
        }
        return EduCourse::where('id', $courseId)->value('name') ?: '';
    }

    /**
     * 获取教师名称
     */
    public function getTeacherNameAttr($value, $data)
    {
        $teacherId = $data['teacher_id'] ?? 0;
        if (!$teacherId) {
            return '';
        }
        return Admin::where('id', $teacherId)->value('username') ?: '';
    }
    
    /**
     * 关联课程
     */
    public function eduCourse()
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
     * 生成班级编号
     */
    public static function generateClassNo()
    {
        $prefix = 'C' . date('Y');
        $last = self::where('class_no', 'like', $prefix . '%')->order('id', 'desc')->value('class_no');
        if ($last) {
            $num = intval(substr($last, strlen($prefix))) + 1;
        } else {
            $num = 1;
        }
        return $prefix . str_pad($num, 3, '0', STR_PAD_LEFT);
    }
}
