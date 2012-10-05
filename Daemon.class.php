<?php
/*
 * MXDaemon 后台脚本控制类
 *
 * MXDaemon使用方法:
 * 直接在后台代码前面加上 MXDaemon::daemonize();
 * 如果希望多个进程跑，则使用MXDeamon::startDeliver($options);
 * $options中可控制最大进程数、等待时间等。
 * 工作进程中，可通过长期空闲的时候自己退出，
 * 任务过多的时候向父进程发起HUP信号
 * 来控制合适的工作进程数。
 *
 * @author zhangqm <zhqm03@gmail.com>
 * @date 2012-03-08
 */
class WF_Daemon { 
    private static $default_options = array(
        'max_workers'  => 5,  //最大worker数量
        'restart_time' => 90, //全部wokers死亡后重启等待时间
//      'alarm_time'   => 90, //时钟信号时间
        'pid_file'     => null,
        'job_handler'  => null,
    );

    private static $workers_count = 0;
    private static $workers_max = 0;
    private static $workers_min = 0;
    private static $pid_file = null;
    private static $info_dir = "/tmp";
    private $job_handler     = null;
    private static $main_pid = 0;
    private static $options  = null;

    public function __construct($options){
    }

    static public function daemonize($options = array()){
        global $stdin, $stdout, $stderr;
        global $argv;

        set_time_limit(0);
        $default_options = array(
            'user'          => null,
            'output'        => '/dev/null',
        );
        $options = array_merge($default_options, $options);

        if (php_sapi_name() != "cli"){
            die("only run in command line mode\n");
        }

        self::$info_dir = '/tmp/daemon_process';
        self::$pid_file = self::$info_dir . "/" . substr(basename($argv[0]), 0, -4) . ".pid";

        self::checkPidfile();

        umask(0);

        if (pcntl_fork() != 0){
            exit();
        }

        posix_setsid();

        if (pcntl_fork() != 0){
            exit();
        }

        chdir("/");

        self::setUser($options['user']) or  die("cannot change owner");

        //close file descriptior
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);
        $output = $options['output'];
        $stdin  = fopen("/dev/null", 'r');
        $stdout = fopen($output, 'a');
        $stderr = fopen($output, 'a');

        self::createPidfile();
    }

    static public function runAsMainChildren($count=1, $options=array()){
        self::daemonize($options);
        self::$workers_count = 0;
        $status = 0;

        declare(ticks=1);
        pcntl_signal(SIGTERM, array(__CLASS__, "signalHandler")); // kill all workers if send kill to main process
        pcntl_signal(SIGCHLD, array(__CLASS__, "signalHandler")); 

        while(true){
            $pid = -1;
            if (self::$workers_count < $count){
                $pid = pcntl_fork();
            }

            if ($pid > 0){
                self::$workers_count ++;
            }
            elseif ($pid == 0){
                if(isset($options['job_handler'])){
                    call_user_func($options['job_handler']);
                }
                return;
            }
            else {
                sleep(1);
            }
        }
        self::mainQuit();
        exit(0);
    }

    static public function runAsAutoPool($workers_max=10, $options=array()){
        self::daemonize($options);
        $main_pid = posix_getpid();

        self::$workers_max   = $workers_max;
        self::$workers_min   = 2;
        self::$workers_count = 0;
        $status = 0;

        declare(ticks=1);
        pcntl_signal(SIGTERM, array(__CLASS__, "signalHandler")); // kill all workers if send kill to main process
        pcntl_signal(SIGCHLD, array(__CLASS__, "signalHandler")); // if worker die, minus children num
        pcntl_signal(SIGUSR1, array(__CLASS__, "signalHandler")); // if send signal usr1 means busy


        while(true){
            $pid = -1;
            if (self::$workers_count < self::$workers_min){
                $pid = pcntl_fork();
            }

            if ($pid > 0){
                self::$workers_count ++;
            }
            elseif ($pid == 0){
                if(isset($options['job_handler'])){
                    call_user_func($options['job_handler']);
                }
                return;
            }
            else {
                sleep(1);
                if (posix_getpid() != $main_pid){
                    _log("run busy $pid");
                    if(isset($options['job_handler'])){
                        call_user_func($options['job_handler']);
                    }
                    return;
                }
            }
        }
        exit(0);
    }


    // 向deliver进程发送HUP信号
    public static function notifyBusy($pid = 0) {
        $pid = $pid > 0 ? $pid : posix_getppid();
        if ($pid > 1){
            posix_kill($pid, SIGUSR1);
        }
    }

    
    //信号处理函数， 只在父进程中执行
    static private function signalHandler($signo){
        switch($signo){
            case SIGUSR1: //busy
                if (self::$workers_count < self::$workers_max){
                    $pid = pcntl_fork(); 
                    if ($pid > 0){ 
                        self::$workers_count ++;
                    }
                }
                break;

            case SIGCHLD:
                while(($pid=pcntl_waitpid(-1, $status, WNOHANG)) > 0){
                        self::$workers_count --;
                }
                break;
            case SIGTERM:
            case SIGHUP:
            case SIGQUIT:
                self::mainQuit();
                break;
            default:
                return false;
        }
    }


    /**
     * 设置用户ID和组ID 
     * 
     * @param string $name 
     * @return void
     */
    static private function setUser($name){
        $result = false;
        if (empty($name)){
            return true;
        }
        $user = posix_getpwnam($name); 
        if ($user) {
            $uid = $user['uid'];
            $gid = $user['gid'];
            $result = posix_setuid($uid);
            posix_setgid($gid);
        }
        return $result;
    }

    public function checkPidfile(){
        if (!file_exists(self::$pid_file)){
            return true;
        }
        $pid = file_get_contents(self::$pid_file);
        $pid = intval($pid);
        if (posix_kill($pid, 0)){
            echo "the daemon proces is running\n";
        }
        else {
            echo "the daemon proces end abnormally\n";
        }
        exit(1);
    }

    public function createPidfile(){
        if (!is_dir(self::$pid_file)){
            mkdir(self::$info_dir);
        }
        $fp = fopen(self::$pid_file, 'w');
        fwrite($fp, posix_getpid());
        fclose($fp);
        _log("create pid file " . self::$pid_file);
    }

    public function mainQuit(){
        if (file_exists(self::$pid_file)){
            unlink(self::$pid_file);
            _log("delete pid file " . self::$pid_file);
        }
        posix_kill(0, SIGKILL);
        exit(0);
    }
}

if (!function_exists('_log')){
    function _log($msg){
        printf("%s\t%d\t%d\t%s\n", date("c"), posix_getpid(), posix_getppid(), $msg);
    }
}
