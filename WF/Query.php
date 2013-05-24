<?php
extension_loaded('pdo') or die('dependent on pdo extension');
extension_loaded('pdo_mysql') or die('dependent on pdo_mysql extension');

/**
 * Query 数据库操作工具类. 
 * 使用PDO扩展, 可支持多种数据库，方便使用，并防止SQL注入。
 *
 * @depends PDO
 * @version $id$
 * @author kufazhang <zhqm03@gmail.com>
 */
class WF_Query {
    private $conn      = null;
    protected $table   = null;

    protected $columns = array("*");
    protected $where   = null;
    protected $order   = array();
    protected $limit   = null;
    protected $offset  = null;

    public $primaryKey = 'id';
    public $adapter    = null;
    public $fetchAll   = true;
    public $data       = array();

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

    public function _reset(){
        $this->where   = array();
        $this->order   = array();
        $this->limit   = null;
        $this->offset  = null;
        $this->columns = array("*");
        $this->fetchAll = true;
        $this->data    = array();
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
    public function delete($conditions){
        $sql = "DELETE FROM " . $this->tableName() . " WHERE ";
        $where = array();
        if (is_int($conditions)){
            $conditions = array($this->primaryKey => $conditions);
        }
        foreach($conditions as $col => $value)
        {
            $where[] = "$col=?";
        }
        $sql .= implode(" AND ", $where);
        $sql .= " LIMIT 1";

        $result = $this->_execute($sql, array_values($conditions));
        return $result;
    }

    /**
     * update 更新记录
     * 
     * $this->update(array('id'=>10), array('key1'=>'val1', 'key2'=>'val2')
     * $this->update(10, array('key1'=>'val1', 'key2'=>'val2')
     */
    public function update($conditions, $attributes){
        $sql  = 'UPDATE ' . $this->tableName() . " SET ";
        if (is_int($conditions)) {
            $id = $conditions;
            $conditions = array($this->primaryKey => $id);
        }
        $data = array_values($conditions);
        $sql .= implode('= ? ,', array_keys($attributes)) . '= ? ';
        $sql .= "WHERE ";
        foreach($conditions as $key => $value)
        {
            $parts[] = "$key = ?";
        }
        $sql .= implode(" AND ", $parts);

        $result = $this->_execute($sql, array_merge(array_values($attributes), $data));
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
        $result = $this->_execute($sql, array_values($attributes));
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
            $this->where($attrs);
        }
        else {
            $this->where(array($this->primaryKey => $pk));
        }
        return $this->find();
    }

    public function findAll(){
        $stmt = $this->query();
        $data = $stmt? $stmt->fetchAll(PDO::FETCH_ASSOC) : false;
        if ($stmt){ $stmt->closeCursor(); }
        return $data;
    }

    public function find()
    {
        $stmt = $this->query();
        $data = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if ($stmt){ $stmt->closeCursor(); }
        return $data;
    }

    /**
     * findAllBySql 通过sql语句查询
     * 
     * @param string $sql 
     * @param mixed $data 
     */
    public function findAllBySql($sql, $data=array())
    {
        $stmt = $this->_query($sql, $data);
        $data = $stmt? $stmt->fetchAll(PDO::FETCH_ASSOC) : false;
        if ($stmt){ $stmt->closeCursor(); }
        return $data;
    }

    /**
     * _execute 执行查询以外其他操作
     * 
     * @param mixed $sql 
     * @param array $params 
     * @access public
     * @return void
     */
    public function _execute($sql, $params=array()){
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
     * _query 查询
     * 
     * @param mixed $sql 
     * @param array $params 
     * @access public
     * @return void
     */
    public function _query($sql, $params=array()){
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

    public function query($params=null){
        $sql = $this->getSqlString();
        if ($params === null){
            $params = $this->data;
        }
        $this->_reset();
        return $this->_query($sql, $params);
    }

    public function one()
    {
        $this->limit = 1;
        $this->fetchAll = false;
        return $this;
    }

    public function limit($limit, $offset=null)
    {
        $this->limit = $limit;
        if ($offset !== null){
            $this->offset = $offset;
        }
        return $this;
    }

    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    public function order($order)
    {
        if (is_string($order)) {
            if (strpos($order, ',') !== false) {
                $order = preg_split('#,\s+#', $order);
            } else {
                $order = (array) $order;
            }
        }
        foreach ($order as $k => $v) {
            if (is_string($k)) {
                $this->order[$k] = $v;
            } else {
                $this->order[] = $v;
            }
        }
        return $this;
    }

    public function columns(array $columns, $prefixColumnsWithTable = true)
    {
        $this->columns = $columns;
        $this->prefixColumnsWithTable = (bool) $prefixColumnsWithTable;
        return $this;
    }

    public function from($table)
    {
        $this->table = $table;
        return $this;
    }

    public function group($group)
    {
        if (is_array($group)) {
            foreach ($group as $o) {
                $this->group[] = $o;
            }
        } else {
            $this->group[] = $group;
        }
        return $this;
    }

    /**
     * where 
     * 
     * $q->where('id=1');
     * $q->where(array('a'=>1, 'b'=>2));
     * $q->where('positiion = ? and name = ? ', 0, 'main');
     * $q->where(array('pos'=>array(1,2,3))
     *
     * $q->where(array('pos'=>array('$lt'=>3)); //lt, lte, eq, gte, gt, ne, in, between, null
     */
    public function where($predicate)
    {
        if (is_string($predicate)) {
            $this->where[] = array($predicate);
            if (func_num_args() > 1){
                $args = func_get_args();
                array_shift($args);
                foreach($args as $arg){
                    $this->data[] = $arg;
                }
            }
        } elseif (is_array($predicate)) {
            foreach ($predicate as $pkey => $pvalue) {
                if (!is_array($pvalue)){
                    $cond = "$pkey = ?";
                    $this->data[] = $pvalue;
                }
                $this->where[] = array($cond);
            }
        }
        return $this;
    }

    public function getRawState($key = null)
    {
        $rawState = array(
            'columns' => $this->columns,
            'table'   => $this->table,
            'where'   => $this->where,
            'order'   => $this->order,
            'limit'   => $this->limit,
            'offset'  => $this->offset
        );
        return (isset($key) && array_key_exists($key, $rawState)) ? $rawState[$key] : $rawState;
    }

    public function getSqlString()
    {
        $specifications = array(
            'SELECT' => array(
                'SELECT %1$s FROM %2$s' => array( array(1 => '%1$s', 2 => '%1$s AS %2$s', 'combinedby' => ', '), null)
            ),
            //'JOIN'   => array(
            //'%1$s' => array( array(3 => '%1$s JOIN %2$s ON %3$s', 'combinedby' => ' '))
            //),
            'WHERE'  => 'WHERE %1$s',
            //'GROUP'  => array(
            //    'GROUP BY %1$s' => array( array(1 => '%1$s', 'combinedby' => ', '))
            //),
            //'HAVING' => 'HAVING %1$s',
            'ORDER'  => array(
                'ORDER BY %1$s' => array( array(2 => '%1$s %2$s', 'combinedby' => ', '))
            ),
            'LIMIT'  => 'LIMIT %1$s',
            'OFFSET' => 'OFFSET %1$s'
        );

        $sqls = array();
        $parameters = array();

        foreach ($specifications as $name => $specification) {
            $parameters[$name] = $this->{'process' . $name}();
            if ($specification && is_array($parameters[$name])) {
                $sqls[$name] = $this->createSqlFromSpecificationAndParameters($specification, $parameters[$name]);
            }
        }

        $sql = implode(' ', $sqls);
        return $sql;
    }


    protected function createSqlFromSpecificationAndParameters($specification, $parameters)
    {
        if (is_string($specification)) {
            return vsprintf($specification, $parameters);
        }

        $topSpec = key($specification);
        $paramSpecs = $specification[$topSpec];

        $topParameters = array();
        $position = -1;
        foreach ($parameters as $position => $paramsForPosition) {
            if (isset($paramSpecs[$position]['combinedby'])) {
                $multiParamValues = array();
                foreach ($paramsForPosition as $multiParamsForPosition) {
                    $ppCount = count($multiParamsForPosition);
                    if (!isset($paramSpecs[$position][$ppCount])) {
                        throw new Exception\RuntimeException('A number of parameters (' . $ppCount . ') was found that is not supported by this specification');
                    }
                    $multiParamValues[] = vsprintf($paramSpecs[$position][$ppCount], $multiParamsForPosition);
                }
                $topParameters[] = implode($paramSpecs[$position]['combinedby'], $multiParamValues);
            } elseif ($paramSpecs[$position] !== null) {
                $ppCount = count($paramsForPosition);
                if (!isset($paramSpecs[$position][$ppCount])) {
                    //var_dump($specification, $parameters);
                    throw new Exception\RuntimeException('A number of parameters (' . $ppCount . ') was found that is not supported by this specification');
                }
                $topParameters[] = vsprintf($paramSpecs[$position][$ppCount], $paramsForPosition);
            } else {
                $topParameters[] = $paramsForPosition;
            }
        }
        return vsprintf($topSpec, $topParameters);
    }

    public function processLimit()
    {
        if ($this->limit === null){
            return null;
        }
        return array(intval($this->limit));
    }

    public function processOrder()
    {
        if (empty($this->order)) {
            return null;
        }
        $orders = array();
        foreach ($this->order as $k => $v) {
            if (is_int($k)) {
                if (strpos($v, ' ') !== false) {
                    list($k, $v) = preg_split('# #', $v, 2);
                } else {
                    $k = $v;
                    $v = "ASC";
                }
            }
            if (strtoupper($v) == 'DESC') {
                $orders[] = array($this->quoteIdentifier($k), 'DESC');
            } else {
                $orders[] = array($this->quoteIdentifier($k), 'ASC');
            }
        }
        return array($orders);
    }

    public function processWhere()
    {
        if (count($this->where) == 0) {
            return null;
        }
        $tmps = array();
        foreach($this->where as $cond){
            $tmps[] = current($cond);
        }
        return array(implode(" AND ", $tmps));
    }

    public function processOffset()
    {
        if ($this->offset === null){
            return null;
        }
        return array(intval($this->offset));
    }

    public function processSelect()
    {
        $expr = 1;

        if (!$this->table) {
            return null;
        }
        $table = $this->table;
        $quotedTable = $this->quoteIdentifier($table) . ".";

        // process table columns
        $columns = array();
        foreach ($this->columns as $columnIndexOrAs => $column) {

            $columnName = '';
            if ($column === "*") {
                $columns[] = array($quotedTable . "*");
                continue;
            } 

            $columnName .= $quotedTable . $this->quoteIdentifier($column);

            // process As portion
            if (is_string($columnIndexOrAs)) {
                $columnAs = $this->quoteIdentifier($columnIndexOrAs);
            } elseif (stripos($columnName, ' as ') === false) {
                $columnAs = (is_string($column)) ? $this->quoteIdentifier($column) : 'Expression' . $expr++;
            }
            $columns[] = (isset($columnAs)) ? array($columnName, $columnAs) : array($columnName);
        }

        return array($columns, $table);

    }

    public function quoteIdentifier($identifier){
        return sprintf("`%s`", $identifier);
    }

}

/*

$select->from("table")->columns("*")->where("a=？ or c=?")->limit(1)->query($data)
$select->where(xxxx)->order()->query()
$select->where()->one()->query()
$select->findByPk(10)
$select->where()->delete($data)
$select->findBySql()
 */
