<?php
require_once __DIR__ . '/../../Loader.class.php';
WF_Loader::registerAutoload();
//WF_Daemon::runAsMainChildren(5, array('output'=>'/tmp/daemon2.log'));
echo "daemon\n";
//WF_Daemon::run();

$time1 = microtime(true);
$jobs = array(1, 3, 4, 2, 5);
$data = WF_Daemon::runJobs($jobs, 'test1', array('max'=>3));
var_dump($data);

$time2 = microtime(true);
var_dump($time2 - $time1);

function test(){
    sleep(rand(20, 30));
} 

function test1($job){
    if ($job > 3){
        sleep(3);
        return false;
    }
    sleep($job);
    return true;
}
//test();
?>
