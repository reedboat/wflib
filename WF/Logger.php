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
    const NOTICE  = 'NOTICE';  // 记录一些非结构化的基本不是为统计准备的数据。
    const DEBUG   = 'DEBUG';   // Debug: debug messages
    const TRACE   = 'TRACE';   // Trace: debug messages on screen

    private $backend;
    private $logfile;
    private $logdir;
    private $seperator = " ";
    private $enable = true;

    /**
     * log_format 
     * 
     * 可供使用的格式串为
     * %datetime 标准的时间字串 Y-m-d H:i:s
     * %iso_datetime 2004-02-12T15:19:21+00:00
     * %micro_time 细致到微秒级
     * %ip  client_ip
     * %level 日志级别，大写
     * @var string
     * @access private
     */
    private $default_log_format = "%iso_datetime %level %label %msg\n";
    private $log_format = '';

    private $priorities = array(
        self::EMERG  => true,
        self::ERROR  => true,
        self::WARN   => true,
        self::INFO   => true,
        self::NOTICE => false,
        self::DEBUG  => false,
        self::TRACE  => false,
    );

    private static $settings = array();
    private static $instances = array();

    public static function getLogger($name='default')
    {
        if(isset(self::$instances[$name])){
            return self::$instances[$name];
        }
        $className = __CLASS__;
        $logger = new $className();
        $logger->label = $name;
        foreach(self::$settings as $key => $value){
            $logger->$key = $value;
        }
        self::$instances[$name] = $logger;
        return $logger;
    }

    public static function basicConfig($settings){
        self::$settings = array_merge(self::$settings, $settings);
    }


    public function __construct($clog=null){
        if (is_object($clog)){
            $this->backend = $clog;
        }
        elseif (is_string($clog)){
            $time = time();
            $replace = array(
                '%date%'  => date('Ymd', $time),
                '%year%'  => date('Y', $time),
                '%month%' => date('m', $time),
                '%day%'   => date('d', $time),
                '%hour%'  => date('H', $time),
            );
            $clog = strtr($clog, $replace);
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

    public function setSeperator($sep){
        $this->seperator = $sep;
    }

    public function setFormat($format){
        $this->log_format = $format;
    }

    private function getClientIp(){
        $client_ip = '0.0.0.0';
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            $client_ip = end(explode(',',$_SERVER['HTTP_X_FORWARDED_FOR']));
        }
        else if (isset($_SERVER['REMOTE_ADDR']))
        {
            $client_ip = $_SERVER['REMOTE_ADDR'];
        }
        return $client_ip;
    }

    private function getMcirotime(){
        list($msec, $sec) = explode(" ", microtime(false));
        return date("Y-m-d H:i:s", $sec) . $msec;
    }


    /**
     * enablePriorities 设定记录的级别
     * 
     * @param mixed $levels 
     * @access public
     * @return void
     */
    public function enableLevels($levels){
        $levels = (array)$levels;
        foreach($levels as $level){
            $this->priorities[strtoupper($level)] = true;
        }
    }

    /**
     * disablePriority 取消某些级别的日志
     * 
     * @param array $levels 
     */
    public function disableLevels($levels){
        $levels = (array)$levels;
        foreach($levels as $level){
            $this->priorities[strtoupper($level)] = false;
        }
    }

    public function getLevels($level = null){
        if ($level === null){
            return $this->priorities;
        }
        else if (isset($this->priorities[$level])){
           return $this->priorities[$level];
        }
        return null;
    }

    /**
     * setLevel 只有等于或者高于$level级别的日志才被记录
     */
    public function setPriority($level){
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
        $this->enable = false;
    }

    private function msg($msg, $level){
        $msg = $this->format($msg);
        if (!$this->log_format){
            return str_replace(array('%iso_datetime', '%level',  '%label', '%msg'), array(date('c'), $level, $this->label, $msg), $this->default_log_format);
        }
        else {
            $placeholder = array('%iso_datetime', '%level', '%msg', '%datetime', '%ip', '%micro_time', '%label');
            $data        = array(date('c'), $level, $msg, date("Y-m-d H:i:s"), $this->getClientIp(), $this->getMcirotime(), $this->label);
            return str_replace($placeholder, $data, $this->log_format);
        }
    }

    public function log($msg, $level){
        if (!$this->enable || !isset($this->priorities[$level]) || !($this->priorities[$level])){
            return;
        }

        if (is_object($this->backend)) {
            $msg = $this->format($msg);
            $this->backend->log($msg, $level);
            return;
        }

        $msg = $this->msg($msg, $level);

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

    public function emerg($msg){
        $this->log($msg, self::EMERG);
    }

    public function error($msg){
        $this->log($msg, self::ERROR);
    }

    public function warn($msg){
        $this->log($msg, self::WARN);
    }

    public function info($msg){
        $this->log($msg, self::INFO);
    }

    public function notice($msg){
        $this->log($msg, self::NOTICE);
    }

    public function debug($msg){
        $this->log($msg, self::DEBUG);
    }

    /**
     * dump 直接将日志输出到显示屏或者页面, 使用TRACE级别
     * 
     * @param mixed $data 
     * @access public
     * @return void
     */
    public function dump($data, $trace=false){
        //$this->trace($data);
        //if (!$this->priorities[self::TRACE]) {
            //return;
        //}
        echo $this->msg($data, self::TRACE);
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
        $msg = $this->format($msg);
        $this->log($msg . "\n" . $trace, self::TRACE);
    }

    public function format($data){
        if (is_array($data)){
            return WF_Util::serialize($data, $this->seperator);
        }
        return $data;
    }

    public function __set($key, $value)
    {
        switch($key){
        case 'priority':
            $this->setPriority($value);
            break;
        case 'format':
            $this->setFormat($value);
            break;
        case 'seperator':
            $this->setSeperator($value);
            break;
        case 'label':
            $this->label = $value;
            break;
        case 'dir':
            $this->backend = 'dir';
            $this->logdir = rtrim($value, '/\\');
            if (!is_dir($this->logdir)){
                $success=@mkdir($this->logdir, 0755, true);
                if (!$success){
                    throw new RuntimeException("Logger directory `" . $this->logdir . "` can't be created" );
                }
            }
            break;
        case 'file':
            $this->backend = 'file';
            $this->logfile = rtrim($value, '/\\');
            break;
        default:
            throw new InvalidArgumentException('Logger config error, please check key ' . $key);
        }
    }

}

?>
