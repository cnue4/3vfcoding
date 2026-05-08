<?php
/**
 * 试听体验课模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class EduTrialClass extends BaseModel
{
    protected $name = 'edu_trial_class';

    protected $autoWriteTimestamp = true;

    protected $deleteTime = 'delete_time';

    protected $append = ['trial_result_text', 'convert_status_text', 'status_text'];

    const TRIAL_RESULT_LIST = [
        1 => '待试听',
        2 => '已试听',
        3 => '未到场',
        4 => '已取消',
    ];

    const CONVERT_STATUS_LIST = [
        1 => '待跟进',
        2 => '已转化',
        3 => '未转化',
    ];

    const STATUS_LIST = [
        1 => '正常',
        2 => '关闭',
    ];

    public function intendedCourse()
    {
        return $this->belongsTo(EduCourse::class, 'intended_course_id', 'id');
    }

    public function trialClass()
    {
        return $this->belongsTo(EduClass::class, 'trial_class_id', 'id');
    }

    public function teacher()
    {
        return $this->belongsTo(Admin::class, 'teacher_id', 'id');
    }

    public function getTrialResultTextAttr($value, $data)
    {
        $result = $data['trial_result'] ?? null;
        return self::TRIAL_RESULT_LIST[$result] ?? '';
    }

    public function getConvertStatusTextAttr($value, $data)
    {
        $status = $data['convert_status'] ?? null;
        return self::CONVERT_STATUS_LIST[$status] ?? '';
    }

    public function getStatusTextAttr($value, $data)
    {
        $status = $data['status'] ?? null;
        return self::STATUS_LIST[$status] ?? '';
    }

    public static function generateTrialNo(): string
    {
        $prefix = 'TR' . date('Ymd');
        $last = self::where('trial_no', 'like', $prefix . '%')->order('id', 'desc')->value('trial_no');
        $num = $last ? ((int) substr((string) $last, strlen($prefix)) + 1) : 1;
        return $prefix . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
    }
}
