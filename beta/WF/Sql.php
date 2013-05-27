<?php

class WF_Table {
    private $conn = null;
    protected $table = '';
    public $primaryKey = 'id';

    public function __construct($table, $db, $primaryKey = 'id')
    {
        $this->conn = $db;
        $this->table = $table;
        $this->primaryKey = $primaryKey;
    }

    public function tableName(){
        return $this->table;
    }

    private function connect($dsn, $username='', $password='', $driver_options=array()){
        $this->conn = new PDO($dsn, $username, $password, $driver_options);
    }

    public function execute($sql, $params=array()){
        $logger = WF_Registry::get('logger');
        $logger->debug("sql execute: '$sql (" . implode(', ', $params) . ")'");
        if (!$this->conn) {
            $logger = WF_Registry::get('logger');
            $logger->error('connection not exist');
            return false;
        }

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            $logger->error("sql prepare failed '$sql'");
            $logger->error("db error:" . $this->conn->errorCode() . ' ' . json_encode($this->conn->errorInfo()));
            return false;
        }

        $result = $stmt->execute($params);
        if (!$result){
            $logger->error("sql execute failed '$sql (" . implode(', ', $params) . ")'");
            $logger->error("db error:" . $stmt->errorCode() . ' ' . json_encode($stmt->errorInfo()));
        }
        $stmt->closeCursor();
        return $result;
    }

    public function query($sql, $params=array()){
        $logger = WF_Registry::get('logger');
        $logger->debug("sql query result: '$sql (" . implode(', ', $params) . ")' rowset count:");
        if (!$this->conn) {
            return false;
        }
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            $logger->error("sql prepare failed '$sql'");
            $logger->error("db error:" . $this->conn->errorCode() . ' ' . json_encode($this->conn->errorInfo()));
            return false;
        }
        $result = $stmt->execute($params);
        if (!$result){
            $logger->error("sql query failed '$sql (" . implode(', ', $params) . ")'");
            $logger->error("db error:" . $stmt->errorCode() . ' ' . json_encode($stmt->errorInfo()));
        }
        return $stmt;
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

    public function delete($attributes){
        $sql = "DELETE FROM " . $this->tableName() . " WHERE ";
        $conditions = array();
        if (is_int($attributes)){
            $attributes = array($this->primaryKey => $attributes);
        }
        foreach($attributes as $col => $value)
        {
            $conditions[] = "$col=?";
        }
        $sql .= implode(" AND ", $conditions);
        $sql .= " LIMIT 1";

        $result = $this->execute($sql, array_values($attributes));
        return $result;
    }

    /**
     * update 
     * 
     * @param int|array $conditions 
     * @param array $attrs 
     * @access public
     * @return void
     */
    public function update($conditions, $attrs){
        $sql  = 'UPDATE ' . $this->tableName() . " SET ";
        if (is_int($conditions)) {
            $id = $conditions;
            $conditions = array($this->primaryKey => $id);
        }
        $data = array_values($conditions);
        $sql .= implode('= ? ,', array_keys($attrs)) . '= ? ';
        $sql .= "WHERE ";
        foreach($conditions as $key => $value)
        {
            $parts[] = "$key = ?";
        }
        $sql .= implode(" AND ", $parts);

        $result = $this->execute($sql, array_merge(array_values($attrs), $data));
        return $result;
    }

    public function insert($arributes)
    {
        $sql = "insert into " . $this->tableName() . '(';
        $sql .= implode(',', array_keys($attributes));
        $sql .= ') values (';
        $sql .= implode(',', array_pad(array(), count($attributes), '?'));
        $sql .= ')';
        $result = $this->execute($sql, array_values($this->_attributes));
        return $result;
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
        $stmt = $this->query($sql, array($pk));
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($stmt){ $stmt->closeCursor(); }
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
        $stmt = $this->query($sql, array_values($attributes));
        $data = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if ($stmt){ $stmt->closeCursor(); }
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
        return $sql;
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
        return $sql;
    } 

    public function findAllBySql($sql, $data, $options=array())
    {
        $stmt = $this->query($sql, $data);
        $data = $stmt? $stmt->fetchAll(PDO::FETCH_ASSOC) : false;
        if ($stmt){ $stmt->closeCursor(); }
        return $data;
    }
}

