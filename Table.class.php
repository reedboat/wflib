<?php

class WF_Table {
    private $db = null;
    private static $_models = array();

    protected $_attributes = array();
    protected $_pk;
    public $primaryKey = 'id';
    protected $_columns   = array();
    protected $_columnsDefault   = array();
    protected $_new = false;

    public function __construct($scenario='insert')
    {
        if ($scenario != 'insert')
        {
            return ;
        }

        if (!empty($this->_columnDefaults))
        {
            $this->_attributes = $this->_columnDefaults;
        }
        $this->_new = true;
        $this->init();
    }

    public function init()
    {
    }

    public function db()
    {
        if ($this->db == null)
        {
            $this->db = WF_Registry::get('db');
        }
        return $this->db;
    }

    public function setDb($db)
    {
        $this->db = $db;
    }

    public function setNewRecord($status)
    {
        $this->_new = !! $status;
    }

    public function instantiate($attributes)
    {
        $class=get_class($this);
        $model=new $class(null);
        return $model;
    }

    public function getPrimaryKey()
    {
        if(is_string($this->primaryKey))
            return $this->{$this->primaryKey};
        else if(is_array($this->primaryKey))
        {
            $values=array();
            foreach($this->primaryKey as $name)
                $values[$name]=$this->$name;
            return $values;
        }
        else
            return null;
    }

    public function __set($name, $value)
    {
        if(in_array($name, $this->_columns))
        {
            $this->_attributes[$name]=$value;
        }
    }

    public function __isset($name)
    {
        if(in_array($name, $this->_columns) && isset($this->_attributes[$name]))
        {
            return true;
        }
        return false;
    }

    public function __get($name)
    {
        if(in_array($name, $this->_columns) && isset($this->_attributes[$name]))
        {
            return $this->_attributes[$name];
        }
    }

    public static function model($className=__CLASS__)
    {
        if(isset(self::$_models[$className]))
            return self::$_models[$className];
        else
        {
            $model=self::$_models[$className]=new $className(null);
            return $model;
        }
    }

    public function isNewRecord()
    {
        return $this->_new;
    }

    public function delete($attributes = null){
        if ($attributes == null){
            $pk = $this->getPrimaryKey();
            if (!$pk) return false;
            if (!is_array($this->primaryKey)){
                $attributes = array($this->primaryKey => $pk);
            }
            else {
                $attributes = $pk;
            }
        }

        $sql = "DELETE FROM " . $this->tableName() . " WHERE ";
        $conditions = array();
        foreach($attributes as $col => $value)
        {
            $conditions[] = "$col=?";
        }
        $sql .= implode(" AND ", $conditions);
        $sql .= " LIMIT 1";

        $result = false;
        if ($this->beforeDelete()){
            $result = $this->db()->execute($sql, array_values($attributes));
            if ($result){
                $this->afterDelete();
            }
        }
        return $result;
    }

    public function save()
    {
        if (method_exists($this, 'beforeSave'))
        {
            $return = $this->beforeSave();
        }
        if (!$return) return false;

        if ($this->isNewRecord())
        {
            $sql = "insert into " . $this->tableName() . '(';
            $sql .= implode(',', array_keys($this->_attributes));
            $sql .= ') values (';
            $sql .= implode(',', array_pad(array(), count($this->_attributes), '?'));
            $sql .= ')';
            $result = $this->db()->execute($sql, array_values($this->_attributes));
            if ($result)
            {
                $id = $this->db()->lastInsertId();
                $primaryKey = $this->primaryKey;
                if (is_string($primaryKey))
                {
                    if (!isset($this->_attributes[$primaryKey]))
                    {
                        $this->_attributes[$primaryKey] = $id;
                    }
                }

                if ($this->_pk != null)
                {
                    $this->_pk = $this->getPrimaryKey();
                }
                $this->_new = false;

                if (method_exists($this, 'afterSave'))
                {
                    $return = $this->afterSave('insert');
                }
            }

            return $result;
        }
        else {
            $sql  = 'UPDATE ' . $this->tableName() . " SET ";
            $attrs = $this->_attributes;
            $primaryKey = $this->primaryKey;
            if (is_string($primaryKey))
            {
                $pk = array($attrs[$primaryKey]);
                unset($attrs[$primaryKey]);
            }
            else if (is_array($primaryKey))
            {
                $pk = array();
                foreach($primaryKey as $name)
                {
                    $pk[] = $attrs[$name];
                    unset($attrs[$name]);
                }
            }
            $sql .= implode('=?,', array_keys($attrs)) . '=? ';
            $sql .= "WHERE ";
            if (is_string($primaryKey))
            {
                $sql .= $this->primaryKey . ' = ?';
            }
            else if (is_array($primaryKey))
            {
                $conditions = array();
                foreach($primaryKey as $name)
                {
                    $conditions[] = "$name = ?";
                }
                $sql .= implode(" AND ", $conditions);
            }
            $result = $this->db()->execute($sql, array_merge(array_values($attrs), $pk));
            if ($result && method_exists($this, 'afterSave'))
            {
                $return = $this->afterSave('update');
            }
            return $result;
        }
    }

    public function findByPk($pk, $options=array())
    {
        if (is_array($this->primaryKey))
        {
            $key1 = $this->primaryKey[0];
            if (!isset($pk[$key1]))
            {
                $attrs = array_combine($this->primaryKey, $pk);
            }
            else {
                $attrs = $pk;
            }
            return $this->findByAttributes($attrs, $options);
        }
        $sql  = 'select * from ' . $this->tableName() . " WHERE {$this->primaryKey}=?";
        $stmt = $this->db()->query($sql, array($pk));
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($stmt){ $stmt->closeCursor(); }
        if (!isset($options['model']) || $options['model'] == true){
            return $this->populateRecord($data);
        }
        return $data;
    }

    public function findByAttributes($attributes, $options=array())
    {
        $sql = "SELECT * FROM " . $this->tableName() . " WHERE ";
        $conditions = array();
        foreach($attributes as $col => $value)
        {
            $conditions[] = "$col=?";
        }
        $sql .= implode(" AND ", $conditions);
        $sql .= ' LIMIT 1';
        $stmt = $this->db()->query($sql, array_values($attributes));
        $data = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if ($stmt){ $stmt->closeCursor(); }
        if (!isset($options['model']) || $options['model'] == true){
            return $this->populateRecord($data);
        }
        return $data;
    }

    public function findAllByAttributes($attributes, $options=array())
    {
        $sql = "SELECT * FROM " . $this->tableName() . " WHERE ";
        $conditions = array();
        foreach($attributes as $col => $value)
        {
            $conditions[] = "$col=?";
        }
        $sql.= implode(" AND ", $conditions);
        $stmt = $this->db()->query($sql, array_values($attributes));
        $data = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if ($stmt){ $stmt->closeCursor(); }
        if (!isset($options['model']) || $options['model'] == true){
            return $this->populateRecords($data);
        }
        return $data;
    }


    public function findAll($condition=array(), $options=array())
    {
        $sql = "select * from " .$this->tableName() . " ";
        if (isset($condition['condition']))
        {
            $sql .= " WHERE {$condition['condition']}";
        }
        if (isset($condition['order']))
        {
            $sql .=" ORDER BY {$condition['order']}"; 
        }
        if (isset($condition['limit']))
        {
            $sql .=" LIMIT {$condition['limit']}";
        }
        $params = isset($condition['params']) ? $condition['params'] : array();
        $stmt = $this->db()->query($sql, $params);
        $data = $stmt? $stmt->fetchAll(PDO::FETCH_ASSOC) : false;
        if ($stmt){ $stmt->closeCursor(); }

        if (!isset($options['model']) || $options['model'] == true){
            return $this->populateRecords($data);
        }
        return $data;
    } 

    public function findAllBySql($sql, $data, $options=array())
    {
        $stmt = $this->db()->query($sql, $data);
        $data = $stmt? $stmt->fetchAll(PDO::FETCH_ASSOC) : false;
        if ($stmt){ $stmt->closeCursor(); }
        if (!isset($options['model']) || $options['model'] == true){
            return $this->populateRecords($data);
        }
        return $data;
    }

    public function populateRecords($data,$index=null)
    {
        $records=array();
        if (!$data) return $records;
        foreach($data as $attributes)
        {
            if(($record=$this->populateRecord($attributes))!==null)
            {
                if($index===null)
                    $records[]=$record;
                else
                    $records[$record->$index]=$record;
            }
        }
        return $records;
    }

    public function populateRecord($attributes)
    {
        if($attributes!==false)
        {
            $record=$this->instantiate($attributes);
            $record->init();
            $record->setAttributes($attributes);
            $record->_pk=$record->getPrimaryKey();
            return $record;
        }
        else
            return null;
    }

    public function getAttributes()
    {
        return $this->_attributes;
    }

    public function setAttributes($attributes)
    {
        foreach($attributes as $name=>$value)
        {
            if(property_exists($this,$name))
                $this->$name=$value;
            //else if(isset($this->schema['columns'][$name]))
            else if(in_array($name, $this->_columns))
                $this->_attributes[$name]=$value;
        }
    }

    public function setAttribute($name, $value)
    {
        if(property_exists($this,$name))
            $this->$name=$value;
        else if(in_array($name, $this->_columns))
            $this->_attributes[$name]=$value;
    }

    protected function beforeSave()
    {
        return true;
    }

    protected function afterSave($type='new')
    {
        return true;
    }

    protected function beforeDelete(){
        return true;
    }

    protected function afterDelete(){
    }

    public function getLogger(){
        if ($this->logger){
            return $this->logger;
        }
        return WF_Registry::get('logger');
    }

    public function setLogger($logger){
        $this->logger = $logger;
    }
}

