<?php
/**
 * 教务预警中心控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\Admin;
use app\backend\model\EduClass;
use app\backend\model\Student;
use app\backend\model\WarningCenter as WarningCenterModel;
use app\common\controller\Backend;
use think\App;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="教务预警中心")
 * Class WarningCenter
 * @package app\backend\controller\edu
 */
class WarningCenter extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new WarningCenterModel();
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

            $query = $this->modelClass->order(['id' => 'desc']);
            if ($keyword !== '') {
                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->whereLike('warning_no', "%{$keyword}%")
                        ->whereOrLike('warning_title', "%{$keyword}%")
                        ->whereOrLike('warning_content', "%{$keyword}%")
                        ->whereOrLike('warning_source_type', "%{$keyword}%")
                        ->whereOrLike('handle_remark', "%{$keyword}%");
                });
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
            'warningCategoryList' => WarningCenterModel::WARNING_CATEGORY_LIST,
            'warningLevelList' => WarningCenterModel::WARNING_LEVEL_LIST,
            'warningStatusList' => WarningCenterModel::WARNING_STATUS_LIST,
        ]);
        return view();
    }

    /**
     * @NodeAnnotation(title="添加")
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->buildSaveData($this->request->post());
            $data['warning_no'] = WarningCenterModel::generateWarningNo();
            if ($this->modelClass->save($data)) {
                $this->success(lang('operation success'));
            }
            $this->error(lang('operation failed'));
        }

        View::assign($this->buildFormViewData([
            'warning_category' => 1,
            'warning_level' => 2,
            'warning_status' => 1,
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
            $this->error('教务预警不存在');
        }

        if ($this->request->isPost()) {
            $data = $this->buildSaveData($this->request->post());
            if ($model->save($data)) {
                $this->success(lang('operation success'));
            }
            $this->error(lang('operation failed'));
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
        $model = $this->modelClass->find($id);
        if (!$model) {
            $this->error('教务预警不存在');
        }

        View::assign(['formData' => $this->normalizeRow($model->toArray())]);
        return view();
    }

    /**
     * @NodeAnnotation(title="删除")
     */
    public function delete()
    {
        $id = (int) $this->request->param('id');
        if (!$id) {
            $this->error(lang('id error'));
        }

        $model = $this->modelClass->find($id);
        if (!$model) {
            $this->error('教务预警不存在');
        }

        if ($model->delete()) {
            $this->success(lang('operation success'));
        }
        $this->error(lang('operation failed'));
    }

    protected function buildSaveData(array $post): array
    {
        $rule = [
            'warning_title|预警标题' => 'require|max:200',
            'warning_category|预警分类' => 'require|in:1,2,3,4,5,6',
            'warning_level|预警等级' => 'require|in:1,2,3,4',
            'warning_source_type|来源类型' => 'require|max:50',
            'source_id|来源业务ID' => 'number',
            'class_id|班级' => 'number',
            'student_id|学生' => 'number',
            'teacher_id|教师' => 'number',
            'warning_content|预警内容' => 'require|max:1000',
            'warning_status|处理状态' => 'require|in:1,2,3,4',
            'responsible_admin_id|责任人' => 'number',
            'handle_remark|处理备注' => 'max:500',
            'status|状态' => 'require|in:1,2',
        ];
        $this->validate($post, $rule);

        $classId = (int) ($post['class_id'] ?? 0);
        if ($classId > 0 && !EduClass::find($classId)) {
            $this->error('班级不存在');
        }

        $studentId = (int) ($post['student_id'] ?? 0);
        if ($studentId > 0 && !Student::find($studentId)) {
            $this->error('学生不存在');
        }

        $teacherId = (int) ($post['teacher_id'] ?? 0);
        if ($teacherId > 0 && !Admin::find($teacherId)) {
            $this->error('教师不存在');
        }

        $responsibleAdminId = (int) ($post['responsible_admin_id'] ?? 0);
        if ($responsibleAdminId > 0 && !Admin::find($responsibleAdminId)) {
            $this->error('责任人不存在');
        }

        $warningStatus = (int) ($post['warning_status'] ?? 1);
        return [
            'warning_title' => trim((string) ($post['warning_title'] ?? '')),
            'warning_category' => (int) ($post['warning_category'] ?? 1),
            'warning_level' => (int) ($post['warning_level'] ?? 2),
            'warning_source_type' => trim((string) ($post['warning_source_type'] ?? '')),
            'source_id' => (int) ($post['source_id'] ?? 0),
            'class_id' => $classId,
            'student_id' => $studentId,
            'teacher_id' => $teacherId,
            'warning_content' => trim((string) ($post['warning_content'] ?? '')),
            'warning_status' => $warningStatus,
            'responsible_admin_id' => $responsibleAdminId,
            'handle_remark' => trim((string) ($post['handle_remark'] ?? '')),
            'handle_time' => $warningStatus >= 3 ? time() : 0,
            'status' => (int) ($post['status'] ?? 1),
        ];
    }

    protected function buildFormViewData(array $formData): array
    {
        return [
            'formData' => $formData,
            'warningCategoryList' => WarningCenterModel::WARNING_CATEGORY_LIST,
            'warningLevelList' => WarningCenterModel::WARNING_LEVEL_LIST,
            'warningStatusList' => WarningCenterModel::WARNING_STATUS_LIST,
            'classList' => EduClass::where('status', 1)->order('id asc')->column('name', 'id'),
            'studentList' => Student::where('status', 1)->order('id asc')->column('name', 'id'),
            'adminList' => Admin::where('status', 1)->order('id asc')->column('username', 'id'),
            'title' => isset($formData['id']) ? lang('Edit') : lang('Add'),
        ];
    }

    protected function normalizeRow(array $item): array
    {
        $item['warning_category_text'] = WarningCenterModel::WARNING_CATEGORY_LIST[$item['warning_category'] ?? null] ?? '';
        $item['warning_level_text'] = WarningCenterModel::WARNING_LEVEL_LIST[$item['warning_level'] ?? null] ?? '';
        $item['warning_status_text'] = WarningCenterModel::WARNING_STATUS_LIST[$item['warning_status'] ?? null] ?? '';
        return $item;
    }
}
