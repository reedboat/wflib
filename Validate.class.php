<?php
class WF_Validate {
    private $rules = array();
    public $errors = array();
    private $messages = array(
        'alpha'           => 'Please provide only alphabetic characters.',
        'choices'         => 'Please provide a valid value.',
        'city'            => 'Please provide a valid city name.',
        'date'            => 'Please provide a valid date.',
        'dateISO'         => 'Please provide a valid ISO date.',
        'dateGreaterThan' => 'Please provide a date that is before {date}.',
        'email'           => 'Please provide a valid email address.',
        'equalTo'         => 'Please provide the same value again.',
        'float'           => 'Please provide a valid floating point number.',
        'length'          => 'Please provide {length} characters.',
        'max'             => 'Please provide a value less than or equal to {max}.',
        'maxLength'       => 'Please provide no more than {maxlength} characters.',
        'min'             => 'Please provide a value greater than or equal to {min}.',
        'minLength'       => 'Please provide no fewer than {minlength} characters.',
        'notEqualTo'      => 'Please provide a different value.',
        'numeric'         => 'Please provide numbers only.',
        'phone'           => 'Please provide a valid phone number.',
        'phrase'          => 'Please provide a valid phrase.',
        'required'        => 'This field is required.',
        'url'             => 'Please provide a valid URL.',
        'zip'             => 'Please provide a valid zip code.',
    );

    private $keyRules = array(
        'requiredif', 'mutex','required', 'equalTo', 'notEqualTo' 
    );

    public function __construct($rules=array(), $msgs = null){
        $this->rules = $rules;
    }

    public function getMessage(){
        $keys = implode(', ', array_keys($this->errors));
        return "Invalid Arguments ($keys)";
    }

    /**
     * validate 核心验证方法
     * 
     * @param mixed $data 
     * @access public
     * @return void
     */
    public function validate($data){
        $this->data = $data;
        $this->rules = $this->parseAllRules($this->rules);
        $keys = array_diff_key($this->rules, $this->data);

        foreach($keys as $key => $rules){
            if (in_array('required', $rules)){
                if (!$this->required($key, $data)){
                    $this->errors[$key] = $this->msg('required', $key);
                    continue;
                }
            }

            foreach($rules as $rule){
                if (is_array($rule)) {
                    if ($rule[0] == 'requiredIf' || $rule[0] == 'mutex'){
                        if (!$this->{$rule[0]}($key, $rule[1], $data)){
                            $this->errors[$key] = $this->msg($rule, $key);
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
            $result    = $this->singleValidate($value, $rules);
            if ($result !== true){
                $this->errors[$key] = $this->msg($result, $key, $value);
            }
        }

        return empty($this->errors);
    }

    public function msg($rule, $key, $value='null'){
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
           return "Param '$key' Error: " . $msg;
        }

        $rule = implode('=', $rule);
        return "Param '$key' Error: expected '$rule' but actual '$value'";
    }

    public function singleValidate($value, $rules, $func=null){
        foreach($rules as $rule){
            if (is_array($rule)){
                $method = $rule[0];
                $params = array($value, $rule[1]);
            }
            else {
                $method = $rule;
                $params = array($value);
            }

            if ($method == 'list'){
                $method = 'a' . ucfirst($method);
            }

            if (in_array($method, array('required', 'requiredIf', 'mutex'))){
                continue;
            }


            $result = call_user_func_array(array($this, $method), $params);
            if (!$result){
                return $rule;
            }
        }
        return true;
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

    private function alpha($value){
        return ctype_alpha($value);
    }


    public function choice($value, $choiceStr){
        $valid_choices = $this->split($choiceStr);
        return in_array($value, $valid_choices);
    }

    public function date($value){
        return strtotime($value) > 0;
    }

    public function datetime($value){
    }

    public function time($value){
    }

    public function email($value){
        return preg_match('/[\w\.]+@[\w\.]{4,}/', $value);
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
        return preg_match('/^https?:\/\/[A-Z0-9.-]+\.[A-Z]{2,4}/i', $value) ;
    }

    public function ip($value){
        return preg_match('/(\d{1,3}\.){3}\d{1,3}/', $value);
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

    public function aArray($value){
        return is_array($value);
    }

    public function aBoolean($value){
        $value = strtolower($value);
        return $value == 1 || $value == 'true' || $value == 'on' || $value == 'yes';
    }

    public function notempty($value){
        return $value !== '';
    }

    public function string($value){
        return is_string($value);
    }
}
?>
