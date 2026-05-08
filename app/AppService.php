<?php
declare (strict_types = 1);

namespace app;

use think\Service;
use think\facade\Route;

/**
 * 应用服务类
 */
class AppService extends Service
{
    public function register()
    {
        // 服务注册
    }

    public function boot()
    {
        // 服务启动
        // 加载后端教务管理路由
        if (file_exists($this->app->getBasePath() . 'backend/route/edu.php')) {
            include $this->app->getBasePath() . 'backend/route/edu.php';
        }
    }
}
