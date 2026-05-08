<?php

/**
 * 采用FunAdmin
 * User: cjw
 * Date: 2021-12-24
 * Time: 14:43
 * 需要在filesystem里面配置备份目录，设置方法
 * 'backup' => [
 * 'type' => 'local',
 * 'root' => app()->getRootPath() . 'backup',
 * 'url' => 'backup',
 * 'visibility' => 'public',
 * ],
 */

declare (strict_types=1);

namespace app\databases\controller;

use app\common\controller\Backend;
use fun\helper\ZipHelper;
use think\db\exception\DbException;
use think\facade\Db;
use think\Request;
use think\App;
use think\facade\View;
use fun\helper\FileHelper;
/**
 * @ControllerAnnotation (title="计划任务")
 */
class Database extends Backend
{
    protected $pageSize = 15;
    protected $layout = '../../backend/view/layout/main';
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->setSearchFields = [];
        View::assign('engineList', ['InnoDB' => 'InnoDB', 'MyISAM' => 'MyISAM']);
    }

    /**
     *更新数据库表缓存，刷新列
     */
    public function dataclear()
    {
//        $sql = 'SET GLOBAL information_schema_stats_expiry=0;';
//        Db::execute($sql);
//        $sql = 'SET @@GLOBAL.information_schema_stats_expiry=0;';
//        Db::execute($sql);
//        $sql = 'SET SESSION information_schema_stats_expiry=0;';
//        Db::execute($sql);
        $sql = 'SET @@SESSION.information_schema_stats_expiry=0;';
        Db::execute($sql);
        $this->success('成功');
    }


    /**
     *数据库列表
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            if ($this->request->param('selectFields')) {
                $this->selectList();
            }
            list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();
            $where[] = ['`TABLE_SCHEMA`', '=', config('database.connections.mysql.database')];
            $wheres = '';
            foreach ($where as $v) {
                $wheres .= (empty($wheres) ? '' : ' and ') . $v[0] . ' ' . $v[1] . ' \'' . $v[2] . '\'';
            }
            $sql = 'SELECT  `TABLE_NAME` as `id`,`TABLE_NAME`,`ENGINE`,`TABLE_ROWS`,`TABLE_COMMENT`,`CREATE_TIME`,`UPDATE_TIME`,`TABLE_COLLATION` FROM information_schema.`TABLES` WHERE ' . $wheres;
//			halt($sql);
            $list = Db::query($sql);
            $result = ['code' => 0, 'msg' => lang('Get Data Success'), 'data' => $list, 'count' => count($list)];
            return json($result);
        }
        return view();
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
        $base = config('database.connections.mysql.database');
        $sql = 'SELECT  `TABLE_NAME` as `id`,`TABLE_NAME`,`ENGINE`,`TABLE_ROWS`,`TABLE_COMMENT`,`CREATE_TIME`,`UPDATE_TIME`,`TABLE_COLLATION` FROM information_schema.`TABLES` WHERE `TABLE_SCHEMA` = \'' . $base . "' and `TABLE_NAME` = '$id'";
        $list = Db::query($sql);
        if (empty($list)) $this->error(lang('Data is not exist'));
        if ($this->request->isPost()) {
            $post = $this->request->post();
            foreach ($post as $k => $v) {
                if (is_array($v)) {
                    $post[$k] = implode(',', $v);
                }
            }
            try {
                $tablename = $post['TABLE_NAME'];
                $engine = $post['ENGINE'];
                $comment = $post['TABLE_COMMENT'];
                $collation = $post['TABLE_COLLATION'];
                if (empty($engine) || empty($comment)) $this->error(lang('关键字段没有值'));
                $sql = "ALTER TABLE `$base`.`$tablename` ENGINE = '$engine',COMMENT = '$comment', COLLATE='$collation' ;";
                $save = Db::query($sql);
            } catch (DbException $e) {
                $this->error(lang($e->getMessage()));
            }
            $this->success(lang('operation success'));
        }
        $view = ['formData' => $list[0], 'title' => lang('Add'),];
        return view('add', $view);
    }


    /**
     * 数据库列表查看
     * @NodeAnnotation(title="edit")
     * @return \think\response\View
     */
    public function list()
    {
        $id = $this->request->param('id');
        $base = config('database.connections.mysql.database');
        $sql = "SELECT  `TABLE_NAME` as `id`,`TABLE_NAME`,`ENGINE`,`TABLE_ROWS`,`TABLE_COMMENT`,`CREATE_TIME`,`UPDATE_TIME`,`TABLE_COLLATION` FROM information_schema.`TABLES` WHERE `TABLE_SCHEMA` = '$base' and `TABLE_NAME` = '$id'";
        $list = Db::query($sql);
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
        $filepath = './data';//文件路径
        if (!is_dir($filepath)) {
            FileHelper::mkdirs($filepath);
        }
        $filepath .= $file . '';
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
        $this->delFile();
        $bo ? $this->success(lang('备份成功')) : $this->error(lang('备份失败'));
    }


    /**
     * 删除历史的备份文件，避免文件过多导致磁盘不足
     * @param int $num
     */
    public function delFile()
    {
        $filePath = './data/';//目录
        $ha = scandir($filePath);//扫描文件
        $daynum = '-20 day';//设置删除超过时长
        $num = 30;//设置保留文件个数
        $now = date('Y.m.d', strtotime($daynum));//设置删除时间段前的数据
        $base = config('database.connections.mysql.database');
        $filename = $base . '_' . $now;
        foreach ($ha as $k => $v) {
            if (strpos($v, '.sql') !== false) {//压缩文件
                $zip = new \ZipArchive();
                $zip->open(($filePath . @str_replace('sql', 'zip', $v)), \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
                $zip->addFile($v);
                $zip->close();
            }
            if ($v < $filename && strpos($v, $base) !== false && strpos($v, '.zip') === true) {
                unlink($filePath . $v);
            } else if ($k > $num && strpos($v, '.zip') === true) {//超过30个文件，开始删除超出的
                unlink($filePath . $v);
            }
        }
        return [count($ha), '删除成功'];//执行成功
    }

}

