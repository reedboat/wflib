<?php
class WF_Validate {
    private $rules = array();
    private $errors = array();
    private $messages = array(
        'alnum'           => 'Please provide only alnum characters.',
        'alpha'           => 'Please provide only alphabetic characters.',
        'city'            => 'Please provide a valid city name.',
        'date'            => 'Please provide a valid date.',
        'dateISO'         => 'Please provide a valid ISO date.',
        'dateGreaterThan' => 'Please provide a date that is before {date}.',
        'email'           => 'Please provide a valid email address.',
        'equalTo'         => 'Please provide the same value again.',
        'float'           => 'Please provide a valid floating point number.',
        'length'          => 'Please provide {length} characters.',
        'max'             => 'Please provide a value less than or equal to {max}.',
        'maxLength'       => 'Please provide no more than {maxLength} characters.',
        'min'             => 'Please provide a value greater than or equal to {min}.',
        'minLength'       => 'Please provide no fewer than {minLength} characters.',
        'notEqualTo'      => 'Please provide a different value.',
        'numeric'         => 'Please provide numbers only.',
        'phone'           => 'Please provide a valid phone number.',
        'phrase'          => 'Please provide a valid phrase.',
        'required'        => 'This field is required.',
        'url'             => 'Please provide a valid URL.',
        'zip'             => 'Please provide a valid zip code.',
        'choice'          => 'Please provide a valid value in {choice}.',
        'list'            => 'Please provide a valid list value.',
        'callback'        => 'Please provide a valid value',
    );

    public function __construct($rules=array(), $msgs = null){
        $this->rules = $rules;
    }

    public function getMessage(){
        $keys = implode(', ', array_keys($this->errors));
        return "Invalid Arguments ($keys)";
    }

    public function getDetail($key=null){
        if ($key == null){
            $result = array();
            foreach($this->errors as $key => $error){
                $result[$key] = $this->msg($error['rule']);
            }
        }
        else {
            $result = '';
            if (isset($this->errors[$key])){
                $result = $this->msg($this->errors[$key]['rule']);
            }
        }
        return $result;
    }

    public function getDetailString(){
        $result = "";
        foreach($this->errors as $key => $error){
            $result.= "Param \$$key error: " . $this->msg($error['rule']) . " \n";
        }
        return $result;
    }

    public function getStructed(){
        return $this->errors;
    }

    /**
     * validate 核心验证方法
     * 
     * @param mixed $data 
     * @return boolean
     */
    public function validate($data){
        $this->clear();
        $this->rules = $this->parseAllRules($this->rules);
        $keys = array_diff_key($this->rules, $data);
        $valid = true;

        foreach($keys as $key => $rules){
            if (in_array('required', $rules)){
                if (!$this->required($key, $data)){
                    $valid = false;
                    $this->error('required', $key);
                    continue;
                }
            }

            foreach($rules as $rule){
                if (is_array($rule)) {
                    if ($rule[0] == 'requiredIf' || $rule[0] == 'mutex'){
                        if (!$this->{$rule[0]}($key, $rule[1], $data)){
                            $valid = false;
                            $this->error($rule, $key);
                            break;
                        }
                    }
                }
            }
        }

        foreach($data as $key => $value){
            $this->key = $key;
            $value     = trim($value);
            if (!isset($this->rules[$key])){
                continue;
            }
            $rules     = $this->rules[$key];
            foreach($rules as $rule){
                $result    = $this->singleValidate($value, $rule);
                if (!$result){
                    $this->error($rule, $key, $value);
                    $valid = false;
                    break;
                }
            }
        }

        return $valid;
    }

    public function clear(){
        $this->errors = array();
    }

    /**
     * error 设定错误
     */
    public function error($rule, $key, $value=null){
        $this->errors[$key] = array('rule'=>$rule, 'value'=>$value);
    }

    /**
     * msg 转换错误信息
     */
    public function msg($rule, $value=null){
        if (is_array($rule)){
            $name = $rule[0];
        }
        else {
            $name = $rule;
        }

        if (array_key_exists($name, $this->messages)){
            $msg = $this->messages[$name];

            if (is_array($rule)){
                $msg = str_replace('{' . $name . '}', $rule[1], $msg);
            }
            return $msg;
        }

        if (is_array($rule)){
            $rule = implode('=', $rule);
        }
        return "expected '$rule' but actual '$value'";
    }

    /**
     * singleValidate 
     * 
     * @param mixed $value 
     * @param mixed $rules 
     * @param mixed $func 
     * @return true
     */
    public function singleValidate($value, $rule, $func=null){
        if (is_array($rule)){
            $method = $rule[0];
            $params = array($value, $rule[1]);
        }
        else {
            $method = $rule;
            $params = array($value);
        }

        //部分方法为php关键字，使用前缀a处理
        if (in_array($method, array('list', 'array', 'boolean'))){
            $method = 'a' . ucfirst($method);
        }

        //外围已经检验过
        if (in_array($method, array('required', 'requiredIf', 'mutex'))){
            return true;
        }


        $result = call_user_func_array(array($this, $method), $params);
        return $result;
    }

    public function parseRule($ruleStr){
        $ruleStr = preg_replace('/\s{2,}/', ' ', $ruleStr);
        $rules = explode(' ', $ruleStr);

        foreach($rules as $k => $rule){
            if ($rule == '') continue;
            $tmp = explode('=', $rule);
            $rules[$k] = sizeof($tmp) == 1 ? $rule : $tmp;
        }
        return $rules;
    }

    public function parseAllRules($rulesMap){
        if (empty($rulesMap)) {
            throw new Exception("unprovided validation rules");
        }
        $result = array();
        foreach($rulesMap as $key => $ruleStr){
            $result[$key] = $this->parseRule($ruleStr);
        }
        return $result;
    }

    public function split($rule){
        return explode('|', $rule);
    }

    public function required($key, $data){
        return (array_key_exists($key, $data) && !empty($data[$key]));
    }

    public function requiredIf($key, $if_key, $data){
        if (array_key_exists($if_key, $data)){
            return array_key_exists($key, $data);
        }
        return true;
    }

    public function mutex($key, $mutex_keys, $data){
        $flag = 0;
        $mutex_keys = $this->split($mutex_keys);
        if (array_key_exists($key, $data)){
            $flag = 1;
        }
        foreach($mutex_keys as $key){
            if (array_key_exists($key, $data)){
                if ($flag == 1){
                    return false;
                }
                else {
                    $flag = 1;
                }
            }
        }
        return $flag == 1;
    }

    //rules

    private function alnum($value){
        return ctype_alnum($value);
    }

    private function alpha($value){
        return ctype_alpha($value);
    }

    /**
     * digit 是否为数字组成
     */
    public function digit($value){
        return ctype_digit($value);
    }


    public function choice($value, $choiceStr){
        $valid_choices = $this->split($choiceStr);
        return in_array($value, $valid_choices);
    }

    public function date($value){
        return strtotime($value) > 0;
    }

    //是否为日期时间格式
    public function datetime($value){
        return preg_match("/^[\d: \-\/]+$/", $value);
    }

    public function time($value){
    }

    public function email($value){
        //return preg_match('/[\w\.]+@[\w\.]{4,}/', $value);
        return filter_var($value, FILTER_VALIDATE_EMAIL);
    }

    public function length($value, $length){
        return mb_strlen($value) == $length;
    }

    public function max($value, $max){
        return $value <= $max;
    }

    public function maxLength($value, $max){
        return mb_strlen($value) <= $max;
    }

    public function min($value, $min){
        return $value >= $min;
    }

    public function minLength($value, $min){
        return mb_strlen($value) >= $min;
    }

    public function none($value){
        return true;
    }

    /**
     * numeric 是否是合法数字(含正负数、指数、小数)
     */
    public function numeric($value){
        return preg_match('/^[-+]?([0-9]*\.)?[0-9]+([eE][-+]?[0-9]+)?$/',$value);
    }


    public function int($value){
        return preg_match('/^[+-]?\d+$/', $value);
    }

    public function phone($value){
        return preg_match('/^[1-9\-\+]{3,18}$/', $value);
    }

    public function url($value){
        //return preg_match('/^https?:\/\/[A-Z0-9.-]+\.[A-Z]{2,4}/i', $value) ;
        return filter_var($value, FILTER_VALIDATE_URL);
    }

    public function ip($value){
        //return preg_match('/(\d{1,3}\.){3}\d{1,3}/', $value);
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    public function ipv6($value){
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

    public function aList($value, $param){
        list($seprator, $rule) = $this->split($param);
        $parts = explode($seprator, $value);
        if (!method_exists($this, $rule)){
            throw new Exception("unknow rule '$rule' for list validate");
        }
        foreach($parts as $part){
            if (!$this->$rule($part)){
                return false;
            }
        }
        return true;
    }

    public function regexp($value, $pattern){
        return preg_match($pattern, $value);
    }

    public function callback($value, $callback){
        return call_user_func($callback, $value);
    }

    public function aArray($value){
        return is_array($value);
    }

    public function aBoolean($value){
        //$value = strtolower($value);
        //return $value == 1 || $value == 'true' || $value == 'on' || $value == 'yes';
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function notempty($value){
        return $value !== '';
    }

    public function string($value){
        return is_string($value);
    }

    /**
     * vname 是否为合法的变量名，字母或者下划线开头，后跟字母、下划线或者数字
     */
    public function vname($value){
        return preg_match('/^[a-z_]\w*$/i', $value);
    }

    /**
     * chinese 是否全中文。只支持utf-8编码。
     */
    public function chinese($value){
        return preg_match("/^[\u4E00-\u9FA5]*$/u", $value);
    }

    /**
     * chinese_alnum  是否全中文或英文数字下划线
     */
    public function chinese_alnum($value){
        return preg_match("/^[\u4E00-\u9FA5\w]*$/u", $value);
    }

    /**
     * word 普通单词，不含特殊符号和空格
     */
    public function nospecial($value){
        $pattern = preg_quote("`~!@#$^&*()=|{}':;,[].<>/?~！@#￥……&*（）——|{}【】‘；：”“。，、？", '/');
        $result = preg_match("/[${pattern}]/u", $value);
        return ! $result;
    }
}
?>
