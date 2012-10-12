<?php
require_once __DIR__ . '/../../Loader.class.php';
WF_Loader::registerAutoload();
//WF_Daemon::runAsMainChildren(5, array('output'=>'/tmp/daemon2.log'));
echo "daemon\n";
WF_Daemon::run();

function test(){
    sleep(rand(20, 30));
}
test();
?>
