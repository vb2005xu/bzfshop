<?php

/**
 * @author QiangYu
 *
 * 供货商后台
 *
 * */

use Core\Helper\Utility\Auth as AuthHelper;
use Core\Helper\Utility\Route as RouteHelper;
use Core\Plugin\PluginHelper;
use Core\Plugin\ThemeHelper;
use Core\Service\Order\Order as OrderBasicService;

define('SUPPLIER_PATH', dirname(__FILE__));
define('SUPPLIER_DIR', basename(SUPPLIER_PATH));

// 包含整个系统的初始化
require_once(SUPPLIER_PATH . '/../protected/bootstrap.php');

//预先加载一些常用模块，提高后面的加载效率
require_once(PROTECTED_PATH . '/Core/Helper/Utility/Auth.php');
require_once(PROTECTED_PATH . '/Core/Helper/Utility/Route.php');
require_once(PROTECTED_PATH . '/Core/Helper/Utility/Time.php');
require_once(PROTECTED_PATH . '/Core/Service/Order/Order.php');
require_once(PROTECTED_PATH . '/Core/Plugin/PluginHelper.php');
require_once(PROTECTED_PATH . '/Core/Plugin/ThemeHelper.php');
require_once(PROTECTED_PATH . '/Core/Log/File.php');
require_once(PROTECTED_PATH . '/Core/Asset/IManager.php');
require_once(PROTECTED_PATH . '/Core/Asset/SimpleManager.php');
require_once(PROTECTED_PATH . '/Core/Asset/ManagerHelper.php');

// ---------------------------------------- 1. 设置系统运行设置 --------------------------------------

// 加载全局变量设置
$f3->config(PROTECTED_PATH . '/Config/supplier.cfg');
// 根据环境变量的不同，加载对应的环境变量设置，开发环境和生产环境的配置显然是不一样的
$f3->config(PROTECTED_PATH . '/Config/supplier-' . $f3->get('sysConfig[env]') . '.cfg');

// 设置工作时区
if ($f3->get('sysConfig[time_zone]')) {
    date_default_timezone_set($f3->get('sysConfig[time_zone]'));
}

// 当前网站的 webroot_url_prefix
if (!$f3->get('sysConfig[webroot_url_prefix]')) {
    $f3->set(
        'sysConfig[webroot_url_prefix]',
        $f3->get('SCHEME') . '://' . $f3->get('HOST')
        . (('80' != $f3->get('PORT')) ? ':' . $f3->get('PORT') : '')
        . $f3->get('BASE')
    );
}

//数据路径
if (!$f3->get('sysConfig[data_path_root]')) {
    $f3->set('sysConfig[data_path_root]', realpath(SUPPLIER_PATH . '/../data'));
}

//数据 url prefix
if (!$f3->get('sysConfig[data_url_prefix]')) {
    $f3->set('sysConfig[data_url_prefix]', str_replace('/' . SUPPLIER_DIR, '/data', $f3->get('BASE')));
}

//图片 image_url_prefix
if (!$f3->get('sysConfig[image_url_prefix]')) {
    $f3->set('sysConfig[image_url_prefix]', $f3->get('sysConfig[data_url_prefix]'));
}

// RunTime 路径
if (!$f3->get('sysConfig[runtime_path]')) {
    $f3->set('sysConfig[runtime_path]', realpath(PROTECTED_PATH . '/Runtime'));
}

define('RUNTIME_PATH', $f3->get('sysConfig[runtime_path]'));

// 设置 Tmp 路径
$f3->set('TEMP', RUNTIME_PATH . '/Temp/');

// 设置 Log 路径
$f3->set('LOGS', RUNTIME_PATH . '/Log/Supplier/');

//开启 Cache 功能
if (!$f3->get('CACHE')) {
    // 让 F3 自动选择使用最优的 Cache 方案，最差的情况会使用 TEMP/cache 目录文件做缓存
    $f3->set('CACHE', 'true');
}

// 设置网站唯一的 key，防止通用模块之间的冲突
RouteHelper::$uniqueKey           = 'SUPPLIER';
AuthHelper::$uniqueKey            = 'SUPPLIER';
OrderBasicService::$orderSnPrefix = 'SUPPLIER';

// 初始化 smarty 模板引擎
$smarty->debugging     = $f3->get('sysConfig[smarty_debug]');
$smarty->force_compile = $f3->get('sysConfig[smarty_force_compile]');
$smarty->use_sub_dirs  = $f3->get('sysConfig[smarty_use_sub_dirs]');

//设置 smarty 工作目录
$smarty->setCompileDir(RUNTIME_PATH . '/Smarty/Supplier/Compile');
$smarty->setCacheDir(RUNTIME_PATH . '/Smarty/Supplier/Cache');

// ---------------------------------------- 2. 开启系统日志 --------------------------------------

// 设置系统的日志
$todayDateStr   = \Core\Helper\Utility\Time::localTimeStr('Y-m-d');
$todayDateArray = explode('-', $todayDateStr);

// 设置一个 fileLogger 方便查看所有的日志输出，按照 年/月/年-月-日.log 输出
$fileLogger =
    new \Core\Log\File(
        $todayDateArray[0] . '/' . $todayDateArray[1] . '/' . implode('-', $todayDateArray) . '.supplier.log');
// 我们不打印 DEBUG 级别的日志，不然数据量太大了
$fileLogger->levelAllow = array(
    \Core\Log\Base::CRITICAL,
    \Core\Log\Base::ERROR,
    \Core\Log\Base::WARN,
    \Core\Log\Base::NOTICE,
    \Core\Log\Base::INFO,
);
$logger->addLogger($fileLogger);
unset($fileLogger);

/* * **************** 如果是调试模式，在这里设置调试 ************************ */
if ($f3->get('DEBUG')) {

    // 调试模式，关闭缓存
    $f3->set('CACHE', false);

    // 调试模式下，弄一个 fileLogger 方便查看所有的日志输出
    $fileLogger = new \Core\Log\File(\Core\Helper\Utility\Time::localTimeStr('Y-m-d') . '.supplier.debug.log');
    $logger->addLogger($fileLogger);

    // 把 smarty 的一些错误警告关闭，不然会影响我们的调试
    Smarty::muteExpectedErrors();

    // 使用自定义的调试框架
    if ($f3->get('USERDEBUG')) {
        require_once(PROTECTED_PATH . '/Framework/Debug/BzfDebug.php');
        // 开启 debug 功能
        BzfDebug::enableDebug();
        // 开启 Smarty Web Log
        BzfDebug::enableSmartyWebLog();
    }

}

// ---------------------------------------- 3. 初始化资源管理器 AssetManager --------------------------------------

// asset 路径，用于发布 css, js , 图片 等
if (!$f3->get('sysConfig[asset_path_root]')) {
    $f3->set('sysConfig[asset_path_root]', realpath(SUPPLIER_PATH . '/asset'));
}

if (!$f3->get('sysConfig[asset_path_url_prefix]')) {
    $f3->set('sysConfig[asset_path_url_prefix]', $f3->get('sysConfig[webroot_url_prefix]') . '/asset');
}

\Core\Asset\SimpleManager::instance(
    $f3->get('sysConfig[asset_path_url_prefix]'),
    $f3->get('sysConfig[asset_path_root]')
);
// asset 文件 url 开启 hash，文件名采用 时间戳.文件名 的方式
\Core\Asset\SimpleManager::instance()->enableFileHashUrl(true, true);
\Core\Asset\ManagerHelper::setAssetManager(\Core\Asset\SimpleManager::instance());

// ---------------------------------------- 4. 加载显示主题 --------------------------------------

$themeIntance = ThemeHelper::loadSystemTheme(ThemeHelper::SYSTEM_SUPPLIER_THEME);
if (!$themeIntance) {
    die('没有正确设置 ' . ThemeHelper::SYSTEM_SUPPLIER_THEME . ' 主题');
}

// 调用主题自己的初始化方法
$themeLoadRet = $themeIntance->pluginLoad(PluginHelper::SYSTEM_SUPPLIER);
if (true !== $themeLoadRet) {
    die(ThemeHelper::SYSTEM_SUPPLIER_THEME . ' 主题无法初始化:' . $themeLoadRet);
}
// 调用主题的 action
$themeActionRet = $themeIntance->pluginAction(PluginHelper::SYSTEM_SUPPLIER);
if (true !== $themeActionRet) {
    die(ThemeHelper::SYSTEM_SUPPLIER_THEME . ' 主题无法加载:' . $themeActionRet);
}

// ---------------------------------------- 5. 加载系统插件 --------------------------------------

// 这里我们加载额外的插件
PluginHelper::loadActivePlugin(PluginHelper::SYSTEM_SUPPLIER);
// 执行插件的 action 方法，让插件能完成各种注册
PluginHelper::doActivePluginAction(PluginHelper::SYSTEM_SUPPLIER);


// ---------------------------------------- 6. 启动整个系统 --------------------------------------

// 启动控制器
$f3->run();

// 执行完成，卸载插件
PluginHelper::unloadActivePlugin(PluginHelper::SYSTEM_SUPPLIER);
// 执行完成，卸载主题
$themeIntance->pluginUnload(PluginHelper::SYSTEM_SUPPLIER);
