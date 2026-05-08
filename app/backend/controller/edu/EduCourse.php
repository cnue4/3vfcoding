<?php
/**
 * 课程管理控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\EduCourse as EduCourseModel;
use app\backend\model\EduCourseCategory;
use app\common\controller\Backend;
use think\App;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="课程管理")
 * Class EduCourse
 * @package app\backend\controller\edu
 */
class EduCourse extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new EduCourseModel();
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

            $query = $this->modelClass
                ->with(['category'])
                ->where($where);

            if ($keyword !== '') {
                $categoryIds = EduCourseCategory::where('name', 'like', '%' . $keyword . '%')->column('id');
                $matchedIds = $this->modelClass->where('name|course_no', 'like', '%' . $keyword . '%')->column('id');
                if (!empty($categoryIds)) {
                    $matchedIds = array_merge($matchedIds, $this->modelClass->where('category_id', 'in', $categoryIds)->column('id'));
                }
                $matchedIds = array_values(array_unique(array_filter($matchedIds)));
                $query->where('id', 'in', !empty($matchedIds) ? $matchedIds : [0]);
            }
            
            $count = $query->count();
                
            $list = $query
                ->order($sort)
                ->page($this->page, $this->pageSize)
                ->select()
                ->toArray();

            $result = ['code' => 0, 'msg' => lang('get formData success'), 'data' => $list, 'count' => $count];
            return json($result);
        }

        return view();
    }

    /**
     * @NodeAnnotation(title="添加")
     * @return \think\response\View
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            
            $rule = [
                'name|课程名称' => 'require|max:100',
                'category_id|课程分类' => 'require',
                'total_hours|总课时' => 'require|number',
                'unit_price|课时单价' => 'require|number',
            ];
            $this->validate($post, $rule);
            
            $post['course_no'] = EduCourseModel::generateCourseNo();
            $post = array_merge($this->transformCourseData($post), ['course_no' => $post['course_no']]);
            
            $result = $this->modelClass->save($post);
            
            if ($result) {
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('operation failed'));
            }
        }
        
        $categoryList = EduCourseCategory::where('status', 1)->order('sort asc,id asc')->field(['id', 'name'])->select()->toArray();
        
        $view = [
            'formData' => [],
            'categoryList' => $categoryList,
            'statusList' => EduCourseModel::STATUS_LIST,
            'typeList' => EduCourseModel::TYPE_LIST,
            'difficultyList' => EduCourseModel::DIFFICULTY_LIST,
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
        
        if ($this->request->isPost()) {
            $post = $this->request->post();
            
            $rule = [
                'name|课程名称' => 'require|max:100',
                'category_id|课程分类' => 'require',
                'total_hours|总课时' => 'require|number',
                'unit_price|课时单价' => 'require|number',
            ];
            $this->validate($post, $rule);
            
            $post = $this->transformCourseData($post);
            
            $model = $this->modelClass->find($id);
            $result = $model->save($post);
            
            if ($result) {
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('operation failed'));
            }
        }
        
        $formData = $this->modelClass->find($id)->toArray();
        $categoryList = EduCourseCategory::where('status', 1)->order('sort asc,id asc')->field(['id', 'name'])->select()->toArray();
        
        $view = [
            'formData' => $formData,
            'categoryList' => $categoryList,
            'statusList' => EduCourseModel::STATUS_LIST,
            'typeList' => EduCourseModel::TYPE_LIST,
            'difficultyList' => EduCourseModel::DIFFICULTY_LIST,
            'title' => lang('Edit'),
        ];
        View::assign($view);
        return view('add');
    }

    /**
     * 课程管理临时关闭 token 校验
     */
    protected function checkToken()
    {
        return true;
    }

    /**
     * 兼容当前数据库课程字段
     */
    protected function transformCourseData(array $post): array
    {
        $data = [
            'name' => $post['name'] ?? '',
            'category_id' => $post['category_id'] ?? 0,
            'total_hours' => $post['total_hours'] ?? 0,
            'unit_price' => $post['unit_price'] ?? 0,
            'course_type' => !empty($post['course_type']) ? (int) $post['course_type'] : 1,
            'difficulty' => !empty($post['difficulty']) ? (int) $post['difficulty'] : 1,
            'status' => isset($post['status']) ? (int) $post['status'] : 1,
        ];

        if (!empty($post['course_no'])) {
            $data['course_no'] = $post['course_no'];
        }

        return $data;
    }

    /**
     * @NodeAnnotation(title="查看")
     * @return \think\response\View
     */
    public function view()
    {
        $id = $this->request->param('id');
        $model = $this->modelClass->find($id);
        if (!$model) {
            $this->error('课程不存在');
        }

        View::assign([
            'formData' => $model->toArray(),
            'categoryList' => EduCourseCategory::where('status', 1)->order('sort asc,id asc')->column('name', 'id'),
            'statusList' => EduCourseModel::STATUS_LIST,
            'typeList' => EduCourseModel::TYPE_LIST,
            'difficultyList' => EduCourseModel::DIFFICULTY_LIST,
        ]);
        return view();
    }

    /**
     * @NodeAnnotation(title="删除")
     */
    public function delete()
    {
        $ids = $this->request->param('ids') ? $this->request->param('ids') : $this->request->param('id');
        if (!empty($ids)) {
            $hasClass = \app\backend\model\EduClass::where('course_id', 'in', $ids)->where('status', 1)->count();
            if ($hasClass > 0) {
                $this->error('该课程下还有正常班级，无法删除');
            }
            
            $list = $this->modelClass->where('id', 'in', $ids)->select();
            try {
                foreach ($list as $k => $v) {
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
