<?php
/**
 * Event类，用来分离部分逻辑
 **/
class WF_Event implements ArrayAccess
{
    private $name; 
    private $data;

    private static $handlers;

    public static function bind($name, $callback){
        if (!isset(self::$handlers[$name])){
            self::$handlers[$name] = array();
        }
        self::$handlers[$name][] = $callback;
    }

    public static function fire($name, $data = array()) {
        $event = new WF_Event($name, $data);
        if (isset(self::$handlers[$name])){
            foreach(self::$handlers[$name] as $callback){
                try {
                    call_user_func($callback,$event);
                }
                catch(Exception $e){
                }
            }
        }
    }

    public static function unbind($name, $callback) {
        if (isset(self::$handlers[$name])) {
            $key = array_search(self::$handlers[$name], $callback);
            if ($key != -1){
                unset(self::$handlers[$name]);
                return true;
            }
        }
        return false;
    }

    public function __construct($name, $data)
    {
        $this->name = $name;
        $this->data = $data;
    }

    public function __get($key){
        if ($key == 'name'){
            return $this->$key;
        }
        elseif (isset($this->data[$key])){
            return $this->data[$key];
        }
        return null;
    }

    public function __set($key, $value){
        if ($key == 'name') return false;
        $this->data[$key] = $value;
    }

    public function offsetGet($key, $value){
        if ($key == 'name'){
            return $this->$key;
        }
        elseif (isset($this->data[$key])){
            return $this->data[$key];
        }
        return null;
    }

    public function offsetSet($key){
        if ($key == 'name') return false;
        $this->data[$key] = $value;
        return true;
    }

    public function offsetExists($key){
        if ($key == 'name') return false;
        return isset($this->data[$key]);
    }

    public function offsetUnset($key){
        unset($this->data[$key]);
    }

    public function data(){
        return $this->data; 
    }
}
?>
