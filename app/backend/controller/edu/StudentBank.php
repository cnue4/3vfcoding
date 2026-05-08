<?php
/**
 * 学生银行控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\Student;
use app\backend\model\StudentBank as StudentBankModel;
use app\common\controller\Backend;
use think\App;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="学生银行")
 * Class StudentBank
 * @package app\backend\controller\edu
 */
class StudentBank extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new StudentBankModel();
    }

    /**
     * @NodeAnnotation(title="列表")
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $this->consolidateDuplicateAccounts();

            $page = max((int) $this->request->param('page', 1), 1);
            $limit = max((int) $this->request->param('limit', 10), 1);
            $keyword = trim((string) $this->request->param('keyword', ''));

            $query = $this->modelClass->with(['student'])->order(['id' => 'asc']);
            $this->applyTeacherStudentScope($query, 'student_id');

            if ($keyword !== '') {
                $studentIds = Student::where('name|student_no', 'like', '%' . $keyword . '%')->column('id');
                if ($this->isTeacherRole()) {
                    $classIds = $this->getTeacherOwnedClassIds();
                    $studentIds = Student::whereIn('class_id', !empty($classIds) ? $classIds : [0])
                        ->where('name|student_no', 'like', '%' . $keyword . '%')
                        ->column('id');
                }
                $matchedIds = $this->modelClass->where('remark', 'like', '%' . $keyword . '%')->column('id');
                if (!empty($studentIds)) {
                    $matchedIds = array_merge($matchedIds, $this->modelClass->where('student_id', 'in', $studentIds)->column('id'));
                }
                $matchedIds = array_values(array_unique(array_filter($matchedIds)));
                $query->where('id', 'in', !empty($matchedIds) ? $matchedIds : [0]);
            }

            $count = $query->count();
            $list = $query->page($page, $limit)->select()->toArray();
            foreach ($list as &$item) {
                $item = $this->normalizeBankRow($item);
            }
            unset($item);

            return json(['code' => 0, 'msg' => lang('get formData success'), 'data' => $list, 'count' => $count]);
        }

        return view();
    }

    /**
     * @NodeAnnotation(title="添加")
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $payload = $this->buildOperationPayload($post);
            $this->assertTeacherOwnsStudent((int) ($payload['student_id'] ?? 0));
            $account = $this->getStudentAccount((int) $payload['student_id']);

            if ($account) {
                $result = $this->applyOperation($account, $payload);
                if ($result) {
                    $this->success('积分已累计到该学生账户');
                }
            } else {
                $result = $this->modelClass->save($this->buildInitialAccountData($payload));
                if ($result) {
                    $this->success(lang('operation success'));
                }
            }
            $this->error(lang('operation failed'));
        }

        View::assign($this->buildFormViewData([
            'mode' => 'income',
            'amount' => '',
            'status' => 1,
            'total_income' => 0,
            'total_expense' => 0,
            'balance' => 0,
        ]));
        return view();
    }

    /**
     * @NodeAnnotation(title="编辑")
     */
    public function edit()
    {
        $id = (int) $this->request->param('id');
        $model = $this->modelClass->with(['student'])->find($id);
        if (!$model) {
            $this->error('学生银行记录不存在');
        }
        $this->assertTeacherOwnsStudent((int) ($model->student_id ?? 0));

        $model = $this->getStudentAccount((int) $model->student_id) ?: $model;

        if ($this->request->isPost()) {
            $post = $this->request->post();
            $payload = $this->buildOperationPayload($post, (int) $model->student_id);
            $this->assertTeacherOwnsStudent((int) ($payload['student_id'] ?? $model->student_id));
            $result = $this->applyOperation($model, $payload);
            if ($result) {
                $this->success(lang('operation success'));
            }
            $this->error(lang('operation failed'));
        }

        $formData = $this->normalizeBankRow($model->toArray());
        $formData['mode'] = 'income';
        $formData['amount'] = '';
        $formData['remark'] = '';
        $formData['lock_student'] = true;

        View::assign($this->buildFormViewData($formData));
        return view('add');
    }

    /**
     * @NodeAnnotation(title="查看")
     */
    public function view()
    {
        $id = (int) $this->request->param('id');
        $model = $this->modelClass->with(['student'])->find($id);
        if (!$model) {
            $this->error('学生银行记录不存在');
        }
        $this->assertTeacherOwnsStudent((int) ($model->student_id ?? 0));

        $model = $this->getStudentAccount((int) $model->student_id) ?: $model;
        View::assign(['formData' => $this->normalizeBankRow($model->toArray())]);
        return view();
    }

    /**
     * @NodeAnnotation(title="删除")
     */
    public function delete()
    {
        $ids = $this->request->param('ids') ?: $this->request->param('id');
        if (empty($ids)) {
            $this->error(lang('Ids can not empty'));
        }

        $list = $this->modelClass->where('id', 'in', $ids)->select();
        if ($list->isEmpty()) {
            $this->error('学生银行记录不存在');
        }

        try {
            foreach ($list as $item) {
                $this->assertTeacherOwnsStudent((int) ($item->student_id ?? 0));
                $item->delete();
            }
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }

        $this->success(lang('operation success'));
    }

    protected function buildOperationPayload(array $post, int $fixedStudentId = 0): array
    {
        if ($fixedStudentId > 0) {
            $post['student_id'] = $fixedStudentId;
        }

        $rule = [
            'student_id|学生' => 'require|number',
            'mode|类型' => 'require|in:income,expense',
            'amount|积分' => 'require|number|gt:0',
            'remark|备注' => 'max:500',
            'status|状态' => 'require|in:1,2',
        ];
        $this->validate($post, $rule);

        $studentId = (int) ($post['student_id'] ?? 0);
        $student = Student::with(['eduClass'])->find($studentId);
        if (!$student) {
            $this->error('学生不存在');
        }

        $studentData = $student->toArray();
        return [
            'student_id' => $studentId,
            'student_name' => $studentData['name'] ?? '',
            'class_name' => $studentData['eduClass']['name'] ?? ($studentData['class_name'] ?? ''),
            'mode' => (string) ($post['mode'] ?? 'income'),
            'amount' => round((float) ($post['amount'] ?? 0), 2),
            'remark' => trim((string) ($post['remark'] ?? '')),
            'status' => (int) ($post['status'] ?? 1),
        ];
    }

    protected function buildInitialAccountData(array $payload): array
    {
        return [
            'student_id' => $payload['student_id'],
            'student_name' => $payload['student_name'],
            'class_name' => $payload['class_name'],
            'total_income' => $payload['mode'] === 'income' ? $payload['amount'] : 0,
            'total_expense' => $payload['mode'] === 'expense' ? $payload['amount'] : 0,
            'last_income' => $payload['mode'] === 'income' ? $payload['amount'] : 0,
            'last_expense' => $payload['mode'] === 'expense' ? $payload['amount'] : 0,
            'remark' => $payload['remark'],
            'status' => $payload['status'],
        ];
    }

    protected function applyOperation(StudentBankModel $account, array $payload): bool
    {
        $totalIncome = round((float) $account->total_income + ($payload['mode'] === 'income' ? $payload['amount'] : 0), 2);
        $totalExpense = round((float) $account->total_expense + ($payload['mode'] === 'expense' ? $payload['amount'] : 0), 2);

        return (bool) $account->save([
            'student_name' => $payload['student_name'],
            'class_name' => $payload['class_name'],
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'last_income' => $payload['mode'] === 'income' ? $payload['amount'] : 0,
            'last_expense' => $payload['mode'] === 'expense' ? $payload['amount'] : 0,
            'remark' => $payload['remark'],
            'status' => $payload['status'],
        ]);
    }

    protected function getStudentAccount(int $studentId): ?StudentBankModel
    {
        if ($studentId <= 0) {
            return null;
        }

        $accounts = $this->modelClass->where('student_id', $studentId)->order('id asc')->select();
        if ($accounts->isEmpty()) {
            return null;
        }
        if ($accounts->count() === 1) {
            return $accounts[0];
        }

        return $this->mergeAccountCollection($accounts);
    }

    protected function consolidateDuplicateAccounts(): void
    {
        $studentIds = $this->modelClass->group('student_id')->having('COUNT(*) > 1')->column('student_id');
        foreach ($studentIds as $studentId) {
            $accounts = $this->modelClass->where('student_id', (int) $studentId)->order('id asc')->select();
            if (!$accounts->isEmpty() && $accounts->count() > 1) {
                $this->mergeAccountCollection($accounts);
            }
        }
    }

    protected function mergeAccountCollection($accounts): StudentBankModel
    {
        $primary = $accounts[0];
        $latest = $accounts[$accounts->count() - 1];
        $income = 0;
        $expense = 0;

        foreach ($accounts as $account) {
            $income += (float) $account->total_income;
            $expense += (float) $account->total_expense;
        }

        $primary->save([
            'student_name' => $latest->student_name,
            'class_name' => $latest->class_name,
            'total_income' => round($income, 2),
            'total_expense' => round($expense, 2),
            'last_income' => (float) $latest->last_income,
            'last_expense' => (float) $latest->last_expense,
            'remark' => (string) $latest->remark,
            'status' => (int) $latest->status,
        ]);

        foreach ($accounts as $index => $account) {
            if ($index === 0) {
                continue;
            }
            $account->delete();
        }

        return $primary->refresh();
    }

    protected function normalizeBankRow(array $item): array
    {
        $studentData = $item['student'] ?? [];
        $item['student_name'] = $item['student_name'] ?? ($studentData['name'] ?? '');
        $item['class_name'] = $item['class_name'] ?? ($studentData['class_name'] ?? '');
        $item['total_income'] = round((float) ($item['total_income'] ?? 0), 2);
        $item['total_expense'] = round((float) ($item['total_expense'] ?? 0), 2);
        $item['last_income'] = round((float) ($item['last_income'] ?? 0), 2);
        $item['last_expense'] = round((float) ($item['last_expense'] ?? 0), 2);
        $item['balance'] = round($item['total_income'] - $item['total_expense'], 2);
        $item['status_text'] = StudentBankModel::STATUS_LIST[$item['status'] ?? null] ?? '';
        return $item;
    }

    protected function buildFormViewData(array $formData): array
    {
        return [
            'formData' => $formData,
            'statusList' => StudentBankModel::STATUS_LIST,
            'title' => isset($formData['id']) ? lang('Edit') : lang('Add'),
        ];
    }
}
