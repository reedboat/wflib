<?php
/**
 * 注册表
 **/
class WF_Registry
{
    private static $data = array();

    private function __construct() {}

    public static function get($key, $default=null){
        if (self::has($key)){
            return self::$data[$key];
        }
        return $default;
    }

    public static function set($key, $value){
        self::$data[$key] = $value;
        return true;
    }

    public static function has($key){
        return isset(self::$data[$key]);
    }

    public static function del($key){
        unset(self::$data[$key]);
        return true;
    }

    public static function registry($key, $value){
        return self::set($key, $value);
    }

    public static function isRegistered($key){
        return self::has($key);
    }

    public function instance($key, $className){
        $obj = self::get($key);

        if ($obj == null){
            $args = func_get_args();
            array_shift($args);
            array_shift($args);
            $rc  = new ReflectionClass($className);
            $obj = $rc->newInstanceArgs($args);
            self::set($key, $obj);
        }
        return $obj;
    }
}
?>
