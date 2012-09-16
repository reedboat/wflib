<?php
class WF_Config {

    private static $_config = array();

    public static function get($key, $default = null)
    {
        return isset(self::$_config[$key]) ? self::$_config[$key] : $default;
    }

    public static function set($key, $value=null)
    {
        if (is_array($key)){
            $config = $key;    
            self::$_config = array_merge(self::$_config, $config);
        }
        elseif (is_string($key))
        {
            self::$_config[$key] = $value;
            return true;
        }
        return false;
    }

    public static function init($config=array()){
        self::$_config = $config;
        return true;
    }
}
