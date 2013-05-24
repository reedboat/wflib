<?php
class WF_Loader{
    private static $classDirs = array();

    public static function registerAutoload(){
        self::addPath(dirname(__FILE__));
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
            $file = $dir . '/' . $script . ".php";
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

    static public function addPath($path){
        $pathes = (array)$path;
        foreach($pathes as $path) {
            array_unshift(self::$classDirs, $path);
        }
    }
}
?>
