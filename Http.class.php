<?php
class WF_Http {

    private $params = array();
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

    public function post($url, $params=array(), $options=array()){
        $options['method'] = 'POST';
        return $this->request($url, $params, $options);
    }

    public function request($url, $params = array(), $options = array()){

        $ch = $this->ch;
        $params  = array_merge($this->params, $params);
        $data    = http_build_query($params, '', '&');

        $method = isset($options['method']) 
            ? strtoupper($options['method']) : $this->method;
        if ($method == 'GET'){
            $url .= (strpos($url, "?") === false ? "?" : "&") . $data;
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        }
        elseif ($method == "POST") {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data); 
            curl_setopt($ch, CURLOPT_POST, true);
        }

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
}
