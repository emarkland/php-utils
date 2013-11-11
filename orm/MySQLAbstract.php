<?php
require_once('MySQLException.php');

/**
 * Manage a MySQL connection
 *
 * @package MySQL
 */
abstract class MySQLAbstract
{
    private $_Host;
    private $_DBName;
    private $_User;
    private $_Pass;
    private $_Connection;

    /**
     * Connect to a MySQL database
     *
     * @param String $host The host or domain name
     * @param String $dbName The name of the database to connect to
     * @param String $user The db user name
     * @param String $pass The db user name's password
     */
    public function __construct($host=null, $dbName=null, $user=null, $pass=null, $options=null)
    {
        if (($host == null) && ($dbName == null) && ($user == null) && ($pass == null)) {
            $mysql = $GLOBALS["config"]["mysql"];
            $host = $mysql["host"];
            $dbName = $mysql["dbname"];
            $user = $mysql["user"];
            $pass = $mysql["pass"];
        }

        $this->_Host = $host;
        $this->_DBName = $dbName;
        $this->_User = $user;
        $this->_Pass = $pass;

        if (!$this->_Connection instanceof PDO) {
            try {
                $this->_Connection = new PDO("mysql:host=$this->_Host;dbname=$this->_DBName", $this->_User, $this->_Pass);
                $this->_Connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            } catch (PDOException $e) {
                //TODO: Create that log class and log this exception
                throw new MySQLConnectionException($e);
            }
        }
    }

    /**
     * Process query
     * @param $query - The query to be processed
     * @param $queryParams - Extra query string parameters (Format: [name1 => [value1, pdo object type], name2 => [value2, pdo object type], ...])
     * @throws MySQLQueryException
     * @return Boolean True, if the query was successfully processed. False, if otherwise.
     */
    protected function process($query, $queryParams) {
        try {
            $preparedQuery = $this->_Connection->prepare($query);
            if ($queryParams !== null && gettype($queryParams) === 'array') {
                foreach ($queryParams as $name => $value) {
                    if (count($value) === 2) {
                        $preparedQuery->bindParam($name, $value[0], $value[1]);
                    }
                }
            }
            if ($preparedQuery->execute()) {
                return $preparedQuery;
            }
        } catch (PDOException $e) {
            throw new MySQLQueryException($e);
        }
        return null;
    }

    /**
     * The base create mod
     * @param String $query
     * @param Array $queryParams
     * @return string|number
     */
    protected function createBase($query, $queryParams) {
        if ($this->process($query, $queryParams)) {
            return $this->_Connection->lastInsertId();
        }
        return -1;
    }

    /**
     * Get a result from mysql query
     * @param String $query The query to be processed
     * @param Array $queryParams Extra query string parameters (Format: [name1 => [value1, pdo object type], name2 => [value2, pdo object type], ...])
     * @return The result entry fetched. Null, if otherwise
     */
    protected function getOne($query, $queryParams = null) {
        $result = $this->process($query, $queryParams);
        if ($result) {
            return $result->fetch(PDO::FETCH_ASSOC);
        }
        return null;
    }

    /**
     * Get all results from mysql query
     * @param String $query The query to be processed
     * @param Array $queryParams Extra query string parameters (Format: [name1 => [value1, pdo object type], name2 => [value2, pdo object type], ...])
     * @return An array of results that meets query criteria. Null, if otherwise
     */
    protected function getAll($query, $queryParams = null) {
        $result = $this->process($query, $queryParams);
        if ($result) {
            return $result->fetchAll(PDO::FETCH_ASSOC);
        }
        return null;
    }

    protected function getDBName() {
        return $this->_DBName;
    }

    /**
     * Detect whether if a given model has changed
     * @return True, if the model has changed. False, if otherwise.
     */
    abstract protected function modelChanged();
}
?>