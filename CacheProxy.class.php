<?php
class WF_CacheProxy {
    private $backend;
    private $target = null;
    private $enabled = true;
    private $key;
    private $lifetime;
    private $serialize;

    public function __construct($backend){
        $this->backend = $backend;
    }

    public function enable(){$this->enabled = true;}
    public function disable(){$this->enabled = false;}

    public function policy($key, $lifetime=3600, $serialize=false){
        $this->key = $key;
        $this->lifetime = intval($lifetime);
        $this->serialize = !! $serialize;
    }

    public function delegate($target, $policy = null){
        $this->target = $target;
    }

    public function __set($key, $val){
        switch($key){
        case 'key':
            if (!is_string($val)){
                throw new RuntimeException('provided an invalid cache key');
            }
            $this->$key = $val;
            break;
        case 'lifetime':
            if (is_int($val)){
                throw new RuntimeException('provided an invalid cache lifetime');
            }
            $this->$key = $val;
            break;
        case 'serialize':
            if (!is_bool($val)){
                throw new RuntimeException('provided an invalid serialize status');
            }
            $this->$key = $val;
            break;
        default:
            throw new RuntimeException('change property failed');
        }
        return false;
    }

    public function __call($name, $args){
        if ($this->enabled){
            if (is_null($this->key)){
                throw new RuntimeException('cache key is not assigned');
            }
            $data = $this->backend->get($this->key);
            if (!$data){
                $data = call_user_func_array(array($this->target, $name), $args);
                $data = $this->serialize ? json_encode($data) : $data;
                $this->backend->set($key, $data, $this->lifetime);
            }

            return $this->serialize ? json_decode($data, true) : $data;
        }

        return call_user_func_array(array($this->target, $name), $args);
    }
}
