<?php
/**
 * WF_Render 
 * 
 * @package WF
 * @version $id$
 * @author weiye <zhqm03@gmail.com> 
 */

class WF_Render
{
    /**
     * 视图文件扩展名
     * 
     * @var string
     */
    protected $_extname;

    /**
     * 视图堆栈
     *
     * @var array
     */
    private $_stacks = array();

    /**
     * 当前处理的视图
     *
     * @var int
     */
    private $_current;

    /**
     * 视图变量
     *
     * @var array
     */
    protected $_vars;

    /**
     * 视图文件所在目录
     *
     * @var string
     */
    private $_view_dir;
    private $_layout_dir;
    private $_helper_dir;
    private $_control_dir;

    private $_compile_dir;

    //todo add vars
    private $begin_mark = '<{';
    private $end_mark   = '}>';

    /**
     * 构造函数
     */
    function __construct($view_dir)
    {
        $this->_view_dir = $view_dir;
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


    public function display($viewname = null, array $vars = null, array $config = null)
    {
        if (empty($viewname))
        {
            $viewname = $this->_viewname;
        }

        $charset = defined("CHARSET") ? constant('CHARSET') : 'UTF-8';
        header('Content-Type: text/html; charset=' . $charset);

        echo $this->fetch($viewname, $vars, $config);
    }

    public function fetch($viewname, array $vars = null, array $config = null){
        return $this->assign($vars)->_parse($filename);
    }

    public function getVar($key)
    {
        return isset($this->_vars[$key]) ? $this->_vars[$key] : null;
    }

    /**
     * 返回分析器使用的视图文件的扩展名
     *
     * @return string
     */
    function _extname($extname = null)
    {
        if ($extname){
            $this->_extname = $extname;
        }else {
            return $this->_extname;
        }
    }

    /**
     * 分析一个视图文件并返回结果
     *
     * @param string $filename
     * @param string $view_id
     * @param array $inherited_stack
     *
     * @return string
     */
    function _parse($filename, $view_id = null, array $inherited_stack = null)
    {
        if (!$view_id) $view_id = mt_rand();

        $stack = array(
            'id'            => $view_id,
            'contents'      => '',
            'extends'       => '',
            'blocks_stacks' => array(),
            'blocks'        => array(),
            'blocks_config' => array(),
            'nested_blocks' => array(),
        );
        array_push($this->_stacks, $stack);
        $this->_current = count($this->_stacks) - 1;
        unset($stack);

        ob_start();
        $this->_include($filename);
        $stack = $this->_stacks[$this->_current];
        $stack['contents'] = ob_get_clean();

        // 如果有继承视图，则用继承视图中定义的块内容替换当前视图的块内容
        if (is_array($inherited_stack))
        {
            foreach ($inherited_stack['blocks'] as $block_name => $contents)
            {
                if (isset($stack['blocks_config'][$block_name]))
                {
                    switch (strtolower($stack['blocks_config'][$block_name]))
                    {
                    case 'append':
                        $stack['blocks'][$block_name] .= $contents;
                        break;
                    case 'replace':
                    default:
                        $stack['blocks'][$block_name] = $contents;
                    }
                }
                else
                {
                    $stack['blocks'][$block_name] = $contents;
                }
            }
        }

        // 如果有嵌套 block，则替换内容
        while (list($child, $parent) = array_pop($stack['nested_blocks']))
        {
            $stack['blocks'][$parent] = str_replace("%block_contents_placeholder_{$child}_{$view_id}%",
                $stack['blocks'][$child], $stack['blocks'][$parent]);
            unset($stack['blocks'][$child]);
        }

        // 保存对当前视图堆栈的修改
        $this->_stacks[$this->_current] = $stack;

        if ($stack['extends'])
        {
            // 如果有当前视图是从某个视图继承的，则载入继承视图
            $filename = "{$this->_view_dir}/{$stack['extends']}.{$this->_extname}";
            return $this->_parse($filename, $view_id, $this->_stacks[$this->_current]);
        }
        else
        {
            // 最后一个视图一定是没有 extends 的
            $last = array_pop($this->_stacks);
            foreach ($last['blocks'] as $block_name => $contents)
            {
                $last['contents'] = str_replace("%block_contents_placeholder_{$block_name}_{$last['id']}%",
                    $contents, $last['contents']);
            }
            $this->_stacks = array();

            return $last['contents'];
        }
    }

    /**
     * 视图的继承
     *
     * @param string $tplname
     *
     * @access public
     */
    protected function _extends($tplname)
    {
        $this->_stacks[$this->_current]['extends'] = $tplname;
    }

    /**
     * 开始定义一个区块
     *
     * @param string $block_name
     * @param mixed $config
     *
     * @access public
     */
    protected function _block($block_name, $config = null)
    {
        $stack =& $this->_stacks[$this->_current];
        if (!empty($stack['blocks_stacks']))
        {
            // 如果存在嵌套的 block，则需要记录下嵌套的关系
            $last = $stack['blocks_stacks'][count($stack['blocks_stacks']) - 1];
            $stack['nested_blocks'][] = array($block_name, $last);
        }
        $this->_stacks[$this->_current]['blocks_config'][$block_name] = $config;
        array_push($stack['blocks_stacks'], $block_name);
        ob_start();
    }

    /**
     * 结束一个区块
     *
     * @access public
     */
    protected function _endblock()
    {
        $block_name = array_pop($this->_stacks[$this->_current]['blocks_stacks']);
        $this->_stacks[$this->_current]['blocks'][$block_name] = ob_get_clean();
        echo "%block_contents_placeholder_{$block_name}_{$this->_stacks[$this->_current]['id']}%";
    }

    /**
     * 构造一个控件
     *
     * @param string $control_type
     * @param string $id
     * @param array $args
     *
     *
     * @access public
     */
    protected function _control($control_type, $id = null, $args = array())
    {
        Q::control($control_type, $id, $args)->display();
        // TODO! display($this) 避免多次构造视图解析器实例
        // 由于视图解析器实例的继承问题，所以暂时无法利用
    }

    /**
     * 载入一个视图片段
     *
     * @param string $element_name
     * @param array $vars
     *
     * @access public
     */
    protected function _element($element_name, array $vars = null)
    {
        $filename = "{$this->_view_dir}/_elements/{$element_name}_element.{$this->_extname}";
        $this->_include($filename, $vars);
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

    /**
     * 解析模版 
     * 
     * @param text $str 
     * @return text
     */
    protected function parseContent($str){
        //将数组引用的圆点替换成中括号和双引号
        $str = preg_replace_callback('/\$[a-z_]\w*(?:\.\w+)+/', array($this, "_array_point_expression_parse"), $str);

        //将变量添加echo 
        $str = preg_replace('/' .$this->begin_mark. '\s*(?:echo\s*)?(\$[^ \s\(\)\.};]+)\s*;?\s*'.$this->end_mark.'/', '<?php echo $1; ?>', $str);
        //替换函数调用,添加echo
        $str = preg_replace('/'.$this->begin_mark.'\s*(?:echo\s*?)?([^ \t\r\n\(\)}]+)\(([^\t\r\n\(\)}@]+)\)(?:\s*;)?\s*'.$this->end_mark.'/', '<?php echo $1($2); ?>', $str);
        //替换开始结束符号
        $str = str_replace( array( $this->begin_mark, $this->end_mark), array( '<?php ', ' ?>'), $str);
        return $str;
    }
    
    private function _path($dir, $filename){
        switch($dir){
            case 'scripts':
            case 'layouts':
            case 'helper':
            case 'control':
                $dir = $this->_view_dir . '/' . $dir;
            default:
                $dir = rtrim($dir, '/\/');
        }
        if (strpos($filename, $this->_extname) === false){
            $filename .= '.' . $this->_extname;
        }
        return $dir . '/' . $filename;
    }


    /**
     * 载入视图文件
     */
    protected function _include($filename, array $vars = null)
    {
        if (substr($filename, 0, 1) !== '/'){
            $filename = $this->_path('scripts', $filename);
        }

        extract($this->_vars);
        if (is_array($vars)) extract($vars);
        $objfile = $this->_compile_dir . '/' . md5( $filename) . '.' . $this->_extname;
        if ( @filemtime($filename  ) >= @filemtime( $objfile ) ){
            $str = file_get_contents($filename);
            $str = $this->parseContent($str);
            $fh = fopen($objfile, "w+");
            fwrite($fh, $str);
            fclose($fh);
        }
        include $objfile;
    }

    public function __call($method, $params) {
        $this->_helper($method, $params);
    }

    protected function _helper($method, $params){
        $result = "";
        if (function_exists($method)){
            $result = call_user_func_array($method, $params);
        }
        else if (file_exists($helper_file)){
            $helper_file = $this->_path('plugins', $method . ".php");
             include_once $helper_file;
            $result = call_user_func_array($method, $params);
        } else {
            throw new RuntimeException("helper $method doesn't exist");
        }
        return $result;
    }
}

