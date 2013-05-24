<?php
class ValidateTest extends PHPUnit_Framework_TestCase {

    public function testParseRule(){
        $validator = new WF_Validate();
        $str = 'required  requiredif=key2|key3 list=,|int maxlength=10';
        $rules = array(
               'required',
               array('requiredif', 'key2|key3'),
               array('list', ',|int'),
               array('maxlength', 10),
            );
        $this->assertEquals($rules, $validator->parseRule($str));
    }

    public function testParseAllRules(){
        $validator = new WF_Validate();
        $rules = array(
            'key1' => 'required  requiredif=key2|key3 list=,|int maxlength=10'
        );
        $parsedRules = array(
            'key1'  => array(
               'required',
               array('requiredif', 'key2|key3'),
               array('list', ',|int'),
               array('maxlength', 10),
            )
        );
        $this->assertEquals($parsedRules, $validator->parseAllRules($rules));
    }

    public function testRequiredIf(){
        $validator = new WF_Validate();
        $if_key = 'key2';

        $data = array(
            'key1' => '',
            'key2' => '',
        );

        $result = $validator->requiredIf('key2', $if_key, $data);
        $this->assertTrue($result);
        $result = $validator->requiredIf('key3', $if_key, $data);
        $this->assertFalse($result);
    }

    public function testMutex(){
        $validator = new WF_Validate();
        $data = array(
            'key1' => '',
            'key2' => '',
        );


        $result = $validator->mutex('key3', 'key1', $data);
        $this->assertTrue($result);
        $result = $validator->mutex('key1', 'key2|key3', $data);
        $this->assertFalse($result);
        $result = $validator->mutex('key3', 'key1|key2', $data);
        $this->assertFalse($result);
    }

    public function testList(){
        $validator = new WF_Validate();
        $str='list=,|int';
        $value = '1,2,a';
        $rules = $validator->parseRule($str);
        $rule = $rules[0];

        $result = $validator->alist($value, $rule[1]);
        $this->assertFalse($result);
        $value = '1,2,30,450';
        $result = $validator->alist($value, $rule[1]);
        $this->assertTrue($result);
    }

    public function testChoieces(){
        $validator = new WF_Validate();
        $str = 'choice=1|2|3';
        $rules = $validator->parseRule($str);
        $rule = $rules[0];
        $value = 2;
        $result = $validator->{$rule[0]}($value, $rule[1]);
        $this->assertTrue($result);
        $value = 4;
        $result = $validator->{$rule[0]}($value, $rule[1]);
        $this->assertFalse($result);
    }

    public function testValidate(){
        $rules = array(
            'oe' => 'choice=utf-8|gbk|gb2312',
            'of' => 'choice=xml|json',
            'site' => 'required maxLength=8 minLength=2',
            'id' =>'mutex=ids int',
            'ids'=>'list=,|int',
        );
        $validator = new WF_Validate($rules);
        $result = $validator -> validate(array('site'=>'news', 'id'=>'10'));
        $this->assertTrue($result);
        $validator = new WF_Validate($rules);
        $result = $validator -> validate(array('site'=>'news', 'ids'=>'1,2,3', 'oe'=>'gbk', 'of'=>'xml'));
        $this->assertTrue($result);

        $validator = new WF_Validate($rules);
        $result = $validator -> validate(array('site'=>'news', 'id'=>'abc'));
        $this->assertFalse($result);
        $this->assertEquals(array('id'), array_keys($validator->getDetail()));

        $validator = new WF_Validate($rules);
        $result = $validator -> validate(array());
        $this->assertFalse($result);
        $this->assertEquals(array('site', 'id'), array_keys($validator->getDetail()));

    }

    public function testGetError(){
        $validator = new WF_Validate(array());

        $msg = $validator->msg(array('length', 3));
        $this->assertContains('3', $msg);

        $validator->error(array('choice', 'xml|html'), 'of');
        $msg = $validator->getDetail('of');
        $this->assertContains('in xml|html', $msg);

        $rules = array(
            'oe' => 'choice=utf-8|gbk|gb2312',
            'of' => 'choice=xml|json',
            'site' => 'required maxLength=8 minLength=2',
            'id' =>'mutex=ids int',
            'ids'=>'list=,|int',
        );
        $validator = new WF_Validate($rules);
        $result = $validator -> validate(array('of'=>'html', 'ids'=>'1,2,b', 'site'=>'', 'oe'=>'utf-8'));
        $this->assertFalse($result);
        $expected = array('site', 'ids', 'of');
        $actual   = array_keys($validator->getDetail());
        sort($expected);
        sort($actual);
        $this->assertEquals($expected, $actual);


        $msg = $validator->getDetail();
        $this->assertTrue(array_key_exists('of', $msg));
        $this->assertContains('in xml|json', $msg['of']);

        $msg = $validator->getDetail('ids');
        $this->assertContains('list', $msg);

        $msg = $validator->getMessage();
        $this->assertContains("Invalid Arguments", $msg);
        $this->assertContains('site', $msg);
        $this->assertContains('ids', $msg);
        $this->assertContains('of', $msg);

        $msg = $validator->getDetailString();
        $this->assertContains('site', $msg);
        $this->assertContains('ids', $msg);
        $this->assertContains('of', $msg);
        $this->assertContains('Param $site error', $msg);
    }
}
?>
