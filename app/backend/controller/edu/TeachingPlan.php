<?php
/**
 * 教案资料库控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\EduTeachingPlan;
use app\backend\model\EduCourse;
use app\backend\model\Admin;
use app\common\controller\Backend;
use think\App;
use think\facade\View;
use think\facade\Filesystem;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="教案资料库")
 * Class TeachingPlan
 * @package app\backend\controller\edu
 */
class TeachingPlan extends Backend
{
    protected function checkToken()
    {
        return true;
    }

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new EduTeachingPlan();
    }

    /**
     * @NodeAnnotation(title="列表")
     * @return mixed|	hink
esponseson|	hink
esponseiew
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            if ($this->request->param('selectFields')) {
                $this->selectList();
            }

            list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();
            $sort = ['id' => 'asc'];
            $keyword = trim((string) $this->request->param('keyword', $this->request->param('search', '')));
            
            $query = $this->modelClass
                ->with(['course', 'teacher'])
                ->where($where);

            if ($keyword !== '') {
                $courseIds = EduCourse::where('name|course_no', 'like', '%' . $keyword . '%')->column('id');
                $teacherIds = Admin::where('username|realname', 'like', '%' . $keyword . '%')->column('id');
                $matchedIds = $this->modelClass->where('title|file_name', 'like', '%' . $keyword . '%')->column('id');

                if (!empty($courseIds)) {
                    $matchedIds = array_merge($matchedIds, $this->modelClass->where('course_id', 'in', $courseIds)->column('id'));
                }
                if (!empty($teacherIds)) {
                    $matchedIds = array_merge($matchedIds, $this->modelClass->where('teacher_id', 'in', $teacherIds)->column('id'));
                }

                $matchedIds = array_values(array_unique(array_filter($matchedIds)));
                $query->where('id', 'in', !empty($matchedIds) ? $matchedIds : [0]);
            }
            
            $count = $query->count();
                
            $list = $query
                ->order($sort)
                ->page($this->page, $this->pageSize)
                ->select();

            $list = $list->each(function ($item) {
                $item->course_name = EduCourse::where('id', $item->course_id)->value('name') ?: '';
                $item->teacher_name = $item->teacher ? $item->teacher->username : '';
                $item->category_text = $item->category_text;
                $item->file_size_text = $item->file_size_text;
                return $item;
            })->toArray();

            $result = ['code' => 0, 'msg' => lang('get formData success'), 'data' => $list, 'count' => $count];
            return json($result);
        }

        return view();
    }

    /**
     * @NodeAnnotation(title="添加")
     * @return 	hink
esponseiew
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $currentTeacherId = (int) session('admin.id');
            if ($currentTeacherId <= 0) {
                $this->error('教师信息异常，请重新登录后再试');
            }
            
            $rule = [
                'title|教案标题' => 'require|max:255',
                'course_id|课程' => 'require',
                'category|分类' => 'require',
            ];
            $this->validate($post, $rule);
            $categoryId = $post['category'] ?? $post['category_id'] ?? null;
            
            // 处理文件上传
            $file = $this->request->file('file');
            if (!$file) {
                $file = request()->file('file');
            }
            if (!$file && isset($_FILES['file']) && !empty($_FILES['file']['tmp_name'])) {
                $file = \think\facade\Request::file('file');
            }
            if (!$file) {
                $this->error('请上传教案文件');
            }
            
            // 验证文件类型
            $allowedExts = ['txt', 'doc', 'docx', 'pdf', 'xls', 'xlsx', 'zip', 'rar', '7z'];
            $ext = strtolower($file->getOriginalExtension());
            if (!in_array($ext, $allowedExts)) {
                $this->error('只支持txt、doc、docx、pdf、xls、xlsx、zip、rar、7z文件类型');
            }
            
            // 验证文件大小
            $maxSize = 10 * 1024 * 1024; // 10M
            if ($file->getSize() > $maxSize) {
                $this->error('文件大小不能超过10M');
            }
            
            // 上传文件
            $savename = Filesystem::putFile('upload/teaching_plan', $file, 'md5');
            $filePath = 'storage/' . $savename;
            
            $data = [
                'title' => $post['title'],
                'course_id' => $post['course_id'],
                'category_id' => $categoryId,
                'teacher_id' => $currentTeacherId,
                'file_name' => $file->getOriginalName(),
                'file_path' => $filePath,
                'file_size' => $file->getSize(),
                'file_type' => $ext,
                'download_count' => 0,
                'status' => $post['status'] ?? 1,
            ];
            
            $result = $this->modelClass->save($data);
            
            if ($result) {
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('operation failed'));
            }
        }
        
        $courseList = EduCourse::where('status', 1)->order('id desc')->field(['id', 'name'])->select()->toArray();
        $currentTeacher = Admin::find((int) session('admin.id'));
        
        $view = [
            'formData' => ['teacher_id' => (int) session('admin.id')],
            'courseList' => $courseList,
            'currentTeacherName' => $currentTeacher ? (($currentTeacher->realname ?: $currentTeacher->username) ?: '') : '',
            'categoryList' => EduTeachingPlan::CATEGORY_LIST,
            'statusList' => EduTeachingPlan::STATUS_LIST,
            'title' => lang('Add'),
        ];
        View::assign($view);
        return view();
    }

    /**
     * @NodeAnnotation(title="编辑")
     * @return 	hink
esponseiew
     */
    public function edit()
    {
        $id = $this->request->param('id');
        
        if ($this->request->isPost()) {
            $post = $this->request->post();
            $currentTeacherId = (int) session('admin.id');
            if ($currentTeacherId <= 0) {
                $this->error('教师信息异常，请重新登录后再试');
            }
            
            $rule = [
                'title|教案标题' => 'require|max:255',
                'course_id|课程' => 'require',
                'category|分类' => 'require',
            ];
            $this->validate($post, $rule);
            $categoryId = $post['category'] ?? $post['category_id'] ?? null;
            
            $model = $this->modelClass->find($id);
            
            // 处理文件上传
            $file = $this->request->file('file');
            if ($file) {
                // 验证文件类型
                $allowedExts = ['txt', 'doc', 'docx', 'pdf', 'xls', 'xlsx', 'zip', 'rar', '7z'];
                $ext = strtolower($file->getOriginalExtension());
                if (!in_array($ext, $allowedExts)) {
                    $this->error('只支持txt、doc、docx、pdf、xls、xlsx、zip、rar、7z文件类型');
                }
                
                // 验证文件大小
                $maxSize = 10 * 1024 * 1024; // 10M
                if ($file->getSize() > $maxSize) {
                    $this->error('文件大小不能超过10M');
                }
                
                $oldFilePath = $model->file_path;

                // 上传文件
                $savename = Filesystem::putFile('upload/teaching_plan', $file, 'md5');
                $filePath = 'storage/' . $savename;
                
                // 更新文件信息
                $model->file_name = $file->getOriginalName();
                $model->file_path = $filePath;
                $model->file_size = $file->getSize();
                $model->file_type = $ext;

                $this->removeStoredFile($oldFilePath);
            }
            
            $model->title = $post['title'];
            $model->course_id = $post['course_id'];
            $model->category_id = $categoryId;
            $model->teacher_id = $currentTeacherId;
            $model->status = $post['status'] ?? 1;
            
            $result = $model->save();
            
            if ($result) {
                $this->success(lang('operation success'));
            } else {
                $this->error(lang('operation failed'));
            }
        }
        
        $formData = $this->modelClass->find($id)->toArray();
        $formData['teacher_id'] = (int) session('admin.id');
        $courseList = EduCourse::where('status', 1)->order('id desc')->field(['id', 'name'])->select()->toArray();
        $currentTeacher = Admin::find((int) session('admin.id'));
        
        $view = [
            'formData' => $formData,
            'courseList' => $courseList,
            'currentTeacherName' => $currentTeacher ? (($currentTeacher->realname ?: $currentTeacher->username) ?: '') : '',
            'categoryList' => EduTeachingPlan::CATEGORY_LIST,
            'statusList' => EduTeachingPlan::STATUS_LIST,
            'title' => lang('Edit'),
        ];
        View::assign($view);
        return view('add');
    }

    /**
     * @NodeAnnotation(title="查看")
     */
    public function view()
    {
        $id = $this->request->param('id');
        $model = $this->modelClass->with(['course', 'teacher'])->find($id);
        if (!$model) {
            $this->error(lang('record not found'));
        }

        $formData = $model->toArray();
        $formData['course_name'] = EduCourse::where('id', $formData['course_id'] ?? 0)->value('name') ?: '';
        $formData['teacher_name'] = $model->teacher ? $model->teacher->username : '';
        $formData['category_text'] = $model->category_text;
        $formData['file_size_text'] = $model->file_size_text;

        View::assign([
            'formData' => $formData,
            'categoryList' => EduTeachingPlan::CATEGORY_LIST,
            'statusList' => EduTeachingPlan::STATUS_LIST,
        ]);
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
            $this->error(lang('record not found'));
        }

        foreach ($list as $item) {
            $this->removeStoredFile($item->file_path);
        }

        try {
            foreach ($list as $item) {
                $item->delete();
            }
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }

        $this->success(lang('operation success'));
    }

    /**
     * 清理存储文件及空目录
     */
    protected function removeStoredFile($filePath)
    {
        if (empty($filePath)) {
            return;
        }

        $relativePath = trim(str_replace('\\', '/', $filePath), '/');
        $fileRealPath = rtrim(public_path(), '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $uploadRoot = rtrim(public_path(), '/\\') . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'teaching_plan';
        $uploadRootRealPath = realpath($uploadRoot) ?: $uploadRoot;

        $directory = is_file($fileRealPath) ? dirname($fileRealPath) : dirname($fileRealPath);
        if (is_file($fileRealPath)) {
            unlink($fileRealPath);
        }

        while ($directory && is_dir($directory)) {
            $directoryRealPath = realpath($directory) ?: $directory;
            if (strpos($directoryRealPath, $uploadRootRealPath) !== 0) {
                break;
            }
            if (count(scandir($directoryRealPath)) > 2) {
                break;
            }
            rmdir($directoryRealPath);
            if ($directoryRealPath === $uploadRootRealPath) {
                break;
            }
            $directory = dirname($directoryRealPath);
        }
    }

    /**
     * @NodeAnnotation(title="下载")
     */
    public function download()
    {
        $id = $this->request->param('id');
        
        if (!$id) {
            $this->error(lang('id error'));
        }
        
        $model = $this->modelClass->find($id);
        if (!$model) {
            $this->error(lang('record not found'));
        }
        
        $fileRealPath = public_path() . ltrim($model->file_path, '/\\');
        if (!file_exists($fileRealPath)) {
            $this->error('文件不存在');
        }
        
        // 增加下载次数
        $model->download_count += 1;
        $model->save();
        
        // 下载文件
        return download($fileRealPath, $model->file_name);
    }
}