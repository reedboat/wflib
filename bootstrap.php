<?php
define("endl", "\n");
setlocale(LC_ALL, 'zh_CN.UTF-8');
date_default_timezone_set("Asia/Chongqing");
echo date("c"), endl;
echo strftime("%c"), endl;
//
// // Choose domain 
//bindtextdomain($domain, $path);
//bind_textdomain_codeset($domain);
//textdomain($domain);

function _log($msg){
    if (defined('_DEBUG_')){
        $msg = sprintf("%s DEBUG %s\n", date("c"), $msg);
        error_log("/tmp/debug.log");
    }
}

function _str(){
    return call_user_func_array('sprintf', func_get_args());
}

require __DIR__ . '/Loader.class.php';
WF_Loader::registerAutoLoad();

/**
 * WF 常用功能的快捷操作类
 */
class WF {
    public function reg($key){
        return WF_Registry::get($key);
    }

    public function ini($key, $value=null){
        return WF_Config::get($key);
    }

    public function post($url, $params){
        $http = new WF_Http();
        return $http->request($url, $params, array('method'=>'post'));
    }

    public function log($msg, $level='INFO'){
        $logger = self::get('logger');
        $logger->log($msg, $level);
    }

    public function dump($msg){
        $logger = self::get('logger');
        $logger->dump($msg);
    }
    //fire
    //fetch
}
?>
