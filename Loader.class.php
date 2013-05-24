<?php
class WF_Loader{
    private static $classDirs = null;

    public static function registerAutoload(){
        self::$classDirs = array(dirname(__FILE__));
        return spl_autoload_register(array(__CLASS__, 'autoload'));
    }

    public static function autoload($className){
        if (substr($className, 0, 3) == 'WF_'){
            self::loadClass($className);
            return $className;
        }
        return false;
    }
    
    public static function loadClass($className, $dirs=null){
        if (class_exists($className) || interface_exists($className)){
            return $className;
        }
        if (empty($dirs)){
            $dirs = self::$classDirs;
        }

        $script  = str_replace("_", '/', $className);
        foreach($dirs as $dir){
            $dir = rtrim($dir, '/\\');
            $file = $dir . '/' . $script . ".class.php";
            if (file_exists($file)){
                require $file;
                if ( class_exists( $className ) ){
                    return $className;
                }
            }
        }

        throw new RuntimeException($className . " cannot be finded");
        return false;
    }
}
?>
