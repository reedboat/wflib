<?php
class WF_Util{
    public static function getFuncName($callback){
        if (is_array($callback)){
            if(is_string($callback[0])){
                $name = $callback[0];
            }
            else if (is_object($callback[0])){
                $name = get_class($callback[0]);
            }
            $func = $name . "::" . $callback[1];
        }
        else {
            $func = $callback;
        }
        return $func;
    }

    public static function isSpider($ua){
        $spiders = array(
            'googlebot',
            'Mediapartners-Google', //Google Adsense
            'Baiduspider',
            'Sosospider',
            'Sogou web spider',
            'Gigabot',
            'YoudaoBot',
            'YodaoBot'
        );
        $pattern = '@(' . implode(')|(', $spiders) . ')@';
        return preg_match($pattern, $ua);
    }

    public static function array2xml($array,$encoding='utf8') {
        $xml = '<?xml version="1.0" encoding="'.$encoding.'"?>';
        $xml.= '<response>';
        $xml.= self::_array2xml($array);
        $xml.= "</response>";
        return $xml;
    }

    private static function _array2xml($array) {
        $xml = '';
        foreach($array as $key=>$val) {
            is_numeric($key)&&$key="item id=\"$key\"";
            $xml.="<$key>";
            $xml.=is_array($val)?self::_array2xml($val):htmlspecialchars($val);
            list($key,)=explode(' ',$key);
            $xml.="</$key>";
        }
        return $xml;
    }

    public function isIndexArray($arr){
        $keys = array_keys($arr);
        $count=count($keys);
        if ($count == 0) return true;

        if (!$keys[0] == 0 && $count - 1 != array_slice($keys, -1, 1)){
            return false;
        }

        for($i=0; $i < $count; $i++) {
            if ($keys[$i] != $i) {
                return false;
            }
        }

        return true;
    }

    public function getDataByKey($data, $key, $default=null){
        if (strpos($key, '/') === false){
            return isset($data[$key]) ? $data[$key] : $default;
        }
        else {
            $tree = explode('/', $key);
            foreach($tree as $node){
                if (isset($data[$node])){
                    $data = $data[$node];
                }
                else {
                    return $default;
                }
            }
            return $data;
        }
    }
}
?>
