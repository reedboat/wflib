<?php
//namespace cn\weiye\wflib\tests;

use org\bovigo\vfs;
use org\bovigo\vfs\vfsStream;
class LoggerTest extends PHPUnit_Framework_TestCase {
    private $dirLogger  = null;
    private $root = null;

    public function setUp(){
        vfs\vfsStreamWrapper::register();
        $this->root = new vfs\vfsStreamDirectory('logdir');
        $dir = vfsStream::url('logdir/');
        mkdir($dir);
        $this->dirLogger = new WF_Logger($dir);
        vfs\vfsStreamWrapper::setRoot($this->root);
    }

    public function tearDown(){
    }

    public function testLog(){
        $logger = $this->dirLogger;
        $level = 'ERROR';
        $file = vfsStream::url("logdir/error.log");
        $this->assertFalse(file_exists($file));

        $msg = "test";
        $logger->log($msg, $level);
        $data = file($file);
        $log  = array_pop($data);
        $msg  = date("c") . " ".strtoupper($level)." " . $logger->format($msg) . "\n";
        $this->assertEquals($msg, $log);

        $msg = array('a'=>'b', 'c');
        $logger->log($msg, $level);
        $data = file($file);
        $log  = array_pop($data);
        $msg  = date("c") . " ".strtoupper($level)." " . $logger->format($msg) . "\n";
        $this->assertEquals($msg, $log);
    }

    public function testLogLevel(){
        $this->dirLogger->setPriorites('TRACE');
        $levels = array(
            'info', 'warn', 'debug', 'error',
        );
        foreach($levels as $level){
            $msg = 'test';
            $this->dirLogger->$level($msg);
            $file = vfsStream::url("logdir/${level}.log");
            $data =file($file);
            $log  = array_pop($data);
            $msg  = date("c") . " ".strtoupper($level)." " . $msg . "\n";
            $this->assertEquals($msg, $log);
        }
    }

    public function testSetLevels(){
        $level = 'EMERG';
        $logger = $this->dirLogger;
        $logger->disableLevels($level);
        $result = $this->dirLogger->getLevels($level);
        $this->assertFalse($result);

        $this->dirLogger->enableLevels($level);
        $result = $this->dirLogger->getLevels($level);
        $this->assertTrue($result);

        $logger->disableLevels($level);
        $result = $logger->getLevels($level);

        $this->assertFalse($result);
        $logger->emerg('a');
        $file = vfsStream::url("logdir/emerg.log");
        $this->assertFalse(file_exists($file));
    }

    public function testSetPriorites(){
        $logger = $this->dirLogger;
        $logger->setPriorites('WARN');

        $level = 'info';
        $file = vfsStream::url("logdir/$level.log");
        $this->assertFalse(file_exists($file));
        $logger->$level('a');
        $this->assertFalse(file_exists($file));

        $level = 'debug';
        $file = vfsStream::url("logdir/$level.log");
        $this->assertFalse(file_exists($file));
        $logger->$level('a');
        $this->assertFalse(file_exists($file));

        $level = 'warn';
        $file = vfsStream::url("logdir/$level.log");
        $this->assertFalse(file_exists($file));
        $logger->$level('a');
        $this->assertTrue(file_exists($file));

        $level = 'error';
        $file = vfsStream::url("logdir/$level.log");
        $this->assertFalse(file_exists($file));
        $logger->$level('a');
        $this->assertTrue(file_exists($file));
    }

    public function testDisableLog(){
        $logger = $this->dirLogger;
        $logger->disable();

        $level = 'info';
        $file = vfsStream::url("logdir/$level.log");
        $this->assertFalse(file_exists($file));
        $logger->$level('a');
        $this->assertFalse(file_exists($file));

    }

    public function testFormat(){
        $logger = $this->dirLogger;
        $logger->setFormat("%micro_time, %ip, unittest, %msg");
        $level = 'notice';
        $logger->enableLevels(strtoupper($level));

        $file = vfsStream::url("logdir/$level.log");
        $this->assertFalse(file_exists($file));
        $logger->$level(array('a'=>'b'));
        $this->assertTrue(file_exists($file));
        $data = file_get_contents($file);
        $result = preg_match('/^[\d\-]+ [\d:]+\.\d+, [\d\.]+, unittest, .*/', $data);
        $this->assertTrue($result==1);
    }

    public function testTrace(){
        $logger = $this->dirLogger;
        $level = 'trace';
        $logger->enableLevels(strtoupper($level));
        $logger->$level('a');
        $file = vfsStream::url("logdir/$level.log");
        $this->assertTrue(file_exists($file));
        $content = file_get_contents($file);
        $lines = explode("\n", $content);
        $this->assertTrue(count($lines) > 10);
        $result = preg_match('/^[\d:T\+\-]+ TRACE a/', $lines[0]);
        $this->assertTrue($result==1);
    }
}
?>
