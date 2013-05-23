<?php
//condition, method|subdomain|secure
//tokens
//default
//requirements
//widcard
//submapper
#spec
#  name 
#  path 
#  values
#  requirements
#    regexp
#    wildchar
#    normal
#  condition
#    method
#
#  submapper

/**
 * WF_Route 
 * 
 * @package 
 * @version $id$
 * @copyright 1997-2005 The PHP Group
 * @author reedboat <zhqm03@gmail.com> 
 * @license PHP Version 5.0 {@link http://www.php.net/license/5_0.txt}
 */



/**
 * todo
 */
class WF_Router {
    private $definitions=array();
    private $tokens = array();
    private $basepath = '';
    private $method = array('GET', 'POST', 'PUT', 'DELETE');

    private $spec_default = array(
        'name'   => null,
        'path'   => '',
        'values' => array(),
        'conds'  => array(),
        'patterns' => array(),
        '__type' => 'single',
        'target' => null,
    );

    public function map($path, $target=null, $args=array())
    {
        $spec = $this->spec_default;
        $spec['path']= $path;

        if (is_string($target)){
            list($controller, $action) = explode('#', $target);
            $target = array('controller'=>$controller, 'action'=>$action);
        }
        $spec['target'] = $target;

        foreach($args as $key => $val){
            switch ($key){
            case 'method':
                $spec['conds']['method'] = strtoupper($val);
                break;
            case 'values':
            case 'patterns':
                $spec[$key] = $val;
                    break;
            default:
            }
        }

        $spec = $this->regexp($spec);
        

        if (isset($args['name'])){
            $this->definitions[$args['name']] = $spec;
        }
        else {
            $this->definitions[] = $spec;
        }
    }

    public function match($path = null, $method=null)
    {
        if ($path == null){
            $path = $_SERVER['REQUEST_URI'];
        }

        if ($method == null){
            if (isset($_POST['_method']) && ($_method = strtoupper($_POST['_method'])) && in_array($_method,array('PUT','DELETE'))){
                $method = $_method;
            }
            elseif(isset($_SERVER['REQUEST_METHOD'])){
                $method = $_SERVER['REQUEST_METHOD'];
            }
        }
        $method = strtoupper($method);

        foreach($this->definitions as $spec){
            $pattern = $spec['path'];
            $regexp  = $spec['regexp'];

            if (isset($spec['conds']['method'])){
                if ($method != $spec['conds']['method']){
                   continue; 
                }
            }

            $match   = preg_match($regexp, $path, $matches);
            if (!$match){
                continue;
            }

            $data = $spec['values'];
            foreach($matches as $key => $val){
                if (is_integer($key)){
                    continue;
                }

                $data[$key] = $val;
                if ($key == '__wildcard__'){
                    $rest = explode('/', $val);
                    while($tmp = array_splice($rest, 0, 2)){
                        if (count($tmp) == 2){
                            list($key2, $val2) = $tmp;
                            $data[$key2] = $val2;
                        }
                    }
                }
            }
            $spec['values'] = $data;
            return $spec;
        }
        return false;
    }

    private function regexp($spec)
    {
        $path = $spec['path'];
        if (substr($path, -2) == "/*"){
            $path = substr($path, 0, -2) . "/{:__wildcard__:.*}";
        }

        $find = "/\{:(.*?)(?::(.*?))?\}/";
        preg_match_all($find, $path, $matches, PREG_SET_ORDER);
        foreach($matches as $match){
            $whole = $match[0];
            $name  = $match[1];
            if (isset($match[2])){
                $spec['patterns'][$name] = $match[2];
                $path = str_replace($whole, "{:$name}", $path);
            }
            else if (!isset($spec['patterns'][$name])){
                $spec['patterns'][$name] = "[^/]+";
            }
        }

        $pattern = $path;
        $spec['patterns'] = (array) $spec['patterns'];
        $keys = array();
        $vals = array();
        foreach($spec['patterns'] as $name => $subpattern){
            $keys[] = "{:$name}";
            $vals[] = "(?P<$name>" . $subpattern . ")";
        }
        $pattern = str_replace($keys, $vals, $pattern);
        $spec['regexp'] = "#^{$pattern}$#";
        $spec['path'] = $path;


        return $spec;
    }

    public function getSpec($name = null){
        if (!$name) return $this->definitions;
        if (isset($this->definitions[$name])){
            return $this->definitions[$name];
        }
        return null;
    }

    public function url($name, array $data=null)
    {
        $spec = $this->getSpec($name);
        if(!$spec) return;

        $keys = array();
        $vals = array();
        if(!$data) return $spec['path'];

        foreach($data as $key=>$val){
            $keys[] = "{:$key}";
            $vals[] = urlencode($val);
        }
        return str_replace($keys, $vals, $spec['path']);
    }

    public function __sleep(){
        return array('definitions');
    }
}

class WF_Route {
    private $spec;

    public function __construct($spec){
        $this->spec = $spec;
    }
    public function run(){
    }
}
?>
