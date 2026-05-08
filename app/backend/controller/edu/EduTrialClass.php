<?php
/**
 * 试听体验课控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\Admin;
use app\backend\model\EduClass;
use app\backend\model\EduCourse;
use app\backend\model\EduTrialClass as EduTrialClassModel;
use app\common\controller\Backend;
use think\App;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="试听体验课")
 */
class EduTrialClass extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new EduTrialClassModel();
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
            list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();
            $sort = ['id' => 'asc'];
            $keyword = trim((string) $this->request->param('keyword', ''));

            $countQuery = $this->modelClass
                ->with(['intendedCourse', 'trialClass', 'teacher'])
                ->where($where);
            $listQuery = $this->modelClass
                ->with(['intendedCourse', 'trialClass', 'teacher'])
                ->where($where);
            $this->applyTeacherTeacherScope($countQuery, 'teacher_id');
            $this->applyTeacherTeacherScope($listQuery, 'teacher_id');

            if ($keyword !== '') {
                $countQuery->where('student_name|trial_no|contact_name|phone', 'like', '%' . $keyword . '%');
                $listQuery->where('student_name|trial_no|contact_name|phone', 'like', '%' . $keyword . '%');
            }

            $count = $countQuery->count();
            $list = $listQuery
                ->order($sort)
                ->page($this->page, $this->pageSize)
                ->select()
                ->toArray();

            foreach ($list as &$item) {
                $item = $this->normalizeTrialRow($item);
            }
            unset($item);

            return json(['code' => 0, 'msg' => lang('get formData success'), 'data' => $list, 'count' => $count]);
        }
        View::assign([
            'trialResultList' => EduTrialClassModel::TRIAL_RESULT_LIST,
            'convertStatusList' => EduTrialClassModel::CONVERT_STATUS_LIST,
        ]);
        return view();
    }

    /**
     * @NodeAnnotation(title="添加")
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $data = $this->buildTrialData($this->request->post());
            if ($this->isTeacherRole()) {
                $data['teacher_id'] = $this->getCurrentAdminId();
                $teacher = Admin::find($data['teacher_id']);
                $data['teacher_name'] = (string) (($teacher->realname ?: $teacher->username) ?? '');
            }
            $data['trial_no'] = EduTrialClassModel::generateTrialNo();
            $result = $this->modelClass->save($data);
            if ($result) {
                $this->success(lang('operation success'));
            }
            $this->error(lang('operation failed'));
        }
        View::assign($this->buildFormViewData([
            'trial_date' => date('Y-m-d'),
            'trial_result' => 1,
            'convert_status' => 1,
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
            $this->error('试听记录不存在');
        }
        $this->assertTeacherOwnsTeacher((int) ($model->teacher_id ?? 0));
        if ($this->request->isPost()) {
            $result = $model->save($this->buildTrialData($this->request->post()));
            if ($result) {
                $this->success(lang('operation success'));
            }
            $this->error(lang('operation failed'));
        }
        View::assign($this->buildFormViewData($this->normalizeTrialRow($model->toArray())));
        return view('add');
    }

    /**
     * @NodeAnnotation(title="查看")
     */
    public function view()
    {
        $id = (int) $this->request->param('id');
        $model = $this->modelClass->with(['intendedCourse', 'trialClass', 'teacher'])->find($id);
        if (!$model) {
            $this->error('试听记录不存在');
        }
        $this->assertTeacherOwnsTeacher((int) ($model->teacher_id ?? 0));
        View::assign(['formData' => $this->normalizeTrialRow($model->toArray())]);
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
        try {
            foreach ($list as $item) {
                $this->assertTeacherOwnsTeacher((int) ($item->teacher_id ?? 0));
                $item->delete();
            }
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }
        $this->success(lang('operation success'));
    }

    protected function buildTrialData(array $post): array
    {
        $rule = [
            'student_name|试听学员' => 'require|max:100',
            'contact_name|联系人' => 'require|max:100',
            'phone|联系电话' => 'require|max:30',
            'intended_course_id|意向课程' => 'require|number',
            'trial_class_id|试听班级' => 'require|number',
            'teacher_id|试听老师' => 'require|number',
            'trial_date|试听日期' => 'require|date',
            'trial_time|试听时段' => 'require|regex:/^\d{2}:\d{2}$/',
            'trial_result|试听结果' => 'require|in:1,2,3,4',
            'convert_status|转化状态' => 'require|in:1,2,3',
            'follow_record|跟进记录' => 'max:1000',
            'remark|备注' => 'max:500',
            'status|状态' => 'require|in:1,2',
        ];
        $this->validate($post, $rule);
        $course = EduCourse::find((int) ($post['intended_course_id'] ?? 0));
        $class = EduClass::find((int) ($post['trial_class_id'] ?? 0));
        $teacher = Admin::find((int) ($post['teacher_id'] ?? 0));
        if (!$course || !$class || !$teacher) {
            $this->error('试听课程、班级或老师不存在');
        }
        return [
            'student_name' => trim((string) ($post['student_name'] ?? '')),
            'contact_name' => trim((string) ($post['contact_name'] ?? '')),
            'phone' => trim((string) ($post['phone'] ?? '')),
            'age' => trim((string) ($post['age'] ?? '')),
            'intended_course_id' => (int) ($post['intended_course_id'] ?? 0),
            'intended_course_name' => (string) ($course->name ?? ''),
            'trial_class_id' => (int) ($post['trial_class_id'] ?? 0),
            'trial_class_name' => (string) ($class->name ?? ''),
            'teacher_id' => (int) ($post['teacher_id'] ?? 0),
            'teacher_name' => (string) (($teacher->realname ?: $teacher->username) ?? ''),
            'trial_date' => trim((string) ($post['trial_date'] ?? '')),
            'trial_time' => trim((string) ($post['trial_time'] ?? '')),
            'trial_result' => (int) ($post['trial_result'] ?? 1),
            'convert_status' => (int) ($post['convert_status'] ?? 1),
            'follow_record' => trim((string) ($post['follow_record'] ?? '')),
            'remark' => trim((string) ($post['remark'] ?? '')),
            'status' => (int) ($post['status'] ?? 1),
        ];
    }

    protected function normalizeTrialRow(array $item): array
    {
        $item['trial_result_text'] = EduTrialClassModel::TRIAL_RESULT_LIST[$item['trial_result'] ?? null] ?? '';
        $item['convert_status_text'] = EduTrialClassModel::CONVERT_STATUS_LIST[$item['convert_status'] ?? null] ?? '';
        $item['status_text'] = EduTrialClassModel::STATUS_LIST[$item['status'] ?? null] ?? '';
        return $item;
    }

    protected function buildFormViewData(array $formData): array
    {
        $classQuery = EduClass::where('status', 1)->order('id asc');
        if ($this->isTeacherRole()) {
            $classQuery->where('teacher_id', $this->getCurrentAdminId());
        }
        $teacherQuery = Admin::where('status', 1)->order('id asc')->field('id,username,realname');
        if ($this->isTeacherRole()) {
            $teacherQuery->where('id', $this->getCurrentAdminId());
        }

        return [
            'formData' => $formData,
            'courseList' => EduCourse::where('status', 1)->order('id asc')->field('id,name')->select()->toArray(),
            'classList' => $classQuery->field('id,name')->select()->toArray(),
            'teacherList' => $teacherQuery->select()->toArray(),
            'trialResultList' => [
                ['value' => 1, 'name' => '待试听'],
                ['value' => 2, 'name' => '已试听'],
                ['value' => 3, 'name' => '未到场'],
                ['value' => 4, 'name' => '已取消'],
            ],
            'convertStatusList' => [
                ['value' => 1, 'name' => '待跟进'],
                ['value' => 2, 'name' => '已转化'],
                ['value' => 3, 'name' => '未转化'],
            ],
            'statusList' => EduTrialClassModel::STATUS_LIST,
            'title' => isset($formData['id']) ? lang('Edit') : lang('Add'),
        ];
    }
}
