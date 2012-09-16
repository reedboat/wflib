<?php
class WF_Debug
{
    /**
     * 打开错误报告。 
     * 
     * @param int $level 
     * @return void
     */
    public function enable($level=1) {
        if ($level){
            ini_set("display_errors", E_ALL & ~E_NOTICE);
            error_reporting(1);
            return true;
        }
        error_reporting(0);
    }

    /**
     * 输出调试信息 
     * 
     * @param int $return 
     * @return void
     */
    public function output($return=0){
        static $output;
        if (!defined("DEBUG_MODE")){
            return ;
        }
        echo $output;
        $output = '';
        return;
    }


    public function debug() {
        static $output = '', $doc_root;
        if (!defined("DEBUG_MODE")){
            return ;
        }

        //todo 
        $args = func_get_args();
        if (!empty($args) && $args[0] === 'print') {
            $_output = $output;
            $output = '';
            return $_output;
        }

        // do not repeat the obvious (matter of taste)
        if (PHP_SAPI != 'cli'){
            if (!isset($doc_root)) {
                $doc_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
            }
        }

        $backtrace = debug_backtrace();
        // you may want not to htmlspecialchars here
        if (PHP_SAPI != 'cli') {
            $line = htmlspecialchars($backtrace[0]['line']);
            $file = htmlspecialchars(str_replace(array('\\', $doc_root), array('/', ''), $backtrace[0]['file']));
            $class = !empty($backtrace[1]['class']) ? htmlspecialchars($backtrace[1]['class']) . '::' : '';
            $function = !empty($backtrace[1]['function']) ? htmlspecialchars($backtrace[1]['function']) . '() ' : '';
            $output .= "<b>$class$function =&gt;$file #$line</b><pre>";
        }
        else {
            $line = $backtrace[0]['line'];
            $file = str_replace(array('\\', $doc_root), array('/', ''), $backtrace[0]['file']);
            $class = !empty($backtrace[1]['class']) ? $backtrace[1]['class'] . '::' : '';
            $function = !empty($backtrace[1]['function']) ? $backtrace[1]['function'] . '() ' : '';
            $output .= "$class$function =&gt;$file #$line";
        }
        ob_start();
        foreach ($args as $arg) {
            var_dump($arg);
        }
        $output .= htmlspecialchars(ob_get_contents(), ENT_COMPAT, 'UTF-8');
        ob_end_clean();
        if (PHP_SAPI != 'cli') {
            $output .= '</pre>';
        }
    }

    /*
     * 按行读取测试数据, 需要提供解析方法作为参数
     * 解析方法可以返回false，表示该行不解析或者解析失败
     * 可用于注释不必要的数据。
     * 也可以读取指定行的数据
     */
    public function loadData($file, $parser_func, $lineno = -1) {
        $ret = array();
        $lines = file($file);
        if ($lineno != -1){
            $lineno = (array) $lineno;
        }

        foreach($lines as $i => $line){
            if ($lineno != -1 && !in_array($i, $lineno)) {
                continue;
            }

            $line = rtrim($line);
            $data = call_user_func($parser_func, $line);
            if ($data !== false){
                $ret[] = $data;
            }
        }

        return $ret;
    }

    public function ansiBold($text){
        return self::ANSI_ESCAPE . "1m$text" . self::ANSI_ESCAPE . "0m";
    }

    public function ansiColor($text, $fgcolor="red", $bgcolor='white'){
        $colors = array("black","red","green","yellow","blue","purple","cyan","white");
        $fg_index_base = 30;
        $bg_index_base = 40;

        $fg_index = array_search($fgcolor, $colors);
        $bg_index = array_search($bgcolor, $colors);
        if ($fg_index !== false){
            $color_string = $fg_index + $fg_index_base;
        }
        if ($bg_index !== false){
            $color_string .= "," . ($bg_index + $bg_index_base);
        }

        return self::ANSI_ESCAPE . "${color_string}m$text" . self::ANSI_ESCAPE . "0m";
    }
}
