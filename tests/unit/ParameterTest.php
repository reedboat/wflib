<?php
class ParameterTest extends PHPUnit_Framework_TestCase {

    public function testExtract(){
        global $a, $b, $c, $d;
        $get = array(
            'a' => '1',
            'b' => '2 ',
            'c' => array(3,4,5),
            'd' => ' 6',
        );
        $model = new WF_Parameter();
        $model->extract($get, array('a', 'b', 'c', 'e'));
        $this->assertEquals('1', $a);
        $this->assertEquals('2 ', $b);
        $this->assertEquals(null, $d);

        $model->extract($get, array('a', 'b', 'c', 'd'), 'trim');
        $this->assertEquals('1', $a);
        $this->assertEquals('2', $b);
        $this->assertEquals(array(3, 4, 5), $c);
        $this->assertEquals('6', $d);
    }

    public function testFetch(){
        $get = array(
            'a' => '1',
            'b' => '2 ',
            'c' => array(3,4,5),
            'd' => ' 6',
        );
        $model = new WF_Parameter();
        $data = $model->fetch($get, array('a', 'b', 'c', 'e'), 'trim');
        $this->assertEquals(
            array(
               'a' => '1',
               'b' => '2',
               'c' => array(3, 4, 5),
               'e' => null
            ),
            $data
        );
    }
}
?>
