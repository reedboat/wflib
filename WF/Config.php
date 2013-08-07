<?php
class WF_Config {

    private static $_config = array();

    public static function get($key, $default = null)
    {
        if (preg_match('/[\.\/]/', $key) == false){
            return isset(self::$_config[$key]) ? self::$_config[$key] : $default;
        }
        else {
            $tree = preg_split('/[\.\/]/', $key);
            $config = self::$_config;
            foreach($tree as $current){
                if (isset($config[$current])){
                    $config = $config[$current];
                }
                else {
                    return $default;
                }
            }
            return $config;
        }
    }

    public static function set($key, $value=null) {
        if (is_array($key)){
            $config = $key;    
            self::$_config = array_merge(self::$_config, $config);
            return true;
        }
        elseif (is_string($key)) {
            self::$_config[$key] = $value;
            return true;
        }
        return false;
    }

    public static function init($config=array()){
        self::$_config = $config;
        return true;
    }

    public function append($config = array()){
        if (!is_array($config)){
            $config = array();
        }
        self::$_config = array_merge_recursive2(self::$_config, $config);
    }
}
function array_merge_recursive2($paArray1, $paArray2)
{
    if (!is_array($paArray1) or !is_array($paArray2)) { return $paArray2; }
    foreach ($paArray2 AS $sKey2 => $sValue2)
    {
        $paArray1[$sKey2] = array_merge_recursive2(@$paArray1[$sKey2], $sValue2);
    }
    return $paArray1;
}
