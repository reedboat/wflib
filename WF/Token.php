<?php
/**
 * 认证 * 生成token * 销毁token
 **/
class WF_Token
{
    private $flag = '';
    
    function __construct(argument)
    {
        // code...
    }

    /**
     * produce 
     * 
     * @param mixed $params 
     * @access public
     * @return void
     */
    public function produce($params)
    {
        return md5($label . $name . microtime() . rand(1, 1000));
    }

    /**
     * destroy 自动过期?
     * 
     * @param mixed $params 
     * @access public
     * @return void
     */
    public function destroy($params){
    }
}
?>
