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
?>
