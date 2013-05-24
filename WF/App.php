<?php
class WF_App {
    public function init(){
        //read config
        //init log
        //init route
        //init cache
        //init db
        $router = new WF_Router();
        $logger = new WF_Logger();
        $db     = new WF_Db();
        WF_Registry::set('router', $router');
    }

    public function readConfig(){
    }

    public function dispatch(){
    }
}
?>
