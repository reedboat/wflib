<?php
class WF_View {
    private $begin_mark = '<{';
    private $end_mark   = '}>';
    private $_vars = array();
    private $view_dir = '';
    private $compile_dir = '/tmp';

    public function __construct($options = array())
    {
        if(isset($options['view_dir'])){
            $this->view_dir = $options['view_dir'];
        }
        if (isset($options['begin_mark'])){
            $this->begin_mark = $options['begin_mark'];
        }
        if (isset($options['end_mark'])){
            $this->end_mark = $options['end_mark'];
        }
        if (isset($options['compile_dir'])){
            $this->compile_dir = $options['compile_dir'];
            if (!is_writable($this->compile_dir)){
                die("no privilge to write in compile_dir\n");
            }
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
        $file = $this->view_dir . $file;
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

        ob_start();
        include $objfile;
        $content = ob_get_clean();

        return $content;
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
        $str = preg_replace_callback('/\$[a-z_]\w*(?:\.\w+)+/', array($this, "_array_point_expression_parse"), $str);
        $str = preg_replace('/' .$this->begin_mark. '\s*(?:echo\s*)?(\$[^ \s\(\)\.};]+)\s*;?\s*'.$this->end_mark.'/', '<?php echo $1; ?>', $str);
        $str = preg_replace('/'.$this->begin_mark.'\s*(?:echo\s*?)?([^ \t\r\n\(\)}]+)\(([^\t\r\n\(\)}@]+)\)(?:\s*;)?\s*'.$this->end_mark.'/', '<?php echo $1($2); ?>', $str);
        $str = str_replace( array( $this->begin_mark, $this->end_mark), array( '<?php ', ' ?>'), $str);
        return $str;
    }
}
?>
