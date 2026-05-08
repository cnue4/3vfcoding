<?php
/**
 * 学生银行排行榜控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\StudentBank;
use app\common\controller\Backend;
use think\App;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="学生银行排行榜")
 */
class StudentBankRanking extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    /**
     * @NodeAnnotation(title="列表")
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $rows = StudentBank::with(['student'])->select()->toArray();
            foreach ($rows as &$row) {
                $row['student_name'] = $row['student_name'] ?? ($row['student']['name'] ?? '');
                $row['class_name'] = $row['class_name'] ?? ($row['student']['class_name'] ?? '');
                $row['balance'] = round((float) ($row['total_income'] ?? 0) - (float) ($row['total_expense'] ?? 0), 2);
            }
            unset($row);
            usort($rows, function ($a, $b) {
                return $b['balance'] <=> $a['balance'];
            });
            foreach ($rows as $index => &$row) {
                $row['rank_no'] = $index + 1;
            }
            unset($row);
            return json(['code' => 0, 'msg' => lang('get formData success'), 'data' => $rows, 'count' => count($rows)]);
        }
        return view();
    }
}
