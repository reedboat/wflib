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
class WF_Route {
    private $definitions=array();
    private $tokens = array();

    private $spec_default = array(
        'name'   => null,
        'path'   => '',
        'values' => array(),
        'conds'  => array(),
        'patterns' => array(),
        '__type' => 'single',
    );

    public function add($path, $name=null, array $params = array())
    {
        if (is_array($name)){
            $params = $name;
            $name = null;
        }

        $spec = $this->spec_default;
        $spec['name']= $name;
        $spec['path']= $path;

        foreach($params as $key => $val){
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

        if ($name){
            $this->definitions[$name] = $spec;
        }
        else {
            $this->definitions[$name] = $spec;
        }
    }

    public function match($path = null)
    {
        if ($path == null){
            $path = $_SERVER['REQUEST_URI'];
        }
        foreach($this->definitions as $spec){
            $pattern = $spec['path'];
            $regexp  = $spec['regexp'];

            if (isset($spec['conds']['method'])){
                $method = $spec['conds']['method'];
                if ($_SERVER['REQUEST_METHOD'] != $method){
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
            return $data;
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
                $spec['path'] = str_replace($whole, "{:$name}", $path);
            }
            else if (!isset($spec['patterns'][$name])){
                $spec['patterns'][$name] = "[^/]+";
            }
        }

        $pattern = $spec['path'];

        $spec['patterns'] = (array) $spec['patterns'];
        $keys = array();
        $vals = array();
        foreach($spec['patterns'] as $name => $subpattern){
            $keys[] = "{:$name}";
            $vals[] = "(?P<$name>" . $subpattern . ")";
        }
        $pattern = str_replace($keys, $vals, $pattern);
        $spec['regexp'] = "#^{$pattern}$#";


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
?>
