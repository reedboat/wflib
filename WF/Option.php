<?php
class WF_Option {
    public function __construct($options) {
        $this->data = $options;
    }

    public function filter($keys) {
        $result = array();
        foreach($keys as $key) {
            if (isset($this->data[$key])) {
                $result[$key] = $this->data[$key];
            }
        }
        return $result;
    }

    public function get($key, $default = null){
        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }
}
