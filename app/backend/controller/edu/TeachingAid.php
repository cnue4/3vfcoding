<?php
/**
 * 教具申请管理控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\EduTeachingAid as EduTeachingAidModel;
use app\backend\model\Admin;
use app\common\controller\Backend;
use think\App;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="教具申请")
 * Class TeachingAid
 * @package app\backend\controller\edu
 */
class TeachingAid extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new EduTeachingAidModel();
    }

    /**
     * @NodeAnnotation(title="列表")
     * @return mixed|\think\response\Json|\think\response\View
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            if ($this->request->param('selectFields')) {
                $this->selectList();
            }

            list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();
            $sort = ['id' => 'asc'];
            $keyword = trim((string) $this->request->param('keyword', ''));
            
            $countQuery = $this->modelClass
                ->with(['teacher', 'approver'])
                ->where($where);
            $listQuery = $this->modelClass
                ->with(['teacher', 'approver'])
                ->where($where);
            $this->applyTeacherTeacherScope($countQuery, 'teacher_id');
            $this->applyTeacherTeacherScope($listQuery, 'teacher_id');

            if ($keyword !== '') {
                $countQuery->where('apply_no|item_name|purpose|need_time|approve_remark', 'like', '%' . $keyword . '%');
                $listQuery->where('apply_no|item_name|purpose|need_time|approve_remark', 'like', '%' . $keyword . '%');
            }
            
            $count = $countQuery->count();
                
            $list = $listQuery
                ->order($sort)
                ->page($this->page, $this->pageSize)
                ->select()
                ->toArray();

            foreach ($list as &$item) {
                $item['apply_time'] = !empty($item['apply_time']) ? date('Y-m-d', is_numeric($item['apply_time']) ? (int) $item['apply_time'] : strtotime((string) $item['apply_time'])) : '';
            }
            unset($item);

            $result = ['code' => 0, 'msg' => lang('get formData success'), 'data' => $list, 'count' => $count];
            return json($result);
        }

        return view();
    }

    /**
     * @NodeAnnotation(title="申请")
     * @return \think\response\View
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            
            $rule = [
                'item_name|教具名称' => 'require|max:200',
                'quantity|数量' => 'require|number',
                'purpose|用途说明' => 'require',
                'need_time|需求日期' => 'require',
            ];
            $this->validate($post, $rule);
            
            // 自动生成申请编号
            $post['apply_no'] = EduTeachingAidModel::generateApplyNo();
            $post['teacher_id'] = session('admin.id');
            $post['apply_time'] = time();
            $post['status'] = 0; // 待审批
            
            $result = $this->modelClass->save($post);
            
            if ($result) {
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('operation failed'));
            }
        }
        
        $view = [
            'formData' => [],
            'statusList' => EduTeachingAidModel::STATUS_LIST,
            'title' => lang('Add'),
        ];
        View::assign($view);
        return view();
    }

    /**
     * @NodeAnnotation(title="编辑")
     * @return \think\response\View
     */
    public function edit()
    {
        $id = $this->request->param('id');
        $model = $this->modelClass->find($id);
        if (!$model) {
            $this->error('申请不存在');
        }
        $this->assertTeacherOwnsTeacher((int) ($model->teacher_id ?? 0));
        
        // 只有待审批状态可以编辑
        if ($model->status != 0) {
            $this->error('该申请已审批，无法编辑');
        }
        
        if ($this->request->isPost()) {
            $post = $this->request->post();
            
            $rule = [
                'item_name|教具名称' => 'require|max:200',
                'quantity|数量' => 'require|number',
                'purpose|用途说明' => 'require',
                'need_time|需求日期' => 'require',
            ];
            $this->validate($post, $rule);
            
            $result = $model->save($post);
            
            if ($result) {
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('operation failed'));
            }
        }
        
        $formData = $model->toArray();
        
        $view = [
            'formData' => $formData,
            'statusList' => EduTeachingAidModel::STATUS_LIST,
            'title' => lang('Edit'),
        ];
        View::assign($view);
        return view('add');
    }

    /**
     * @NodeAnnotation(title="查看")
     * @return \think\response\View
     */
    public function view()
    {
        $id = $this->request->param('id');
        $model = $this->modelClass->with(['teacher', 'approver'])->find($id);
        if (!$model) {
            $this->error('申请不存在');
        }
        $this->assertTeacherOwnsTeacher((int) ($model->teacher_id ?? 0));

        $formData = $model->toArray();
        $formData['apply_time_text'] = !empty($formData['apply_time']) ? date('Y-m-d', is_numeric($formData['apply_time']) ? (int) $formData['apply_time'] : strtotime((string) $formData['apply_time'])) : '';
        $formData['approve_time_text'] = !empty($formData['approve_time']) ? date('Y-m-d', is_numeric($formData['approve_time']) ? (int) $formData['approve_time'] : strtotime((string) $formData['approve_time'])) : '';

        View::assign([
            'formData' => $formData,
            'teacher' => $model->teacher,
            'approver' => $model->approver,
            'statusList' => EduTeachingAidModel::STATUS_LIST,
        ]);
        return view();
    }

    /**
     * @NodeAnnotation(title="审批")
     * @return \think\response\View
     */
    public function approve()
    {
        $id = $this->request->param('id');
        $model = $this->modelClass->find($id);
        if (!$model) {
            $this->error('申请不存在');
        }
        $this->assertTeacherOwnsTeacher((int) ($model->teacher_id ?? 0));
        
        if ($model->status != 0) {
            $this->error('该申请已审批');
        }
        
        if ($this->request->isPost()) {
            $post = $this->request->post();
            
            $status = $post['status'] ?? 1;
            if (!in_array($status, [1, 2])) {
                $this->error('审批状态错误');
            }
            
            $result = $model->save([
                'status' => $status,
                'approver_id' => session('admin.id'),
                'approve_time' => time(),
                'approve_remark' => $post['approve_remark'] ?? '',
            ]);
            
            if ($result) {
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('operation failed'));
            }
        }
        
        $formData = $model->toArray();
        
        $view = [
            'formData' => $formData,
            'teacher' => $model->teacher,
            'statusList' => EduTeachingAidModel::STATUS_LIST,
        ];
        View::assign($view);
        return view();
    }

    /**
     * @NodeAnnotation(title="更新状态")
     */
    public function updateStatus()
    {
        $id = $this->request->param('id');
        $status = $this->request->param('status');
        
        $model = $this->modelClass->find($id);
        
        if (!$model) {
            $this->error('申请不存在');
        }
        $this->assertTeacherOwnsTeacher((int) ($model->teacher_id ?? 0));
        
        // 状态流转检查
        $validStatus = [0, 1, 2, 3, 4];
        if (!in_array($status, $validStatus)) {
            $this->error('状态错误');
        }
        
        $result = $model->save(['status' => $status]);
        
        if ($result) {
            $this->success('状态更新成功');
        } else {
            $this->error('状态更新失败');
        }
    }

    /**
     * @NodeAnnotation(title="删除")
     */
    public function delete()
    {
        $ids = $this->request->param('ids') ? $this->request->param('ids') : $this->request->param('id');
        if (!empty($ids)) {
            $list = $this->modelClass->where('id', 'in', $ids)->select();
            if ($list->isEmpty()) {
                $this->error('申请不存在');
            }
            try {
                foreach ($list as $k => $v) {
                    $this->assertTeacherOwnsTeacher((int) ($v->teacher_id ?? 0));
                    $v->delete();
                }
            } catch (\Exception $e) {
                $this->error(lang($e->getMessage()));
            }
            $this->success(lang('operation success'));
        } else {
            $this->error(lang('Ids can not empty'));
        }
    }
}
