<?php
/**
 * 采用FunAdmin
 * User: cjw
 * Date: 2021-12-24
 * Time: 14:43
 */

declare (strict_types=1);

namespace app\databases\controller;

use think\facade\Db;
use think\Request;
use think\App;
use think\facade\View;
use think\facade\Cookie;

/**
 * @ControllerAnnotation (title="计划任务")
 */
class Table extends \app\common\controller\Backend
{
    protected $pageSize = 15;
    protected $layout = '../../backend/view/layout/main';

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->setSearchFields = [];
        View::assign('engineList', ['InnoDB' => 'InnoDB', 'MyISAM' => 'MyISAM']);

    }

    public function indexData()
    {
        $id = Cookie::get('tablename');
        if (empty($id)) $this->error('没有数据表');
        if ($this->request->param('selectFields')) {
            $this->selectList();
        }
        list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();//添加返回条件，陈俊威
        $count = Db::table($id)
            ->where($where)
            ->count();
        $list = Db::table($id)
            ->where($where)
            ->order($sort)
            ->page($this->page, $this->pageSize)
            ->select();
        $result = ['code' => 0, 'msg' => lang('Get Data Success'), 'data' => $list, 'count' => $count];
        return json($result);
    }

    public function index()
    {
        $id = $this->request->get('id', '');
        if (!empty($id)) {
            cookie('tablename', $id);
        }
        $result = Db::query("SHOW FULL COLUMNS FROM $id");
        $width = ['int' => '90', 'varchar' => '200', 'date' => '120', 'datetime' => '180'];
        foreach ($result as $k => $v) {
            $type = explode('(', $v['Type'])[0];
            if (str_replace('_time', '', $v['Field']) != $v['Field']) $type = 'datetime';
            $result[$k]['width'] = $width[$type] ?? '100';
        }
        View::assign('result', $result);
        return view('', ['id' => Cookie::get('tablename')]);
    }


    /**
     * 设计表列数据
     * @return \think\response\View
     */
    public function columns()
    {
        $id = $this->request->param('id');
        $base = config('database.connections.mysql.database');
        $sql = 'SELECT  `COLUMN_NAME`,`IS_NULLABLE`,`DATA_TYPE`,`CHARACTER_MAXIMUM_LENGTH`,`COLUMN_COMMENT` FROM information_schema.`COLUMNS` WHERE `TABLE_SCHEMA` = \'' . $base . "' and `TABLE_NAME` = '$id'";
        $list = Db::query($sql);
        if (empty($list)) $this->error(lang('Data is not exist'));
        if ($this->request->isAjax()) {
            $sql = 'SELECT  CONCAT("' . $id . '","|",`COLUMN_NAME`) id,`COLUMN_NAME`,`IS_NULLABLE`,`DATA_TYPE`,`CHARACTER_MAXIMUM_LENGTH`,`COLUMN_COMMENT` FROM information_schema.`COLUMNS` WHERE `TABLE_SCHEMA` = \'' . $base . "' and `TABLE_NAME` = '$id'";
            $list = Db::query($sql);
            $result = ['code' => 0, 'msg' => lang('operation success'), 'data' => $list, 'count' => count($list)];
            return json($result);
        }
        $view = ['formData' => $list[0], 'title' => lang('Add'),];
        return view('', $view);
    }


    /**
     * @NodeAnnotation (title="add")
     * @return \think\response\View
     */
    public function add()
    {
        if ($this->request->isPost()) {
            $post = $this->request->post();
            foreach ($post as $k => $v) {
                if (is_array($v)) {
                    $post[$k] = implode(',', $v);
                }
            }
            $rule = [];
            try {
                $this->validate($post, $rule);
            } catch (\ValidateException $e) {
                $this->error(lang($e->getMessage()));
            }
            try {
                $save = $this->modelClass->save($post);
            } catch (\Exception $e) {
                $this->error(lang($e->getMessage()));
            }
            $save ? $this->success(lang('operation success')) : $this->error(lang('operation failed'));
        }
        $view = [
            'formData' => '',
            'title' => lang('Add'),
        ];
        return view('', $view);
    }


    /**
     * @NodeAnnotation(title="edit")
     * @return \think\response\View
     */
    public function edit()
    {
        $id = $this->request->param('id');
        halt($id);
        $tablename =
        $base = config('database.connections.mysql.database');
        $sql = 'SELECT  `COLUMN_NAME`,`IS_NULLABLE`,`DATA_TYPE`,`CHARACTER_MAXIMUM_LENGTH`,`COLUMN_COMMENT` FROM information_schema.`COLUMNS` WHERE `TABLE_SCHEMA` = \'' . $base . "' and `TABLE_NAME` = '$id'";
        $list = Db::query($sql);
        halt($list);
        if (empty($list)) $this->error(lang('Data is not exist'));
        if ($this->request->isPost()) {
            $post = $this->request->post();
            halt($post);
            foreach ($post as $k => $v) {
                if (is_array($v)) {
                    $post[$k] = implode(',', $v);
                }
            }
            try {
                $tablename = $post['TABLE_NAME'];
                $engine = $post['ENGINE'];
                $comment = $post['COMMENT'];
                if (empty($engine) || empty($comment)) $this->error(lang('关键字段没有值'));
                $sql = "ALTER TABLE `$base`.`$tablename` ENGINE = '$engine',COMMENT = '$comment';";
                $save = Db::query($sql);
            } catch (\Exception $e) {
                $this->error(lang($e->getMessage()));
            }
            $save ? $this->success(lang('operation success')) : $this->error(lang('operation failed'));
        }
        $view = ['formData' => $list[0], 'title' => lang('Add'),];
        return view('add', $view);
    }


    /**
     * 列表修改
     * @NodeAnnotation(title="modify")
     */
    public function modify()
    {
        $id = input('id');
        $field = input('field');
        $value = input('value');
        if ($id) {
            $model = $this->findModel($id);
            if (!$model) {
                $this->error(lang('Data Is Not Exist'));
            }
            $model->$field = $value;
            try {
                $save = $model->save();
            } catch (\Exception $e) {
                $this->error(lang($e->getMessage()));
            }
            $save ? $this->success(lang('Modify success')) : $this->error(lang("Modify Failed"));
        } else {
            $this->error(lang('Invalid data'));
        }
    }


    /**
     * 列表查看
     * @NodeAnnotation(title="edit")
     * @return \think\response\View
     */
    public function list()
    {
        $id = $this->request->param('id');
        $base = config('database.connections.mysql.database');
        $sql = "SELECT  `TABLE_NAME` as `id`,`TABLE_NAME`,`ENGINE`,`TABLE_ROWS`,`TABLE_COMMENT`,`CREATE_TIME`,`UPDATE_TIME`,`TABLE_COLLATION` FROM information_schema.`TABLES` WHERE `TABLE_SCHEMA` = '$base' and `TABLE_NAME` = '$id'";
        $list = Db::query($sql);
        //        $list = $list->getData();//原样输出,cjw
        if (empty($list)) $this->error(lang('Data is not exist'));
        $view = ['formData' => $list[0], 'title' => lang('Add'),];
        return view('list', $view);
    }


    /**
     * 备份数据库
     * @return array
     */
    public function beifen()
    {
        $tableName = $this->request->get('id');
        $base = config('database.connections.mysql.database');
        if (empty($tableName)) {
            $res = Db::table('information_schema.`TABLES`')->where('TABLE_SCHEMA', $base)->field('TABLE_NAME')->select();
            if (empty($res)) {
                return [false, '没有表备份'];
            }
            $alltabls = array_column($res->toArray(), 'TABLE_NAME');
        } else {
            $prefix = \config('database.connections.mysql.prefix');
            $alltabls = is_array($tableName) ? $tableName : explode(',', $tableName);
            foreach ($alltabls as $k => $v) {
                $alltabls[$k] = (str_replace($prefix, '', $v) == $v) ? $prefix . $v : $v;
            }
        }
        $nobeifne = [\config('database.connections.mysql.prefix') . 'asn_file']; //不备份的表;
        $strstr = "/*\n*备份时间：" . date('Y-m-d H:i:s') . "\n*/\n\n";
        $file = '' . $base . (count($alltabls) == 1 ? '_(' . $alltabls[0] . ')' : '') . '_' . date('Y.m.d_H.i.s') . '.sql';//文件名称
        $filepath = '' . str_replace('\\', '/', config('filesystem.disks.backup.root') . '/data/' . $file . '');//文件路径
        $bo = false;
        foreach ($alltabls as $tabs) {
            if (in_array($tabs, $nobeifne)) continue;
            $strstr .= "\nDROP TABLE IF EXISTS `$tabs`;\n";
            $sqla = Db::query('show create table `' . $tabs . '`');
            $strstr .= "" . $sqla[0]['Create Table'] . ";\n";
            $count = Db::table($tabs)->count();
            for ($p = 0, $limit = 10000; $p * $limit < $count; $p++) {//分段行数大小，防止内存溢出，如果php.ini里面的memory_limit设置的足够大，$limit可以直接改成无限大
                $rows = Db::table($tabs)->limit($p * $limit, $limit)->select();
                foreach ($rows as $k => $rs) {
                    $vstr = '';
                    foreach ($rs as $k1 => $v1) {
                        if (!empty($v1)) $v1 = str_replace("\n", '\n', $v1);
                        $v1 = ($v1 == null) ? 'null' : "'$v1'";
                        $vstr .= ",$v1";
                    }
                    $strstr .= "INSERT INTO `$tabs` VALUES(" . substr($vstr, 1) . ");\n";
                }
                $strstr .= "\n";
                @$file = fopen($filepath, 'a');
                if ($file) {
                    $bo = true;
                    if ($strstr) $bo = fwrite($file, $strstr);
                    fclose($file);
                }
                $strstr = "\n";
            }
            if ($count == 0) {
                @$file = fopen($filepath, 'a');
                if ($file) {
                    $bo = true;
                    if ($strstr) $bo = fwrite($file, $strstr);
                    fclose($file);
                }
                $strstr = "\n";
            }
        }
        $bo ? $this->success(lang('operation success')) : $this->error(lang('operation failed'));
    }


}

