<?php
abstract class WF_DbTestCase extends PHPUnit_Framework_TestCase
{
	protected $fixtures=false;
    protected $_records = array();
    protected $_rows = array();
    protected static $basePath = null;
    protected $db = null;
    protected $db_reg_key = 'db';
    protected $model = null;

	public function __get($name)
	{
		if(is_array($this->fixtures) && ($rows=$this->_rows($name))!==false)
			return $rows;
		else
			throw new Exception("Unknown property '$name' for class '".get_class($this)."'.");
	}

    public function __call($name,$params)
    {
        if(is_array($this->fixtures) && isset($params[0]) && ($record=$this->getRecord($name,$params[0]))!==false)
            return $record;
        else {
            throw new Exception("Unknown method '$name' for class '".get_class($this)."'.");
        }
    }

    public function getRows($name){
		if(isset($this->_rows[$name]))
			return $this->_rows[$name];
		else
			return false;
    }
	/**
	 * @param string $name the fixture name (the key value in {@link fixtures}).
	 * @param string $alias the alias of the fixture data row
	 * @return DBTable the DBTable instance corresponding to the specified alias in the named fixture.
	 * False is returned if there is no such fixture or the record cannot be found.
	 */
	public function getRecord($name,$alias)
	{
		if(isset($this->_records[$name][$alias]))
		{
			if(is_string($this->_records[$name][$alias]))
			{
				$row=$this->_rows[$name][$alias];
				$model=WF_Table::model($this->_records[$name][$alias]);
				$key=$model->primaryKey;
				if(is_string($key))
					$pk=$row[$key];
				else
				{
					foreach($key as $k)
						$pk[$k]=$row[$k];
				}
				$this->_records[$name][$alias]=$model->findByPk($pk);
			}
			return $this->_records[$name][$alias];
		}
		else
			return false;
	}

	/**
	 * Sets up the fixture before executing a test method.
	 * If you override this method, make sure the parent implementation is invoked.
	 * Otherwise, the database fixtures will not be managed properly.
	 */
	public function setUp()
	{
		parent::setUp();
        if(is_array($this->fixtures)){
            foreach($this->fixtures as $fixtureName=>$modelClass)
            {
                $tableName=WF_Table::model($modelClass)->tableName();
                $this->resetTable($tableName);
                $rows=$this->loadFixtures($modelClass, $tableName);
                if(is_array($rows) && is_string($fixtureName))
                {
                    $this->_rows[$fixtureName]=$rows;
                    if(isset($modelClass))
                    {
                        foreach(array_keys($rows) as $alias)
                            $this->_records[$fixtureName][$alias]=$modelClass;
                    }
                }
            }
        }
    }

    protected function setDb($db){
        $this->db = $db;
    }

    protected function getDb(){
        if ($this->db == null) {
            $this->db = WF_Registry::get($this->db_reg_key);
        }
        return $this->db;
    }

    public static function setBasePath($basePath){
        self::$basePath = $basePath;
    }

    protected function loadFixtures($modelClass, $tableName){
		$fileName=self::$basePath.DIRECTORY_SEPARATOR.$tableName.'.php';
		if(!is_file($fileName))
			return false;

		$rows=array();
        $data = require($fileName);
		foreach($data as $alias=>$row)
		{
            $model = new $modelClass;
            $model ->setAttributes($row);
            $model ->save();

            $primaryKey = $model->primaryKey;

            if(is_string($primaryKey) && !isset($row[$primaryKey])){
                $row[$primaryKey]=$model->getPrimaryKey();
            }
            $rows[$alias]=$row;
        }
        return $rows;
    }

    protected function resetTable($tableName){
        $db=$this->getDb();
        $db->execute('DELETE FROM '.$tableName);
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver == 'sqlite'){
            $sql = "delete from sqlite_sequence where name=" . $db->quote($tableName);
            $result = $db->execute($sql);
        }
        else if ($driver == 'mysql'){
            $db->execute("truncate table `$tableName`");
        }
    }
}
