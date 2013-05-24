<?php
/**
 * 
 **/
class RouteTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->route = new WF_Route();
    }

    public function testUrl()
    {
        $r = $this->route;
        $r->add('/','home');
        $r->add('/{:c}/{:a}', 'default');

        $url = $r->url('home');
        $this->assertEquals("/", $url);
        $url = $r->url('default', array('c'=>'news', 'a'=>'list'));
        $this->assertEquals("/news/list", $url);
    }

    public function testMatchDefault(){
        $r = $this->route;
        $r->add("/error/{:action}/{:id:\d+}", array("values"=>array('controller'=>'error')));
        $data = $r->match('/error/edit/1');
        $this->assertTrue(!!$data);
        $this->assertEquals('error', $data['controller']);
    }

    public function testMatchRegexp(){
        $r = $this->route;
        $r->add("/error/{:action}/{:id:\d+}");

        $data = $r->match('/error/edit/1');
        $this->assertTrue(!!$data);
        $this->assertEquals(1, $data['id']);

        $data = $r->match('/error/edit/a');
        $this->assertFalse(!!$data);

        $r->add("/{:id}", array('patterns'=>array('id'=>'[0-9]+')));
        $data = $r->match('/123');
        $this->assertTrue(!!$data);
        $this->assertEquals(123, $data['id']);

        $data = $r->match('/1a');
        $this->assertFalse(!!$data);
    }

    public function testMatchWildchar(){
        $r = $this->route;
        $r->add("/*");
        $data = $r->match("/a/b");
        $this->assertTrue($data !== false);
        $this->assertEquals('b', $data['a']);

        $r = $this->route;
        $r->add("/*");
        $data = $r->match("/a");
        $this->assertTrue($data !== false);
    }

    public function testMatchConds(){
        $r = $this->route;
        $r->add("/error/{:action}/{:id}", array('method'=>'post'));
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $data = $r->match('/error/a/b');
        $this->assertTrue(!!$data);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $data = $r->match('/error/a/b');
        $this->assertFalse(!!$data);
    }
}
?>
