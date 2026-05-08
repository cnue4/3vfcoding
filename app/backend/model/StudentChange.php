<?php
/**
 * 学生异动模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class StudentChange extends BaseModel
{
    protected $name = 'student_change';

    protected $autoWriteTimestamp = true;

    protected $deleteTime = 'delete_time';

    protected $append = ['change_type_text'];

    const CHANGE_TYPE_LIST = [
        1 => '请假',
        2 => '调课',
        3 => '退费',
        4 => '赠课',
        5 => '删除赠课',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'id');
    }

    public function oldCourse()
    {
        return $this->belongsTo(EduCourse::class, 'old_course_id', 'id');
    }

    public function newCourse()
    {
        return $this->belongsTo(EduCourse::class, 'new_course_id', 'id');
    }

    public function getChangeTypeTextAttr($value, $data)
    {
        $type = $data['change_type'] ?? null;
        return self::CHANGE_TYPE_LIST[$type] ?? '';
    }
}
