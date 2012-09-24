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

    public function fetch($data, $keys, $filter_func=null){
        $result = array();
        foreach($keys as $key){
            $result[$key] = isset($data[$key]) ? $data[$key] : null;
            if ($filter_func && is_string($result[$key])){
                $result[$key] = $filter_func($result[$key]);
            }
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
