<?php
class WF_Db {
    private $adapter = '';
    private $conn = null;

    private static $instance;

    public function __construct($db_config=null){
        if (is_array($db_config) && (isset($db_config['host']) || isset($db_config['dsn']))){
            if (!isset($db_config['dsn'])) {
                $this->adapter = isset($db_config['adapter']) ? $db_config['adapter'] : 'mysql';
                $dsn = $this->adapter . ":host=" . $db_config['host'] . ";port=" . $db_config['port'];
                if (isset($db_config['dbname'])){
                    $dsn .= ";dbname=" . $db_config['dbname'];
                }
                $options = array();
                if ($this->adapter == 'mysql') {
                    $options = array(
                        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . $db_config['enCode'],
                    );
                }
                $this->connect($dsn, $db_config['username'], $db_config['password'], $options);
            }
            else {
                $this->connect($db_config['dsn'], $db_config['username'], $db_config['password'], array());
                $arr = explode(':', $db_config);
                $this->adapter = $arr[0];
            }
        }
        elseif (is_string($db_config)){
            $dsn = $db_config;
            $username = '';
            $password = '';
            $this->connect($dsn, $username, $password);
        }
    }

    public static function instance($db_config=null){
        if (self::$instance == null){
            $className = __CLASS__;
            self::$instance = new $className($db_config);
        }
        return self::$instance;
    }

    public static function setInstance(WF_Db $instance){
        self::$instance = $instance;
    }

    public function getDBConnection(){
        return $this->conn;
    }

    public function setDBConnection($conn){
        $this->conn = $conn;
    }

    public function selectDB($dbname){
        if ($this->conn->getAttribute(PDO::ATTR_DRIVER_NAME)== 'mysql'){
            return $this->conn->exec("use " . PDO::quote($dbname));
        }
        return false;
    }

    public function getLogger(){
        if (!$this->logger){
            return new WF_Logger();
        }
    }

    public function setLogger($logger){
        $this->logger = $logger;
    }

    public function connect($dsn, $username='', $password='', $driver_options=array()){
        $this->conn = new PDO($dsn, $username, $password, $driver_options);
    }

    public function execute($sql, $params=array()){
        $logger = $this->getLogger();
        if (!$this->conn) {
            $logger->error('connection not exist');
            return false;
        }

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            $logger->error("sql prepare failed '$sql'");
            return false;
        }

        $result = $stmt->execute($params);
        if (!$result){
            $logger->error("sql execute failed '$sql (" . implode(', ', $params) . ")'");
        }
        return $result;
    }

    public function query($sql, $params=array()){
        $logger = $this->getLogger();
        if (!$this->conn) {
            return false;
        }
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            $logger->error("sql prepare failed '$sql'");
            $logger->debug("sql prepare failed '$sql'" . $this->conn->errorInfo());
            return false;
        }
        $result = $stmt->execute($params);
        if (!$result){
            $logger->error("sql execute failed '$sql (" . implode(', ', $params) . ")'");
            $logger->debug("sql prepare failed '$sql'" . $this->conn->errorInfo());
        }
        return $stmt;
    }

    public function __call($name, $params){
        if (method_exists($this->conn, $name)) {
            return call_user_func_array(array($this->conn, $name), $params);
        }
        return false;
    }

    public function close(){
        unset($this->conn);
    }
}
