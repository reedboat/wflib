<?php
/**
 * WF_Logger
 * 
 * @package WF
 * @version $id$
 * @author zhangqm  <zhqm03@gmail.com> 
 */
class WF_Logger{

    const EMERG   = 'EMERG';   // Emergency: system is unusable
    const ERROR   = 'ERROR';     // Error: error conditions
    const WARN    = 'WARN';    // Warning: warning conditions
    const INFO    = 'INFO';    // Informational: informational messages
    const NOTICE  = 'NOTICE';
    const DEBUG   = 'DEBUG';   // Debug: debug messages
    const TRACE   = 'TRACE';   // Trace: debug messages on screen

    private $backend;
    private $logfile;
    private $logdir;
    private $seperator = "\t";

    private $priorities = array(
        self::EMERG  => true,
        self::ERROR  => true,
        self::WARN   => true,
        self::INFO   => true,
        self::NOTICE => false,
        self::DEBUG  => false,
        self::TRACE  => false,
    );

    public function __construct($clog=null){
        if (is_object($clog)){
            $this->backend = $clog;
        }
        elseif (is_string($clog)){
            if ($clog == 'stdout' || $clog == 'system'){
                $this->backend = $clog;
            }
            else if(is_dir($clog)){
                $this->backend = 'dir';
                $this->logdir = rtrim($clog, '/\\');
            }
            else {
                $this->backend = 'file';
                $this->logfile = $clog;
            }
        }
    }

    public function setPriorities($levels){
        $levels = (array)$levels;
        foreach($levels as $level){
            $this->priorities[strtoupper($level)] = true;
        }
    }

    public function disablePriority($levels){
        $levels = (array)$levels;
        foreach($levels as $level){
            $this->priorities[strtoupper($level)] = false;
        }
    }

    /**
     * setLevel 只有等于或者高于$level级别的日志才被记录
     */
    public function setLevel($level){
        $enable = true;
        $level  = strtoupper($level);
        foreach($this->priorities as $key => $value){
            $this->priorities[$key] = $enable;
            if ($key == $level){
                $enable = false;
            }
        }
    }

    /**
     * disable 禁用日志功能
     */
    public function disable(){
        foreach($this->priorities as $key => $value){
            $this->priorities[$key] = false;
        }
    }

    public function log($msg, $level){
        if (!isset($this->priorities[$level]) || !($this->priorities)){
            return;
        }

        $msg = $this->format($msg);
        if (is_object($this->backend)) {
            $this->backend->log($msg, $level);
            return;
        }

        $msg = date('c'). " $level $msg\n";

        if($this->backend == "file"){
            //todo buffer 处理 优化性能
            if ($this->logfile){
                error_log($msg, 3, $this->logfile);
            }
        }
        elseif ($this->backend == 'dir'){
            $file = $this->logdir . "/" . strtolower($level) . ".log";
            error_log($msg, 3, $file);
        }
        elseif ($this->backend == 'stdout'){
            echo $msg;
        }
        else if ($this->backend == 'system'){
            error_log($msg);
        }
    }

    public function debug($msg){
        $this->log($msg, self::DEBUG);
    }

    public function info($msg){
        $this->log($msg, self::INFO);
    }

    public function emerg($msg){
        $this->log($msg, self::EMERG);
    }

    public function error($msg){
        $this->log($msg, self::ERROR);
    }

    public function warn($msg){
        $this->log($msg, self::WARN);
    }

    /**
     * dump 直接将日志输出到显示屏或者页面, 使用TRACE级别
     * 
     * @param mixed $data 
     * @access public
     * @return void
     */
    public function dump($data, $trace=false){
        $this->trace($data);
        if (!$this->priorities[self::TRACE]) {
            return;
        }
        if (is_array($msg)){
            $msg = WF_Util::serialize($msg, ' ');
        }
        printf("%s %s %s", date("c"), self::TRACE, $msg);
        if ($trace){
            debug_print_backtrace();
        }
    }


    /**
     * trace 不仅输出日志，还输出调用栈信息
     */
    public function trace($msg){
        ob_start();
        debug_print_backtrace();
        $trace = ob_get_clean();
        $this->log($msg . "\n" . $trace, self::TRACE);
    }

    public function format($data){
        if (is_array($data)){
            return WF_Util::serialize($data);
        }
        return $data;
    }
}
?>
