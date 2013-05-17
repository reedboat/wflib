<?php
/**
 * Api接口包装类
 * 
 * @package TCMS
 * @version $id$
 * @author kufazhang <zhqm03@gmail.com>
 */
class WF_Api {

    
    /**
     * __construct 
     * 
     * @param mixed $name 
     * @param string $default_encoding 内部程序及数据所用编码
     */
    public function __construct($name, $default_encoding='utf-8')
    {
        $this->boot_time = microtime(true);
        $prj = $this->prj = new TProject($name);
        $method = strtolower($_SERVER['REQUEST_METHOD']);
        $data   = $method == 'get' ? $_GET : array_merge($_GET, $_POST);

        $of = isset($data['of']) ? $data['of'] : null;
        $of = strtolower($of)== 'xml' ? 'xml' : 'json';

        $oe = isset($data['oe']) ? strtolower($data['oe']) : null;
        $oe = $oe == 'utf8' ? 'utf-8' : $oe;
        $oe = in_array($oe, array('gbk', 'utf-8', 'gb2312')) ? $oe : $default_encoding;

        $ie = isset($data['ie']) ? strtolower($data['ie']) : $oe;
        $ie = $ie == 'utf8' ? 'utf-8' : $ie;
        $ie = in_array($ie, array('gbk', 'utf-8', 'gb2312')) ? $ie : $default_encoding;


        $prj->fw->interface->ie = $ie;
        $prj->fw->interface->oe = $oe;
        $prj->fw->interface->of = $of;
        $prj->fw->interface->de = $default_encoding;

        //deny xss 
        if (isset($data['callback']) and preg_match('/^\w+$/', $data['callback']))
        {
            $prj->fw->interface->callback = $data['callback'];
        }
    }

    /**
     * authenticate api鉴权方法
     */
    public function authenticate(){
        $data = $_SERVER['REQUEST_METHOD'] == 'GET' ? $_GET : $_POST;
        $type = $_SERVER['REQUEST_METHOD'] == 'GET' ? 'read' : 'write';
        $param = new WF_Parameter();
        $token = $param->query('token', '');
        $ts    = $param->query('ts', 0);
        $ua    = $param->query('ua', '');

        if(!WF_Config::get("auth.$type", false)){
            return true;
        }

        if (!$token || !$ts || !$ua){
            throw new LogicException('授权参数缺失', 403);
        }
        if (abs(time() - $ts) > 300){
            throw new LogicException('鉴权超时', 403);
        }

        $tokenizer = new Token();

        if(!$tokenizer->check($token, $ua, $ts, $data)){
            throw new LogicException('未授权的访问', 403);
        }
    }

    /**
     * validate 验证参数
     * 
     * @param mixed $rules 
     * @access public
     * @return void
     */
    public function validate($rules){
        $data = $_SERVER['REQUEST_METHOD'] == 'GET' ? $_GET : $_POST;
        $validator = new WF_Validate($rules);
        $result = $validator->validate($data);
        if (!$result){
            $msgs = $validator->getMessage();
            throw new InvalidArgumentException($msgs, 400);
        }
        return true;
    }

    /**
     * fetch 获取参数列表
     * 
     * @param mixed $keys 
     * @param mixed $filters 
     * @param mixed $type 
     * @access public
     * @return void
     */
    public function fetch($keys, $filters=null, $type=WF_Parameter::FETCH_ARRAY){
        $data = $_SERVER['REQUEST_METHOD'] == 'GET' ? $_GET : $_POST;
        $param = new WF_Parameter();
        if (!is_array($keys)){
            $result = $param->fetch($data, array($keys), $filters, $type);
            return array_pop($result);
        }
        return $param->fetch($data, $keys, $filters, $type);
    }

    /**
     * convert_input 输入转码
     * 
     * @param mixed $input 
     * @access public
     * @return void
     */
    public function convert_input($data){
        $prj = $this->prj;
        $de = $prj->fw->interface->de; 
        $ie = $prj->fw->interface->ie; 
        if ($de != $ie)
        {
            if (is_string($data))
            {
                $data = mb_convert_encoding($data, $de, $ie);
            }
            else if (is_array($data))
            {
                foreach($data as $key => $item)
                {
                    $data[$key] = $this->convert_input($item);
                } 
            }
        }
        return $data;
    }

    /**
     * convert_output 输出转码
     * 
     * @param mixed $prj 
     * @param mixed $output 
     * @access public
     * @return void
     */
    public function convert_output($data){
        $prj = $this->prj;
        $de = $prj->fw->interface->de; 
        $oe = $prj->fw->interface->oe; 
        if ($de != $oe)
        {
            if (is_string($data))
            {
                $data = mb_convert_encoding($data, $oe, $de);
            }
            else if (is_array($data))
            {
                foreach($data as $key => $item)
                {
                    $data[$key] = $this->convert_output($item);
                } 
            }
        }
        return $data;
    }

    /**
     * output 数据结果数据
     * 
     * @param mixed $data 
     * @access public
     * @return void
     */
    public function output($data){
        $prj = $this->prj;
        $cost = intval(1000 * (microtime(true) - $this->boot_time));
        //hack for convert error
        $prj->fw->interface->ie = $prj->fw->interface->oe; 
        $errno = 0;
        $message = 'sucess';
        $prj->fw->interface->out($errno, $message, $cost, $data);
        return true;
    }

    /**
     * error 输出错误数据
     * 
     * @param mixed $e 
     * @param mixed $errors 
     * @access public
     * @return void
     */
    public function error($e, $errors = null){
        $prj = $this->prj;
        $code = $e->getCode();
        if (is_array($errors)){
            if ($code > 0)
            {
                $msg = isset($errors[$code]) ? $errors[$code]:'';
            }
            else{
                $code = 99;
                $msg  = '未知异常';
            }
        }
        else
        {
            $code = $e->getCode() == 0 ? 1 : $e->getCode();
            $msg  = $e->getMessage();
        }
        $msg = $this->convert_output($msg);
        $cost = intval(1000 * (microtime(true) - $this->boot_time));
        //hack for convert error
        $prj->fw->interface->ie = $prj->fw->interface->oe; 
        $prj->fw->interface->out($code, $msg, $cost, '');
    }

    /**
     * getModel 获取数据,资源等
     * 
     * @param string $ext 
     * @access public
     * @return void
     */
    public function getModel($ext){
        return $this->prj->rs->data->ext[$ext];
    }

    /**
     * getResource 获取资源。
     * 可以直接获取db,cache等
     * 
     * @param mixed $key 
     * @access public
     * @return void
     */
    public function getResource($key){
        if (strpos($key, '.') === false){
            $rs_type = $key;
            $rs_key  = 'primary';
        }
        else {
            list($rs_type, $rs_key) = explode('.', $key);
        }
        return $this->prj->rs->$rs_type->$rs_key;
    }

    public function getProject(){
        return $this->prj;
    }

    public function log($msg, $level='INFO'){
        $this->prj->CLog->w($leve, $msg);
    }
}
