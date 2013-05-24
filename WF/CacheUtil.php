<?php
class WF_CacheUtil {
    private $default_params = array();
    private $cache;
    private $logger = null;
    public  $enable = true;

    function __construct($cache) {
        $this->cache = $cache;
    }

    public function lock($mutex_key, $lifetime=10){
        $this->logger->debug("lock $mutex_key $lifetime");
        $data =  $this->cache->add($mutex_key, 1, $lifetime);
        return $data;
    }

    public function unlock($mutex_key) {
        $this->logger->debug("unlock $mutex_key");
        $this->cache->delete($mutex_key);
    }

    public function load($key, $lifetime, $func, $args=array(), $options = array()){
        $default_options = array(
            'serialize' => true,  // 是否需要序列化
            'model'     => false, // 是否需要生成Table对象
        );
        $options = array_merge($default_options, $options);

        $value_str = $this->cache->get($key);
        if ($value_str === false){

            $this->enable = false;
            $value = call_user_func_array($func, $args);
            $this->enable = true;

            if ($value !== false && $value !== null){
                if ($options['model']){
                    $value_str = json_encode($value->getAttributes());
                }
                elseif($options['serialize']){
                    $value_str = json_encode($value, true);
                }
                else {
                    $value_str = $value;
                }
                $this->cache->set($key, $value_str, $lifetime);
            }

            return $value;
        }

        if ($options['model']){
            $value = json_decode($value_str, true);
            return $options['model']::model()->populateRecord($value);
        }

        if ($options['serialize']){
            return json_decode($value_str, true);
        }

        return $value_str;
    }

    /**
     * mutexLoad 加锁后加载数据并设置缓存, 如果加锁失败则返回false
     * 
     * @param mixed $params 
     * @param mixed $func 
     * @param mixed $args 
     * @param array $options 
     * @access public
     * @return void
     */
    public function mutexLoad($params, $func, $args, $options=array()){
        extract($params);
        if ($this->lock($mutex_key, $mutex_lifetime)){

            //请求不成功，或者没有数据，则返回false
            //@todo，这种判断是否合理
            $ret = call_user_func_array($func, $args);
            if ($ret === null || $ret === false) {
                $func_name = WF_Util::getFuncName($func);
                $this->logger->warn("call func $func_name in mutexLoad failed");
                return false;
            }
            $value = array('data'=>$ret, 'expire'=>time() + $data_lifetime);
            $value_str = json_encode($value);
            $this->logger->debug("set $key $lifetime $value_str");
            $this->cache->set($key, $value_str, $lifetime);

            $this->unlock($mutex_key);
            return $value;
        }
        else {
            return false;
        }
    }

    public function twoLifeLoad($params, $func) {
        extract($params);
        $args = array_slice(func_get_args(), 2);
        $retry_count = 3;
        $now = time();
        while($retry_count > 0){
            $value_str = $this->cache->get($key);
            if ($value_str){
                $value = json_decode($value_str, true);
                //如数据为标记过期，返回
                if ($value['expire'] >= $now) {
                    $this->logger->debug("Get Data From Cache Success: $key");
                    break;
                }
                //如数据标记过期，则延长过期时间，其他请求仍可请求到旧数据,
                //本次请求则担负更新数据的任务
                //@todo, 如更新失败怎么办, 包括延长过期时间失败和获取新数据失败
                $value['expire'] += $data_lifetime;
                $this->logger->debug("延长数据时间 $key");
                $this->cache->set($key, json_encode($value), $lifetime);

                //本次请求更新数据，更新数据时候加锁
                //@todo 是否考虑更复杂的异常情况
                $this->logger->debug("mutex load&cache data when data expired: $key");
                $value = $this->mutexLoad($params, $func, $args);
                //如果数据
                if ($value !== false){
                    break;
                }

                $this->logger->debug("Wait for other request to update cache data:$key");
                $retry_count --;
                usleep($sleep_time * 1000000);
            }
            else {
                //如果缓存不存在，则本请求加锁后去数据库中请求数据，其他的请求需要等待
                $this->logger->debug("mutex load&cache data when data not exist: $key");
                $value = $this->mutexLoad($params, $func, $args);
                if ($value !== false){
                    break;
                }
                $this->logger->debug("Wait for other request to set cache data: $key");
                $retry_count --;
                usleep($sleep_time * 1000000);
            }
        }

        if ($value){
            return $value['data'];
        }

        return false;
    }
}

class WF_CacheProxy {
    private $obj;
    private $lifetime=0;
    private $key=null;
    private $strategy = 'simple';

    public function __construct($obj, $cache){
        $this->obj = $obj;
        $this->cache = $cache;
    }

    public function setParams($key, $lifetime = 0){
        $this->key = $key;
        $this->lifetime = $lifetime;
    }

    public function __call($name, $args){
        $util = new WF_CacheUtil($this->cache);
        return $cache->load($this->key, $this->lifetime, array($this->obj, $name), $args);
    }
}
?>
