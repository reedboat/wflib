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
    const ERR     = 'ERR';     // Error: error conditions
    const WARN    = 'WARN';    // Warning: warning conditions
    const INFO    = 'INFO';    // Informational: informational messages
    const DEBUG   = 'DEBUG';   // Debug: debug messages
    const TRACE   = 'TRACE';   // Trace: debug messages on screen

    private $backend;
    private $logfile;

    public function __construct($clog=null){
        if (is_object($clog)){
            $this->backend = $clog;
        }
        elseif (is_string($clog)){
            $this->logfile = $clog;
        }
    }

    protected $_priorities = array(
        self::EMERG  => true,
        self::ERR    => true,
        self::WARN   => true,
        self::INFO   => true,
        self::DEBUG  => false,
        self::TRACE  => false,
    );

    public function log($msg, $level){
        if ($level == self::TRACE){
            echo date('r'). " Logger $level $msg\n";   
            return;
        }

        if ($this->backend == null){
            $msg = date('r'). "$level $msg";
            if ($this->logfile){
                error_log($msg, 3, $this->logfile);
            }
            else {
                error_log($msg);
            }
        }
        elseif ($this->backend == 'stdout'){
            echo (date('r'). " Logger $level $msg\n");
        }
        else {
            $this->backend->log($msg, $level);
        }
    }

    public function debug($msg){
        $this->log($msg, self::DEBUG);
    }

    public function info($msg){
        $this->log($msg, self::INFO);
    }

    public function emerg($msg){
        $this->log($msg, self::ERROR);
    }

    public function error($msg){
        $this->log($msg, self::ERROR);
    }

    public function warn($msg){
        $this->log($msg, self::WARN);
    }

    public function trace($msg){
        $this->log($msg, self::TRACE);
    }

    /**
     * format 将数组格式化成Tab分割的字符串序列
     * 
     * @param mixed $data 
     * @access public
     * @return void
     */
    public function format($data, $splitter = "\t"){
        if (is_string($data)) return $data;
        $arr = array();
        foreach($data as $key => $value){
            if (is_integer($key)){
                array_push($arr, $value);
            }
            else {
                array_push($arr, strtoupper($key));
                if (strval($value) === ''){
                    $value = '-';
                }
                array_push($arr, $value);
            }
        }
        return implode($splitter, $arr);
    }

    public function dump($data){
        if (!$this->_priorities[self::TRACE]) {
            return;
        }
        if (is_array($msg)){
            $msg = $this->format($msg, ' ');
        }
        $string = date('c') .  self::TRACE . ": {$msg}\n";
        echo $string;
    }
}
?>
