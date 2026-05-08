<?php
/**
 * 报名管理模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class EduEnrollment extends BaseModel
{
    protected $name = 'edu_enrollment';

    protected $autoWriteTimestamp = true;

    protected $deleteTime = 'delete_time';

    protected $append = ['enroll_type_text', 'status_text'];

    const ENROLL_TYPE_LIST = [
        1 => '续费报名',
        2 => '新报班',
        3 => '转班/升班',
    ];

    const STATUS_LIST = [
        1 => '待确认',
        2 => '已报名',
        3 => '已完成',
        4 => '已取消',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'id');
    }

    public function sourceClass()
    {
        return $this->belongsTo(EduClass::class, 'source_class_id', 'id');
    }

    public function targetClass()
    {
        return $this->belongsTo(EduClass::class, 'target_class_id', 'id');
    }

    public function getEnrollTypeTextAttr($value, $data)
    {
        $type = $data['enroll_type'] ?? null;
        return self::ENROLL_TYPE_LIST[$type] ?? '';
    }

    public function getStatusTextAttr($value, $data)
    {
        $status = $data['status'] ?? null;
        return self::STATUS_LIST[$status] ?? '';
    }

    public static function generateEnrollNo(): string
    {
        $prefix = 'EN' . date('Ymd');
        $last = self::where('enroll_no', 'like', $prefix . '%')->order('id', 'desc')->value('enroll_no');
        $num = $last ? ((int) substr((string) $last, strlen($prefix)) + 1) : 1;
        return $prefix . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
    }
}
