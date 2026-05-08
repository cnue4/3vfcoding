<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2019/9/21
 */

namespace app\common\controller;
use app\BaseController;
use app\backend\model\Admin;
use app\backend\model\AuthGroup;
use app\backend\model\EduClass;
use app\backend\model\Student;
use app\common\model\Languages;
use app\common\traits\Jump;
use app\common\traits\Curd;
use think\App;
use think\captcha\facade\Captcha;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Cookie;
use think\facade\Lang;
use app\backend\middleware\CheckRole;
use app\backend\middleware\ViewNode;
use app\backend\middleware\SystemLog;
use think\helper\Str;

class Backend extends BaseController
{
    use Jump,Curd;

    /**
     * 无需登录
     * @var array
     */
    protected array $noNeedLogin = ['enlang','verify'];
    protected array $onlyNeedLogin = [];
    protected $middleware = [
        ViewNode::class,
        SystemLog::class
    ];

    /**
    * @var
     * 模型
     */
    protected $modelClass;
    /**
     * @var
     * 页面大小
     */
    protected $pageSize;
    /**
     * @var
     * 页数
     */
    protected $page;
    /**
     * 模板布局,
     * @var string|bool
     */
    protected $layout = 'layout/main';
    /**
     * 快速搜索时执行查找的字段
     */
    protected $searchFields = 'id';
    /**
     * 下拉选项条件
     * @var string
     */

    protected $selectMap =[];

    protected $allowModifyFields = [
        'status',
        'sort',
        'title',
        'auth_verify',
    ];
    /**
     * 是否是关联查询
     */
    protected $relationSearch = false;

    /**
     * 关联join搜索
     * @var array
     */
    protected $joinSearch = [];
    /**
     * selectpage 字段
     * @var string[]
     */
    protected $selectpageFields = ['*'];

    /**
     * 隐藏字段
     * @var array
     */
    protected $hiddenFields = [];

    /**
     * 可见字段
     * @var array
     */
    protected $visibleFields = [];


    /**
     * 是否开启数据限制
     * 表示按权限判断/仅限个人
     */
    protected $dataLimit = false;

    /**
     * @var string
     */
    protected $dataLimitField = 'admin_id';
    /**
     * 导出字段
     * @var string[]
     */

    protected $exportFields = ['*'];
    /**
     * 导入字段
     * @var string[]
     */
    protected $importFields = ['*'];
    /**
     * @param App $app
     */
    public function __construct(App $app)
    {
        parent::__construct($app);
        $auth = [];
        if(!empty($this->noNeedLogin) && $this->noNeedLogin!=['*']){
            $auth['except'] = $this->noNeedLogin;
        }
        if(!empty($this->onlyNeedLogin)){
            $auth['only'] = $this->onlyNeedLogin;
        }
        if(!empty($auth)){
            $this->middleware = [CheckRole::class=>$auth] + $this->middleware;
        }else{
            $this->middleware = [CheckRole::class] + $this->middleware;
        }
        //模板管理
        $this->layout && $this->app->view->engine()->layout($this->layout);
        $controller = $this->request->controller(true);
        if($controller!=='ajax'){
            $this->loadlang($controller,app()->http->getName());
        }
        //过滤参数
        $this->pageSize = input('limit', 15);
        $this->page = input('page', 1);
    }

    public function enlang()
    {
        $lang = $this->request->get('lang');
        $language = Languages::where('name',$lang)->find();
        if(!$language) $this->error(lang('please check language config'));
        if(strtolower($lang)=='zh-cn' || !$lang){
            Cookie::set('think_lang', 'zh-cn');
        }else{
            Cookie::set('think_lang', $lang);
        }
        Cache::clear();
        $this->success(lang('Change Success'));
    }

    /**
     * @return \think\Response
     * 验证码
     */
    public function verify()
    {
        return Captcha::create();
    }
    //自动加载语言
    protected function loadlang($name,$app)
    {
        $lang = cookie(config('lang.cookie_var'));
        if(!empty($lang) && Str::contains($lang,'../')){
            return false;
        }
        if($app && $app!=='backend'){
            $res =  Lang::load([
                $this->app->getBasePath() .'backend'. DS . 'lang' . DS . $lang . '.php',
                $this->app->getBasePath() .$app. DS . 'lang' . DS . $lang  . '.php',
                $this->app->getBasePath() .$app. DS . 'lang' . DS . $lang . DS . str_replace('.', DS, $name) . '.php',
            ]);
       }else{
            $res = Lang::load([
                $this->app->getAppPath() . 'lang' . DS . $lang . '.php',
                $this->app->getAppPath() . 'lang' . DS . $lang . DS . str_replace('.', DS, $name) . '.php',
            ]);
        }
        return $res;

    }
    protected function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        try {
            parent::validate($data, $validate, $message, $batch);
            $this->checkToken();
        } catch (ValidateException $e) {
            $this->error($e->getMessage());
        }
        return true;
    }

    /**
     * 检测token 并刷新
     *
     */
    protected function checkToken()
    {
        $check = $this->request->checkToken('__token__', $this->request->param());
        if (false === $check) {
            $this->error(lang('Token verify error'));
        }
    }

    /**
     * 建立Token
     */
    protected function token()
    {
        return $this->request->buildToken();
    }

    protected function getCurrentAdminId(): int
    {
        return (int) session('admin.id');
    }

    protected function getCurrentAdminGroupId(): int
    {
        return (int) session('admin.group_id');
    }

    protected function getCurrentAdminGroupTitle(): string
    {
        $groupTitle = (string) session('admin.authGroup.title');
        if ($groupTitle !== '') {
            return $groupTitle;
        }

        $groupId = $this->getCurrentAdminGroupId();
        if ($groupId <= 0) {
            return '';
        }

        return (string) AuthGroup::where('id', $groupId)->value('title');
    }

    protected function isTeacherRole(): bool
    {
        $adminId = $this->getCurrentAdminId();
        if ($adminId <= 0 || $adminId === 1) {
            return false;
        }

        $groupTitle = $this->getCurrentAdminGroupTitle();
        return $groupTitle !== '' && mb_strpos($groupTitle, '教师') !== false;
    }

    protected function getTeacherOwnedClassIds(): array
    {
        if (!$this->isTeacherRole()) {
            return [];
        }

        return array_map('intval', EduClass::where('teacher_id', $this->getCurrentAdminId())->whereNull('delete_time')->column('id'));
    }

    protected function filterTeacherOwnedClassIds(array $classIds): array
    {
        $classIds = array_values(array_unique(array_filter(array_map('intval', $classIds))));
        if (!$this->isTeacherRole()) {
            return $classIds;
        }

        $ownedClassIds = $this->getTeacherOwnedClassIds();
        return array_values(array_intersect($classIds, $ownedClassIds));
    }

    protected function assertTeacherOwnsClass(int $classId, string $message = '没有权限'): void
    {
        if (!$this->isTeacherRole()) {
            return;
        }

        if ($classId <= 0 || !in_array($classId, $this->getTeacherOwnedClassIds(), true)) {
            $this->error($message);
        }
    }

    protected function assertTeacherOwnsTeacher(int $teacherId, string $message = '没有权限'): void
    {
        if (!$this->isTeacherRole()) {
            return;
        }

        if ($teacherId !== $this->getCurrentAdminId()) {
            $this->error($message);
        }
    }

    protected function assertTeacherOwnsStudent(int $studentId, string $message = '没有权限'): void
    {
        if (!$this->isTeacherRole()) {
            return;
        }

        $student = Student::find($studentId);
        if (!$student) {
            $this->error($message);
        }

        $this->assertTeacherOwnsClass((int) ($student->class_id ?? 0), $message);
    }

    protected function applyTeacherClassScope($query, string $field = 'class_id')
    {
        if (!$this->isTeacherRole()) {
            return $query;
        }

        $classIds = $this->getTeacherOwnedClassIds();
        $query->whereIn($field, !empty($classIds) ? $classIds : [0]);
        return $query;
    }

    protected function applyTeacherTeacherScope($query, string $field = 'teacher_id')
    {
        if (!$this->isTeacherRole()) {
            return $query;
        }

        $query->where($field, $this->getCurrentAdminId());
        return $query;
    }

    protected function applyTeacherStudentScope($query, string $field = 'student_id')
    {
        if (!$this->isTeacherRole()) {
            return $query;
        }

        $studentIds = $this->getTeacherOwnedStudentIds();
        $query->whereIn($field, !empty($studentIds) ? $studentIds : [0]);
        return $query;
    }

    protected function getTeacherOwnedStudentIds(): array
    {
        if (!$this->isTeacherRole()) {
            return [];
        }

        $classIds = $this->getTeacherOwnedClassIds();
        if (empty($classIds)) {
            return [];
        }

        return array_map(
            'intval',
            Student::whereIn('class_id', $classIds)->whereNull('delete_time')->column('id')
        );
    }

}

