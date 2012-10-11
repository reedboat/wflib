<?php
class WF_Parameter {
    const PARAM_ASSOC = 0;
    const PARAM_ARRAY = 1;

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
     * list($a, $b, $c) = $param->fetch2($data, array('a'=>1, 'b'=>2, 'c'=>3));
     * 还可以改变key值。
     * list($page, $size) = $param->fetch2($data, array('p'=>1, 'l'=>20));
     * 
     * @param mixed $data 原数组
     * @param mixed $keys 如果是普通数组，则直接获取这些keys。如果是关联数组，则当不存在的时候提供默认值
     * @param mixed $filter_func 过滤函数, 可使用intval等内置函数，也可使用自定义函数，方法等。
     * @access public
     * @return void
     */
    public function fetch($data, $keys, $filter_func=null, $type=self::PARAM_ASSOC){
        $result = array();
        foreach($keys as $key => $default){
            if (is_int($key)){
                $key = $default;
                $value = isset($data[$key]) ? $data[$key] : null;
            }
            else {
                $value = isset($data[$key]) ? $data[$key] : $default;
            }

            if (!is_null($filter_func) && isset($data[$key])) {
                $func = $filter_func;
                if(is_array($filter_func)){
                    //notice array_key_exists != isset
                    if(array_key_exists($key, $filter_func)){
                        $func = $filter_func[$key];
                    }
                    else { 
                        $func = $filter_func[0];
                    }
                }
                if (is_callable($func)){
                    $value = call_user_func($func, $value);
                }
            }

            $result[$key] = $value;
        }

        if ($type == self::PARAM_ARRAY){
            return array_values($result);
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
