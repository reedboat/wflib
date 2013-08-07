<?php
class WF_View {
    private $begin_mark = '<{';
    private $end_mark   = '}>';
    private $_vars = array();
    private $view_dir = '';
    private $compile_dir = '/tmp';
    private $layout_dir = "_layouts";
    private $helper_dir = "_helpers";
    private $partial_dir = "_partials";
    private $base_path = "";
    private $suffix = '.tpl.php';
    private $layout = null;
    private $called = false;

    public function __construct($options = array())
    {
        if(isset($options['view_dir'])){
            $this->view_dir = $options['view_dir'];
            if (!is_dir($this->view_dir)){
                throw new RuntimeException("view dir '".$this->view_dir . "' does not exist");
            }
        }
        if (isset($options['compile_dir'])){
            $this->compile_dir = $options['compile_dir'];
            if (!is_writable($this->compile_dir)){
                throw new RuntimeException("no privilge to write in compile_dir\n");
            }
        }
        $keys = array(
            'begin_mark', 'end_mark', 'suffix',
            'helper_dir', 'layout_dir', 'partial_dir',
            'base_path',
        );

        foreach($keys as $key){
            if (isset($options[$key])){
                $this->$key = $options[$key];
            }
        }

        if (isset($options['layout'])){
            $this->layout = $options['layout'];
        }
    }

    public function assign($key, $data = null)
    {
        if (is_array($key))
        {
            $this->_vars = array_merge($this->_vars, $key);
        }
        else
        {
            $this->_vars[$key] = $data;
        }
        return $this;
    }

    public function render($file, $vars=null){

        ob_start();
        $this->display($file, $vars=null);
        $content = ob_get_clean();

        return $content;
    }

    public function partial($name)
    {
        $this->display($this->partial_dir . '/' . $name);
    }

    private function getScript($name){
        if(strpos($name, '.') === false){
            $name .= $this->suffix;
        }
        return $this->view_dir . '/' . ltrim($name, '/\\');
    }

    public function display($file, $vars = null){
        $use_layout = false;
        if (!$this->called){
            if ($this->layout){
                $use_layout = true;
            }
            $this->called = true;
        }

        $file = $this->getScript($file);
        if (is_array($vars)){
            $this->assign($vars);
        }
        extract($this->_vars);

        $objfile = $this->compile_dir . "/tpl_" . md5($file);
        if ( !file_exists($objfile) || filemtime($file) >= filemtime($objfile)){
            $content = file_get_contents($file);
            $content = $this->parse($content);
            file_put_contents($objfile, $content);
        }

        if ($use_layout){
            ob_start();
            include($objfile);
            $this->assign('content_for_layout', ob_get_clean());
            $this->display($this->layout_dir . '/' . $this->layout);
        }
        else {
            include($objfile);
        }
    }

    protected function _array_point_expression_parse($match){
        $data = explode('.', $match[0]);
        $result = array_shift($data);
        foreach($data as $item){
            $key = preg_match('/^\d+$/', $item) ? $item : "'$item'";
            $result .= "[$key]";
        }
        return $result;
    }

    public function parse($str){
        //todo 解决js中出现$的问题
        $str = preg_replace_callback('/\$[a-z_]\w*(?:\.\w+)+/', array($this, "_array_point_expression_parse"), $str);
        $str = preg_replace('/'.$this->begin_mark.'\s*=(.*?)' . $this->end_mark . '/', "<?php echo $1;?>", $str);

        //todo 以下2行可以考虑去掉了, 这样有echo的地方必须出现=
        $str = preg_replace('/'.$this->begin_mark. '\s*(?:echo\s*)?(\$[^ \s\(\)\.};=\+\-]+)\s*;?\s*'.$this->end_mark.'/', '<?php echo $1; ?>', $str);
        $str = preg_replace('/'.$this->begin_mark.'\s*(?:echo\s*?)?([^ \t\r\n\(\)}]+)\(([^\t\r\n\(\)}@]+)\)(?:\s*;)?\s*'.$this->end_mark.'/', '<?php echo $1($2); ?>', $str);
        $str = str_replace( array( $this->begin_mark, $this->end_mark), array( '<?php ', ' ?>'), $str);
        return $str;
    }

    public function setLayout($layout){
        $this->layout = $layout;
    }

    public function get($key, $default){
        if (isset($this->_vars[$key])){
            return $this->_vars[$key];
        }
        return $default;
    }

    public function __call($method, $args){
        $real_func = 'view_helper_' . $method;
        if (!function_exists($real_func)){
            $helper_file = $this->view_dir . '/' . $this->helper_dir . '/' . $method . ".php";
            if (file_exists($helper_file)){
                include($helper_file);
            }
        }
        if (function_exists($real_func)){
            return call_user_func_array($real_func, $args);
        }
        throw new BadMethodCallException("helper method '$method' does not existed");
    }
}
?>
