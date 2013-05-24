<?php
class WF_Header { 

    protected $fp;
    protected $host;
    protected $url;
    protected $header = array();
    protected $headerStream = '';
    protected $reqHeader = "";
    protected $params = array();
    protected $path_info;
    protected $end = "\r\n";


    /**
     * 行结束符 
     */
    const LINE_SEP = "\r\n";

    /**
     *  链接缩短服务URL最大长度
     */
    const SHORTENED_URL_MAXLEN = 25;

    public function __construct($url, $params = array(), $method = "get"){
        $path_info = $this->parseUrl($url);
        $port = 80;
        $this->fp = fsockopen($path_info['host'], $port);
        if ( !$this->fp ){
            throw new Exception("Not Stream Resource");    
        }
        return $this;
    }

    protected function getRequestHeader(){
        $isPost = $this->method == "post";
        $in[] = strtoupper($this->method) . " " . $this->path . $this->query . " HTTP/1.1";
        $in[] = "Host: " . $this->host  ;
        $in[] = "User-Agent: meixun.com API PHP5 Client 1.1 (non-curl)";
        $in[] = "Accept: */*";
        $in[] = "Connection: Close";
        if ($isPost) {
            $data = http_build_query($this->params);
            $in[] = "Content-type: application/x-www-form-urlencoded";
            $in[] = "Content-Length: " . strlen($data);
            $in[] = "";
            $in[] = $data; 
        }
        $this->reqHeader = join($this->end , $in) . str_repeat($this->end, 2);
    }

    public function getRawContent(  ){
        $this->method = "GET";
        $this->getRequestHeader();
        $fp = $this->fp;
        fputs($fp, $this->reqHeader);
        while(!feof($fp)) {
            $line = fgets($fp, 128);
            echo $line;
        }
        fclose($fp);
        $this->headerStream = $stream;
        return $this->header;

    }

    public function makeSocket($host, $timeout = 3, $port = 80){
        $fp = fsockopen($host, $port, $errno, $err_str, $timeout);      
        if ( !$fp ){
            if ( $errno == 0){
                throw new ServerNotFoundException($host);//todo define exception
            }
        }
        return $fp;
    }

    public static function setNoCache(){
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT"); 
        header("Cache-Control: no-cache, must-revalidate");
        header("Pragma: no-cache");  

    }
    public static function sendDownload($filename) {
        header("Content-type: $type");
        header("Content-Deposition: attachment; filename=$filename");
        header("Content-Description: $desc");
    }

    public static function httpMessages($code)
    {
        //[Informational 1xx]
        $httpMessages['0']='Unable to access';
        $httpMessages['100']='Continue';
        $httpMessages['101']='Switching Protocols';

        //[Successful 2xx]
        $httpMessages['200']='OK';
        $httpMessages['201']='Created';
        $httpMessages['202']='Accepted';
        $httpMessages['203']='Non-Authoritative Information';
        $httpMessages['204']='No Content';
        $httpMessages['205']='Reset Content';
        $httpMessages['206']='Partial Content';

        //[Redirection 3xx]
        $httpMessages['300']='Multiple Choices';
        $httpMessages['301']='Moved Permanently';
        $httpMessages['302']='Found';
        $httpMessages['303']='See Other';
        $httpMessages['304']='Not Modified';
        $httpMessages['305']='Use Proxy';
        $httpMessages['306']='(Unused)';
        $httpMessages['307']='Temporary Redirect';

        //[Client Error 4xx]
        $httpMessages['400']='Bad Request';
        $httpMessages['401']='Unauthorized';
        $httpMessages['402']='Payment Required';
        $httpMessages['403']='Forbidden';
        $httpMessages['404']='Not Found';
        $httpMessages['405']='Method Not Allowed';
        $httpMessages['406']='Not Acceptable';
        $httpMessages['407']='Proxy Authentication Required';
        $httpMessages['408']='Request Timeout';
        $httpMessages['409']='Conflict';
        $httpMessages['410']='Gone';
        $httpMessages['411']='Length Required';
        $httpMessages['412']='Precondition Failed';
        $httpMessages['413']='Request Entity Too Large';
        $httpMessages['414']='Request-URI Too Long';
        $httpMessages['415']='Unsupported Media Type';
        $httpMessages['416']='Requested Range Not Satisfiable';
        $httpMessages['417']='Expectation Failed';

        //[Server Error 5xx]
        $httpMessages['500']='Internal Server Error';
        $httpMessages['501']='Not Implemented';
        $httpMessages['502']='Bad Gateway';
        $httpMessages['503']='Service Unavailable';
        $httpMessages['504']='Gateway Timeout';
        $httpMessages['505']='HTTP Version Not Supported';

        return $httpMessages[$code];
    }

    public static function sendHttpCode($code) {
        $msg = self::httpMessage($code);
        header("HTTP/1.1 $code $msg");
    }

    public static function sendNotFound(){
        self::sendHttpCode(404);
    }

    public static function sendNotModified(){
        self::sendHttpCode(304);
    }
}
?>
