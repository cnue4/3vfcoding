<?php
/**
 * 校区联系薄模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class CampusContact extends BaseModel
{
    protected $name = 'campus_contact';

    protected $autoWriteTimestamp = true;

    protected $deleteTime = 'delete_time';

    protected $append = ['gender_text', 'position_text'];

    const GENDER_LIST = [
        0 => '女',
        1 => '男',
    ];

    const POSITION_LIST = [
        1 => '校区领导',
        2 => '本部老师',
        3 => '兼职老师',
    ];

    public function getGenderTextAttr($value, $data)
    {
        $gender = $data['gender'] ?? null;
        return self::GENDER_LIST[$gender] ?? '';
    }

    public function getPositionTextAttr($value, $data)
    {
        $position = $data['position'] ?? null;
        return self::POSITION_LIST[$position] ?? '';
    }
}
