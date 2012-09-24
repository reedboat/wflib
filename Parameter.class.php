<?php
class WF_Parameter {
    public function extract($data, $keys){
        $result = array();
        foreach($keys as $key){
            global $$key;
             $$key = isset($data[$key]) ? $data[$key] : null;
        }
    }

    public function fetch($data, $keys){
        $result = array();
        foreach($keys as $key){
            $result[] = isset($data[$key]) ? $data[$key] : null;
        }
        return $result;
    }
}
?>
