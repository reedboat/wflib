<?php
/**
 * WF_Http Curl封装类。 
 *
 * 支持代码级别Hosts
 * 支持设置Cookie
 * 支持Json格式
 */
class WF_Http {

    private $params = array();
    private $hosts = array();
    private $server = '';

    private $http_code  = 200;
    private $curl_errno = 0;
    private $errmsg     = '';

    private $timeout = 0;
    private $method  = 'GET';

    public function __construct(){
        $this->ch = curl_init();
    }

    public function addParam($key, $value=null){
        if ($value === null && is_array($key)){
            $this->params = array_merge($this->params, $key);
        }
        else {
            $this->params[$key] = $value;
        }
    }

    public function getError(){
        return array(
            'http_code' => $this->http_code,  
            'curl_errno'=> $this->curl_errno,
        );
    }

    public function get($url, $params=array(), $options=array()){
        $options['method'] = 'GET';
        return $this->request($url, $params, $options);
    }

    public function getJSON($url, $params=array(), $options=array()){
        $result = false;
        $resp = $this->request($url, $params, $options);
        if ($resp){
            $result = json_decode($resp, true);
        }
        return $result;
    }

    public function post($url, $params=array(), $options=array()){
        $options['method'] = 'POST';
        return $this->request($url, $params, $options);
    }

    public function request($url, $params = array(), $options = array()){

        $ch = $this->ch;
        $params  = array_merge($this->params, (array)$params);
        $data    = http_build_query($params, '', '&');


        //GET or POST
        $method = isset($options['method']) 
            ? strtoupper($options['method']) : $this->method;
        if ($method == 'GET'){
            if ($data){
                $url .= (strpos($url, "?") === false ? "?" : "&") . $data;
            }
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }
        elseif ($method == "POST") {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data); 
            curl_setopt($ch, CURLOPT_POST, true);
        }

        $this->url = $url;
        $url_info = parse_url($url);
        $host = $url_info['host'];

        //指定访问特定的机器
        if (isset($options['machine'])){
            $ip = $options['machine'];
        }
        if (isset($this->hosts[$host])){
            $ip = $this->hosts[$host];
        }
        if ($ip){
            $url = str_replace($host, $this->hosts[$host], $url);
            $this->setHeader('host', $host);
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url); 

        $timeout = isset($options['timeout']) ? $options['timeout'] : $this->timeout;
        if ($timeout > 0 ){
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); 
        }

        $response = curl_exec($ch); 

        if ($response === false ){
            $this->curl_errno  = curl_errno($ch);
            $this->errmsg = curl_error($ch);
            return false;
        }

        $http_code = curl_getinfo($ch,CURLINFO_HTTP_CODE);
        if ($http_code < 200 || $http_code >= 300){
            $this->http_code = $http_code;
            $this->errmsg = $response;
            return false;
        }

        return $response;
    }

    public function setCookie($cookie){
        curl_setopt($this->ch, CURLOPT_COOKIE, $cookie);
    }

    //不等待服务端返回，可使用callback和流水号来记录处理结果
    public function send($url, $params=array()){
        $path_info = parse_url($url);
        $host = $path_info["host"];
        $port = $path_info["port"];
        $timeout = 1;
        $end  = "\r\n";
        $in[] = "POST " . $path. " HTTP/1.1";
        $in[] = "Host: " . $host  ;
        //$in[] = "User-Agent: " . self::$userAgent . "(no curl)";
        $in[] = "Accept: */*";
        $in[] = "Connection: Close";

        $data = http_build_query($params);
        $in[] = "Content-type: application/x-www-form-urlencoded";
        $in[] = "Content-Length: " . strlen($data);
        $in[] = "";
        $in[] = $data;
        $content = join($end , $in) . str_repeat($end, 2);
        $fp = fsockopen($host, $port, $errno, $err_str, $timeout);
        if (!$fp) {
            return false;
        }
        fputs($fp, $content);
        fclose($fp);
        return true;
    }

     public function setOption($key, $value){
         curl_setopt($this->ch, $key, $value);
     }

     public function setHeader($key, $value)
     {
         $pairs = is_array($key) ? $key : array($key=>$value);
         $headers = array();
         foreach($pairs as $key => $value){
             $headers[] = ucfirst($key) . ": " . $value;
         }
         curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
     }

     public function setHosts($hosts)
     {
         $this->hosts = $hosts;
     }

     public function reset(){
         $this->ch         = curl_init();
         $this->http_code  = 200;
         $this->curl_errno = 0;
     }
 
    public function fetchPage($url, $use_cache=false){
        $url_hash = md5($url);
        $cache_dir = '/tmp/curl/' . substr($url_hash, 0, 2);
        if (!is_dir($cache_dir)){
            mkdir($cache_dir, 0777, true);
        }
        $cache_file = $cache_dir . "/" . substr($url_hash, 2) . ".html";
        if ($use_cache && file_exists($cache_file)){
            return file_get_contents($cache_file);
        }
        $html = $this->get($url);
        if($html && $use_cache){
            file_put_contents($cache_file, $html);
        }
        return $html;
    }

    public function getUrl(){
        return $this->url;
    }
}
