<?php
class WF_Parameter {
    public function extract($data, $keys, $filter_func=null){
        $result = array();
        $tmp = '';
        foreach($keys as $key){
            global $$key;
             $tmp = isset($data[$key]) ? $data[$key] : null;
             if ($filter_func && is_string($tmp)){
                 $tmp = $filter_func($tmp);
             }
             $$key = $tmp;
        }
    }

    /**
     * fetch 
     * 
     * @param mixed $data 原数组
     * @param mixed $keys 如果是普通数组，则直接获取这些keys。如果是关联数组，则还会有改名处理
     * @param mixed $filter_func 过滤函数, 可使用intval等内置函数，也可使用自定义函数，方法等。
     * @access public
     * @return void
     */
    public function fetch($data, $keys, $filter_func=null){
        $result = array();
        $flag = array_values($keys) == $keys;
        foreach($keys as $key => $default){
            if ($flag){
                $key = $default;
                $value = isset($data[$key]) ? $data[$key] : null;
            }
            else {
                $value = isset($data[$key]) ? $data[$key] : $default;
            }

            if (!is_null($filter_func) && isset($data[$key]) && !is_array($data[$key])) {
                $value = call_user_func($filter_func, $value);
            }
            $result[$key] = $value;
        }
        return $result;
    }

    public function query($key, $default=null, $trim=true){
        $data = array_merge($_GET, $_POST);
        return $this->retrieve($data, $key, $default, $trim);
    }

    public function post($key, $default=null, $trim=true){
        return $this->retrieve($_POST, $key, $default, $trim);
    }

    public function retrieve($data, $key, $default=null, $trim=true){
        $value = isset($data[$key]) ? ($trim ? trim($data[$key]) : $data[$key]) : $default;
        return $value;
    }

    public function get($key, $default=null, $trim=true){
        return $this->retrieve($_GET, $key, $default, $trim);
    }

    public function cookie($key, $default=null){
        return $this->retrieve($_COOKIE, $key, $default, $false);
    }
}
?>
