<?php
class WF_Parameter {
    const FETCH_ASSOC = 0;
    const FETCH_ARRAY = 1;

    public function extract($data, $keys, $filter_func=null){
        $result = $this->fetch($data, $keys, $filter_func);
        foreach($result as $key => $value){
            global $$key;
            $$key = $value;
        }
    }

    /**
     * fetch 获取data中指定key值的参数.
     *
     * 如果type指定self::PARAM_ARRAY 
     * 返回标量数组式的数据，这样可以使用下面的方式获得数据
     * list($a, $b, $c) = $param->fetch($data, array('a'=>1, 'b'=>2, 'c'=>3), 'trim', WF_Parameter::FETCH_ARRAY);
     * 还可以改变key值。
     * list($page, $size) = $param->fetch($data, array('p'=>1, 'l'=>20), null, WF_Parameter::FETCH_ARRAY);
     * 
     * @param mixed $data 原数组
     * @param mixed $keys 如果是普通数组，则直接获取这些keys。如果是关联数组，则当不存在的时候提供默认值
     * @param mixed $filter_func 过滤函数, 可使用intval等内置函数，也可使用自定义函数，方法等。
     * @access public
     * @return void
     */
    public function fetch($data, $keys, $filters=null, $type=self::FETCH_ASSOC){
        $result = array();
        foreach($keys as $key => $default){
            if (is_int($key)){
                $key = $default;
                $value = isset($data[$key]) ? $data[$key] : null;
            }
            else {
                $value = isset($data[$key]) ? $data[$key] : $default;
            }

            if (isset($data[$key])){
                $filter = $this->getFilter($key, $filters);
                $value  = $this->filter_var($value, $filter);
            }

            $result[$key] = $value;
        }

        if ($type == self::FETCH_ARRAY){
            return array_values($result);
        }

        return $result;
    }

    private function getFilter($key, $filters){
        $filter = null;
        if(is_array($filters)){
            if(array_key_exists($key, $filters)){
                $filter = $filters[$key];
            }
            else if(isset($filters[0])){ 
                $filter = $filters[0];
            }
        }
        else {
            $filter = $filters;
        }
        return $filter;
    }

    public function query($key, $default=null, $trim=true){
        $data = array_merge($_GET, $_POST);
        return $this->retrieve($data, $key, $default, $trim);
    }

    public function post($key, $default=null, $trim=true){
        return $this->retrieve($_POST, $key, $default, $trim);
    }

    public function retrieve($data, $key, $default=null, $filter="trim"){
        if (isset($data[$key])){
            return $this->filter_var($data[$key], $filter);
        }
        return $default;
    }

    public function get($key, $default=null, $trim=true){
        return $this->retrieve($_GET, $key, $default, $trim);
    }

    public function cookie($key, $default=null){
        return $this->retrieve($_COOKIE, $key, $default, $false);
    }

    public function filter_var($value, $filter, $options=null){
        if ($filter === null) return $value;
        if (is_callable($filter)){
            return call_user_func($filter, $value);
        }
        if (is_int($filter)){
            return filter_var($value, $filter, $options);
        }
        if ($filter == 'json_decode_array'){
            return json_decode($value, true);
        }
        if (substr($filter, 0, 11) == 'explode_by_'){
            $sep = substr($filter, 11);
            return explode($sep, $value);
        }
        return $value;
    }
}
