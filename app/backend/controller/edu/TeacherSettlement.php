<?php
/**
 * 课时结算控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\Admin;
use app\backend\model\TeacherSettlement as TeacherSettlementModel;
use app\backend\model\TeacherSettlementDetail;
use think\exception\HttpResponseException;
use think\facade\Db;
use app\common\controller\Backend;
use think\App;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="课时结算")
 * Class TeacherSettlement
 * @package app\backend\controller\edu
 */
class TeacherSettlement extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new TeacherSettlementModel();
    }

    protected function checkToken()
    {
        return true;
    }

    /**
     * @NodeAnnotation(title="列表")
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $page = max((int) $this->request->param('page', 1), 1);
            $limit = max((int) $this->request->param('limit', 10), 1);
            $keyword = trim((string) $this->request->param('keyword', ''));

            $query = $this->modelClass->with(['teacher'])->order(['id' => 'asc']);
            $this->applyTeacherTeacherScope($query, 'teacher_id');
            if ($keyword !== '') {
                $query->where('settlement_no|teacher_name|settlement_month|remark', 'like', '%' . $keyword . '%');
            }

            $count = $query->count();
            $list = $query->page($page, $limit)->select()->toArray();
            foreach ($list as &$item) {
                $item = $this->normalizeRow($item);
            }
            unset($item);

            return json(['code' => 0, 'msg' => lang('get formData success'), 'data' => $list, 'count' => $count]);
        }

        View::assign([
            'settlementStatusList' => TeacherSettlementModel::SETTLEMENT_STATUS_LIST,
        ]);
        return view();
    }

    /**
     * @NodeAnnotation(title="添加")
     */
    public function add()
    {
        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->buildSaveData($this->request->post());
                $data['settlement_no'] = TeacherSettlementModel::generateSettlementNo();
                $this->modelClass->save($data);
                $this->syncDetails((int) $this->modelClass->id, $data);
                Db::commit();
                return json(['code' => 1, 'msg' => lang('operation success')]);
            } catch (HttpResponseException $e) {
                Db::rollback();
                throw $e;
            } catch (\Throwable $e) {
                Db::rollback();
                return json(['code' => 0, 'msg' => $e->getMessage()]);
            }
        }

        View::assign($this->buildFormViewData([
            'settlement_month' => date('Y-m'),
            'settlement_status' => 1,
            'status' => 1,
        ]));
        return view();
    }

    /**
     * @NodeAnnotation(title="编辑")
     */
    public function edit()
    {
        $id = (int) $this->request->param('id');
        $model = $this->modelClass->find($id);
        if (!$model) {
            if ($this->request->isPost()) {
                return json(['code' => 0, 'msg' => '结算单不存在']);
            }
            $this->error('结算单不存在');
        }
        $this->assertTeacherOwnsTeacher((int) ($model->teacher_id ?? 0));

        if ($this->request->isPost()) {
            Db::startTrans();
            try {
                $data = $this->buildSaveData($this->request->post(), $id);
                $model->save($data);
                $this->syncDetails($id, $data);
                Db::commit();
                return json(['code' => 1, 'msg' => lang('operation success')]);
            } catch (HttpResponseException $e) {
                Db::rollback();
                throw $e;
            } catch (\Throwable $e) {
                Db::rollback();
                return json(['code' => 0, 'msg' => $e->getMessage()]);
            }
        }

        View::assign($this->buildFormViewData($this->normalizeRow($model->toArray())));
        return view('add');
    }

    /**
     * @NodeAnnotation(title="查看")
     */
    public function view()
    {
        $id = (int) $this->request->param('id');
        $model = $this->modelClass->with(['details'])->find($id);
        if (!$model) {
            $this->error('结算单不存在');
        }
        $this->assertTeacherOwnsTeacher((int) ($model->teacher_id ?? 0));

        $formData = $this->normalizeRow($model->toArray());
        $details = TeacherSettlementDetail::where('settlement_id', $id)->order('id asc')->select()->toArray();
        View::assign(['formData' => $formData, 'detailList' => $details]);
        return view();
    }

    /**
     * @NodeAnnotation(title="删除")
     */
    public function delete()
    {
        $ids = $this->request->param('ids') ?: $this->request->param('id');
        if (empty($ids)) {
            return json(['code' => 0, 'msg' => lang('Ids can not empty')]);
        }

        $list = $this->modelClass->where('id', 'in', $ids)->select();
        if ($list->isEmpty()) {
            return json(['code' => 0, 'msg' => '结算单不存在']);
        }

        Db::startTrans();
        try {
            foreach ($list as $item) {
                $this->assertTeacherOwnsTeacher((int) ($item->teacher_id ?? 0));
                TeacherSettlementDetail::where('settlement_id', $item->id)->delete();
                $item->delete();
            }
            Db::commit();
            return json(['code' => 1, 'msg' => lang('operation success')]);
        } catch (\Throwable $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }
    }

    protected function buildSaveData(array $post, int $id = 0): array
    {
        $rule = [
            'teacher_id|教师' => 'require|number',
            'settlement_month|结算月份' => 'require|regex:/^\d{4}-\d{2}$/',
            'hour_price|课时单价' => 'require|number',
            'adjust_hours|调增调减课时' => 'number',
            'deduct_amount|扣减金额' => 'number',
            'reward_amount|奖励金额' => 'number',
            'settlement_status|结算状态' => 'require|in:1,2,3,4,5',
            'remark|备注' => 'max:500',
            'status|状态' => 'require|in:1,2',
        ];
        $this->validate($post, $rule);

        $teacherId = (int) ($post['teacher_id'] ?? 0);
        $this->assertTeacherOwnsTeacher($teacherId);
        $teacher = Admin::find($teacherId);
        if (!$teacher) {
            throw new \Exception('教师不存在');
        }

        $settlementMonth = trim((string) ($post['settlement_month'] ?? ''));
        $existsQuery = $this->modelClass->where('teacher_id', $teacherId)->where('settlement_month', $settlementMonth);
        if ($id > 0) {
            $existsQuery->where('id', '<>', $id);
        }
        if ($existsQuery->find()) {
            throw new \Exception('该教师该月份结算单已存在');
        }

        $shouldHours = $this->calculateTeacherHours($teacherId, $settlementMonth);
        $hourPrice = round((float) ($post['hour_price'] ?? 0), 2);
        $adjustHours = round((float) ($post['adjust_hours'] ?? 0), 2);
        $finalHours = round($shouldHours + $adjustHours, 2);
        $shouldAmount = round($shouldHours * $hourPrice, 2);
        $deductAmount = round((float) ($post['deduct_amount'] ?? 0), 2);
        $rewardAmount = round((float) ($post['reward_amount'] ?? 0), 2);
        $finalAmount = round($shouldAmount - $deductAmount + $rewardAmount + ($adjustHours * $hourPrice), 2);
        $settlementStatus = (int) ($post['settlement_status'] ?? 1);

        return [
            'teacher_id' => $teacherId,
            'teacher_name' => (string) ($teacher->realname ?: $teacher->username),
            'settlement_month' => $settlementMonth,
            'hour_price' => $hourPrice,
            'should_hours' => $shouldHours,
            'adjust_hours' => $adjustHours,
            'final_hours' => $finalHours,
            'should_amount' => $shouldAmount,
            'deduct_amount' => $deductAmount,
            'reward_amount' => $rewardAmount,
            'final_amount' => $finalAmount,
            'settlement_status' => $settlementStatus,
            'generate_admin_id' => (int) session('admin.id'),
            'confirm_admin_id' => $settlementStatus >= 3 ? (int) session('admin.id') : 0,
            'confirm_time' => $settlementStatus >= 3 ? time() : 0,
            'pay_time' => $settlementStatus === 4 ? time() : 0,
            'remark' => trim((string) ($post['remark'] ?? '')),
            'status' => (int) ($post['status'] ?? 1),
        ];
    }

    protected function calculateTeacherHours(int $teacherId, string $settlementMonth): float
    {
        return round((float) Db::name('edu_class_hour')
            ->alias('h')
            ->join('edu_class c', 'c.id = h.class_id')
            ->where('c.teacher_id', $teacherId)
            ->where('h.status', 1)
            ->where('h.class_date', 'like', $settlementMonth . '%')
            ->sum('h.actual_hours'), 2);
    }

    protected function syncDetails(int $settlementId, array $data): void
    {
        TeacherSettlementDetail::where('settlement_id', $settlementId)->delete();

        $rows = Db::name('edu_class_hour')
            ->alias('h')
            ->join('edu_class c', 'c.id = h.class_id')
            ->where('c.teacher_id', (int) $data['teacher_id'])
            ->where('h.status', 1)
            ->where('h.class_date', 'like', $data['settlement_month'] . '%')
            ->field('h.id as class_hour_id,h.class_date,h.actual_hours,c.id as class_id,c.name as class_name')
            ->select()
            ->toArray();

        foreach ($rows as $row) {
            TeacherSettlementDetail::create([
                'settlement_id' => $settlementId,
                'teacher_id' => (int) $data['teacher_id'],
                'class_id' => (int) ($row['class_id'] ?? 0),
                'class_name' => (string) ($row['class_name'] ?? ''),
                'class_hour_id' => (int) ($row['class_hour_id'] ?? 0),
                'class_date' => $row['class_date'] ?? null,
                'actual_hours' => round((float) ($row['actual_hours'] ?? 0), 2),
                'hour_price' => round((float) ($data['hour_price'] ?? 0), 2),
                'line_amount' => round((float) ($row['actual_hours'] ?? 0) * (float) ($data['hour_price'] ?? 0), 2),
                'source_type' => 1,
                'source_id' => (int) ($row['class_hour_id'] ?? 0),
                'remark' => '',
            ]);
        }
    }

    protected function buildFormViewData(array $formData): array
    {
        $teacherQuery = Admin::where('status', 1)->order('id asc')->field('id,username,realname');
        if ($this->isTeacherRole()) {
            $teacherQuery->where('id', $this->getCurrentAdminId());
        }

        return [
            'formData' => $formData,
            'settlementStatusList' => TeacherSettlementModel::SETTLEMENT_STATUS_LIST,
            'teacherList' => $teacherQuery->select()->toArray(),
            'title' => isset($formData['id']) ? lang('Edit') : lang('Add'),
        ];
    }

    protected function normalizeRow(array $item): array
    {
        $item['settlement_status_text'] = TeacherSettlementModel::SETTLEMENT_STATUS_LIST[$item['settlement_status'] ?? null] ?? '';
        foreach (['hour_price', 'should_hours', 'adjust_hours', 'final_hours', 'should_amount', 'deduct_amount', 'reward_amount', 'final_amount'] as $field) {
            $item[$field] = round((float) ($item[$field] ?? 0), 2);
        }
        return $item;
    }
}
