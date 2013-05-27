<?php
/**
 * WF_Hook 
 * 本类的方法，本身无特定意义. 不会执行实际的代码。 
 * 仅有当注册器中注册了方法的时候，才有意义 
 * 可以用来实现AOP编程等技巧。
 */
class WF_Hook {

    public function register($hook, $method){
    }

    public function log($msg, $level){
    }

    public function cache($key, $lifetime, $serialize){
    }

    public function cache_start(){
    }

    public function cache_end(){
    }

    public function access(){
    }


    public function before(){
    }
    public function around(){
    }
    public function after(){
    }

    private function hook($name, $method){
    }

    function __call($name, $args){
        switch($name){
        case 'info':
        case 'debug':
        case 'warn':
        case 'error':
        case 'trace':
            $msg = $args[0];
            return $this->log($msg, $name);
        }
    }
}
?>
