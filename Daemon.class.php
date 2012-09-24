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
    private static $deliver_pid   = 0;
    private static $options = null;

    static public function daemonize($options = array()){
        global $stdin, $stdout, $stderr;
        set_time_limit(0);
        $default_options = array(
            'user'          => null,
            'output'        => '/dev/null',
        );
        $options = array_merge($default_options, $options);

        if (php_sapi_name() != "cli"){
            die("only run in command line mode\n");
        }

        umask(0);

        if (pcntl_fork() != 0){
            exit();
        }

        declare(ticks = 1);

        posix_setsid();

        if (pcntl_fork() != 0){
            exit();
        }

        chdir("/");

        if (!empty($options['user'])){
            //ie. www-data
            $result = self::setUser($options['user']);
            if (!$result) {
                die("cannot change user");
            }
        }


        //close file descriptior
        fclose(STDIN);
        fclose(STDOUT);
        fclose(STDERR);
        $output = $options['output'];
        $stdin  = fopen("/dev/null", 'r');
        $stdout = fopen($output, 'a');
        $stderr = fopen($output, 'a');
    }

    static public function runAsMainChildren($count=1, $options=array()){
        self::daemonize($options);
        $child = 0;
        while(true){
            $do_fork = 0;
            if ($chid < $count){
                $do_fork = 1;
                $pid = pcntl_fork();
            }
            if ($pid){
                if ($do_fork) {
                    $child ++;   
                }
                if ($child >= $count){
                    pcntl_wait($pid, $status);
                    $child -- ;
                }
            }
            elseif ($pid == 0){
                unset($child);
                return;
            }
            else {
                sleep(10);
            }
        }
        exit;
    }

    // 启动分发进程 
    static public function startDeliver($options = array()){
        $options = array_merge(self::$default_options, $options);
        self::$options = $options;
        self::$deliver_pid   = posix_getpid();
        self::$workers_count = 0;

        //创建工作进程
        self::createWorkers($options['max_workers']);
        if (self::isWorker()){
            return true;
        }

        sleep(1);
        //设置信号处理函数
        self::handleSignal(1);
        //设置时钟, 当工作进程过少而任务过多的时候，可以防止pcntl_wait阻塞
        //pcntl_alarm(self::$options['alarm_time']);

        while(true){
            sleep($options['restart_time']);
            // 如果从信号处理函数中返回，有可能处于worker进程，需要检查。
            if (self::isWorker()){
                //todo 这里有时间差，恢复默认信号可能需要提前
                return;
            }
            self::notifyBusy(self::$deliver_pid);
        }
    }

    // 向deliver进程发送HUP信号
    public static function notifyBusy($pid = 0) {
        $pid = $pid > 0 ? $pid : posix_getppid();
        posix_kill($pid, SIGUSR1);
    }

    //deliver进程是否存活
    static private function IsDeliverAlive(){
        return posix_getppid() != 1;
    }

    static private function isWorker(){
        return self::$deliver_pid == posix_getppid();
    }

    static private function findWorkersPid($pid){
        if (empty($pid) || $pid == 1) return array();
        exec("ps -ef |grep -v grep|awk '$3 == $pid{print $2}'", $output); 
        //exec 自己会产生一个进程
        return $output;
    }
    
    static private function handleSignal($isDeliver=0){
        if($isDeliver == 1){
            declare(ticks=1);
            //__log("signal xxx in parent".posix_getpid());
          //pcntl_signal(SIGALRM, array(__CLASS__, "signalHandler"));
            pcntl_signal(SIGCHLD, array(__CLASS__, "signalHandler"));
            pcntl_signal(SIGUSR1, array(__CLASS__, "signalHandler"));
          //pcntl_signal(SIGUSR2, array(__CLASS__, "signalHandler"));
            
            pcntl_signal(SIGHUP,  array(__CLASS__, "signalHandler"));
            pcntl_signal(SIGTERM, array(__CLASS__, "signalHandler"));
            pcntl_signal(SIGQUIT, array(__CLASS__, "signalHandler"));
        } else { 
            //__log("signal default in child".posix_getpid());
          //pcntl_signal(SIGALRM, SIG_IGN);
            pcntl_signal(SIGCHLD, SIG_IGN);
            pcntl_signal(SIGUSR1, SIG_DFL);
          //pcntl_signal(SIGUSR2, SIG_DFL);
            
            pcntl_signal(SIGTERM, SIG_DFL);
            pcntl_signal(SIGHUP,  SIG_DFL);
            pcntl_signal(SIGQUIT, SIG_DFL);
        }
    }

    static private function createWorkers($count = 1){
        for ( $i = 0; $i < $count; $i++ ) {
            $pid = pcntl_fork(); 
            if ($pid < 0){ 
                return false;
            } elseif($pid == 0) {
                self::handleSignal(0);
                if (self::$options['job_handler']){
                    $func = self::$options['job_handler']; 
                    echo "call func $func\n";
                    $func();
                    exit;
                }
                return false;
            } else {
                self::$workers_count ++;
            }
        }
        return true;
    }

    //信号处理函数， 只在父进程中执行
    static private function signalHandler($signo){
        //__log("singal trigger $signo");
        switch($signo){
            //case SIGALRM:
            //    pcntl_alarm(self::$options['alarm_time']);
            //    break;
            case SIGUSR2:
                //for test
                break;

            case SIGCHLD:
                while(($pid=pcntl_waitpid(-1, $status, WNOHANG)) > 0){
                        self::$workers_count --;
                }
                break;
            case SIGUSR1:
                $childs_now = count(self::findWorkersPid(self::$deliver_pid));
                if ($childs_now != self::$workers_count){
                    self::$workers_count = $childs_now;
                }
                $max = self::$options['max_workers'];
                $count = self::$workers_count <= 0 ? $max : $max - self::$workers_count;
                self::createWorkers($count);
                break;

            case SIGHUP:
            case SIGQUIT:
            case SIGTERM:
                    //__log("term begin");
                    $workers = self::findWorkersPid(self::$deliver_pid);
                    //__log($workers);
                    //__log("term finded workers");
                    foreach($workers as $worker) {
                        posix_kill($worker, 9);
                    }
                    //__log("term killed workers");
                    sleep(1);
                    exit;
            default:
                return false;
        }
    }


    static private function spawn($script = null){
        $pid = pcntl_fork();
        if ($pid < 0 ){
            return false;
        } else if ($pid > 0){
            usleep(500);
            exit();
        }
        return true;
    }

    /**
     * 设置用户ID和组ID 
     * 
     * @param string $name 
     * @return void
     */
    static private function setUser($name = 'www-data'){
        $result = false;
        $user = posix_getpwnam($name); 
        if ($user) {
            $uid = $user['uid'];
            $gid = $user['gid'];
            $result = posix_setuid($uid);
            posix_setgid($gid);
        }
        return $result;
    }

}
