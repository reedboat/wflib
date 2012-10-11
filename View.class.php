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

        $objfile = "/tmp/tpl_" . md5($file);
        if ( !file_exists($objfile) || filemtime($file) >= filemtime( $objfile )){
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
