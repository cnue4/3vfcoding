<?php
/**
 * 消课明细控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\EduLessonDetail as EduLessonDetailModel;
use app\backend\model\Student;
use app\common\controller\Backend;
use think\App;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="消课明细")
 */
class EduLessonDetail extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new EduLessonDetailModel();
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
            $query = $this->modelClass->with(['student', 'eduClass', 'teacher'])->order('id desc');
            $this->applyTeacherTeacherScope($query, 'teacher_id');
            if ($keyword !== '') {
                $studentIds = Student::where('name|student_no|contact_name|phone', 'like', '%' . $keyword . '%')->column('id');
                $query->where(function ($subQuery) use ($keyword, $studentIds) {
                    $subQuery->whereLike('class_date', "%{$keyword}%");
                    if (!empty($studentIds)) {
                        $subQuery->whereOr('student_id', 'in', $studentIds);
                    }
                });
            }
            $count = $query->count();
            $list = $query->page($page, $limit)->select()->toArray();
            foreach ($list as &$item) {
                $item['student_name'] = $item['student']['name'] ?? '';
                $item['class_name'] = $item['edu_class']['name'] ?? ($item['student']['class_name'] ?? '');
                $item['teacher_name'] = $item['teacher']['realname'] ?? ($item['teacher']['username'] ?? '');
                $item['attendance_text'] = ((int) ($item['attendance_status'] ?? 1) === 2) ? '已请假' : (EduLessonDetailModel::ATTENDANCE_LIST[$item['attendance_status'] ?? null] ?? '');
            }
            unset($item);
            return json(['code' => 0, 'msg' => lang('get formData success'), 'data' => $list, 'count' => $count]);
        }
        return view();
    }

    /**
     * @NodeAnnotation(title="查看")
     */
    public function view()
    {
        $id = (int) $this->request->param('id');
        $model = $this->modelClass->with(['student', 'eduClass', 'teacher'])->find($id);
        if (!$model) {
            $this->error('消课明细不存在');
        }
        $this->assertTeacherOwnsTeacher((int) ($model->teacher_id ?? 0));
        $formData = $model->toArray();
        $formData['student_name'] = $formData['student']['name'] ?? '';
        $formData['class_name'] = $formData['edu_class']['name'] ?? ($formData['student']['class_name'] ?? '');
        $formData['teacher_name'] = $formData['teacher']['realname'] ?? ($formData['teacher']['username'] ?? '');
        $formData['attendance_text'] = ((int) ($formData['attendance_status'] ?? 1) === 2) ? '已请假' : (EduLessonDetailModel::ATTENDANCE_LIST[$formData['attendance_status'] ?? null] ?? '');
        View::assign(['formData' => $formData]);
        return view();
    }
}
