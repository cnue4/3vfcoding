<?php
/**
 * 课时结算明细模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class TeacherSettlementDetail extends BaseModel
{
    protected $name = 'teacher_settlement_detail';

    protected $autoWriteTimestamp = true;

    protected $deleteTime = 'delete_time';

    const SOURCE_TYPE_LIST = [
        1 => '正常上课',
        2 => '补课',
        3 => '代课',
        4 => '调课补差',
        5 => '人工调整',
    ];
}
