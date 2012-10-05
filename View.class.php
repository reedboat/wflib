<?php
class WF_View {
    private $begin_mark = '<{';
    private $end_mark   = '}>';
    private $_vars = array();

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
        if (is_array($vars)){
            $this->assign($vars);
        }
        extract($this->_vars);

        $objfile = "/tmp/" . md5($file);
        if ( @filemtime($file) >= @filemtime( $objfile ) ){
            $content = file_get_contents($file);
            $content = $this->parse($conten);
            file_put_contents($objfile, $content);
        }
        include $objfile;
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
