<?php

/**
 * Query 数据库操作工具类. 
 * 使用PDO扩展, 可支持多种数据库，方便使用，并防止SQL注入。
 *
 * @depends PDO
 * @version $id$
 * @author kufazhang <zhqm03@gmail.com>
 */
class WF_Query {
    private $conn = null;
    protected $table = '';
    public $primaryKey = 'id';

    public function __construct($table, $db=null, $primaryKey = 'id')
    {
        $this->table = $table;
        $this->conn = $db;
        $this->primaryKey = $primaryKey;
    }

    public function tableName(){
        return $this->table;
    }

    public function connect($dsn, $username='', $password='', $driver_options=array()){
        $this->conn = new PDO($dsn, $username, $password, $driver_options);
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

    /**
     * delete 删除记录
     * 
     * @param mixed $attributes 可为主键id或者字段数组
     * @access public
     * @return void
     */
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
     * update 更新记录
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

    /**
     * insert 插入记录
     * 
     * @param mixed $attributes 字段数组
     * @access public
     * @return void
     */
    public function insert($attributes)
    {
        $sql = "insert into " . $this->tableName() . '(';
        $sql .= implode(',', array_keys($attributes));
        $sql .= ') values (';
        $sql .= implode(',', array_pad(array(), count($attributes), '?'));
        $sql .= ')';
        $result = $this->execute($sql, array_values($attributes));
        return $result;
    }

    /**
     * findByPk 通过主键查询
     * 
     * @param mixed $pk 
     * @param array $options 
     * @access public
     * @return void
     */
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
        $stmt = $this->query($sql, array_values($attributes));
        $data = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if ($stmt){ $stmt->closeCursor(); }
        return $data;
    }

    /**
     * findAll 通过多种条件查询，并可以使用排序、limit等。
     * 
     * @param array $condition 
     * @param array $options 
     * @access public
     * @return void
     */
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
        $stmt = $this->query($sql, $params);
        $data = $stmt? $stmt->fetchAll(PDO::FETCH_ASSOC) : false;
        if ($stmt){ $stmt->closeCursor(); }

        return $data;
    } 

    /**
     * findAllBySql 通过sql语句查询
     * 
     * @param mixed $sql 
     * @param mixed $data 
     * @param array $options 
     * @access public
     * @return void
     */
    public function findAllBySql($sql, $data, $options=array())
    {
        $stmt = $this->query($sql, $data);
        $data = $stmt? $stmt->fetchAll(PDO::FETCH_ASSOC) : false;
        if ($stmt){ $stmt->closeCursor(); }
        return $data;
    }

    /**
     * execute 执行查询以外其他操作
     * 
     * @param mixed $sql 
     * @param array $params 
     * @access public
     * @return void
     */
    public function execute($sql, $params=array()){
        //echo "sql execute: '$sql (" . implode(', ', $params) . ")'" . "\n";
        if (!$this->conn) {
            return false;
        }

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $result = $stmt->execute($params);
        $stmt->closeCursor();
        return $result;
    }

    /**
     * query 查询
     * 
     * @param mixed $sql 
     * @param array $params 
     * @access public
     * @return void
     */
    public function query($sql, $params=array()){
        //echo "sql query result: '$sql (" . implode(', ', $params) . ")' rowset count:", "\n";
        if (!$this->conn) {
            return false;
        }
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            return false;
        }
        $result = $stmt->execute($params);
        if (!$result){
        }
        return $stmt;
    }

}

