<?php
/**
 * 学生银行模型
 */
namespace app\backend\model;

use app\common\model\BaseModel;

class StudentBank extends BaseModel
{
    protected $name = 'student_bank';

    protected $autoWriteTimestamp = true;

    protected $deleteTime = 'delete_time';

    protected $append = ['balance'];

    const STATUS_LIST = [
        1 => '正常',
        2 => '禁用',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'id');
    }

    public function getBalanceAttr($value, $data)
    {
        $income = (float) ($data['total_income'] ?? 0);
        $expense = (float) ($data['total_expense'] ?? 0);
        return $income - $expense;
    }
}
