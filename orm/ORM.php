<?php
require_once("MySQLAbstract.php");

class ORM extends MySQLAbstract {
    private $_Table;
    private $_modelChanged = "_modelChanged";
    private $_existsInDB = "_existsInDB";
    private $_fillModelFnName;

    public function __construct($table, $host=null, $dbName=null, $user=null, $pass=null, $options=null) {
        parent::__construct($host, $dbName, $user, $pass, $options);
        $capTable = $this->capitalize_string($table);
        $this->_Table = $capTable;
        $this->_fillModelFnName = "fill$capTable"."Model";
    }
    private function getSchema() {
        $query =
            "SELECT
                COLUMN_NAME AS 'field',
                COLUMN_TYPE AS 'type',
                IS_NULLABLE AS 'null',
                COLUMN_KEY AS 'key',
                COLUMN_DEFAULT AS 'default',
                EXTRA AS 'extra'
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE
                `TABLE_NAME` = :table_name AND
                 `TABLE_SCHEMA` = :table_schema
            ORDER BY Field";

        return $this->getAll($query, array(
            ":table_name" => array($this->_Table, PDO::PARAM_STR),
            ":table_schema" => array($this->getDBName(), PDO::PARAM_STR)
        ));
    }

    private function getSchemaAsJson() {
        return json_encode($this->getSchema());
    }
    public function showSchema() {
        echo $this->getSchemaAsJson();
    }

    public function showType() {
        $json = $this->getSchema();
        $t = $json[0]["type"];
        echo json_encode($this->parseType($t));
    }
    // Schema to file methods
    public function generateFiles() {
        // generate base file
        $file = $this->createBaseFile();
        $tableSchema = $this->getSchema();
        foreach ($tableSchema as $fieldInfo) {
            fwrite($file, $this->generateVarPropertyAndMethods($fieldInfo));
        }
        fwrite($file, $this->generateSaveMethods($tableSchema));
        fwrite($file, $this->generateModelChangedMethod());
        $this->closeFile($file);

        // generate extendable file
        $this->generateExtendedFile();
    }

    private function createBaseFile() {
        $filename = $this->_Table . "Base.php";
        $fPtr = fopen($filename, "w") or die("Can't create file $filename");

        $str = "<?php
require_once(\"MySQLAbstract.php\");
class $this->_Table"."Base extends MySQLAbstract {
    private \$$this->_existsInDB;
    public function __construct() {
        \$this->$this->_existsInDB = false;
        \$this->$this->_modelChanged = false;
    }
";
        // add headers
        fwrite($fPtr, $str);
        return $fPtr;
    }

    private function generateExtendedFile() {
        $filename = $this->_Table . ".php";
        $fPtr = fopen($filename, "w") or die("Can't create file $filename");

        $str = "<?php
    require_once(\"$this->_Table"."Base.php\");
    class $this->_Table extends $this->_Table"."Base {
        // Extend the $this->_Table class via this file
    }
?>";
        fwrite($fPtr, $str);
        fclose($fPtr);
    }
    
    private function generateModelChangedMethod() {
        return "
    private \$$this->_modelChanged = false;
    protected function modelChanged() {
       return \$this->$this->_modelChanged;
    }";
    }

    private function closeFile($file){
        $str = "
}
?>";
        fwrite($file, $str);
        fclose($file);
    }

    private function generateSaveMethods($tableSchema) {
        $str = $this->writeSaveMethod();
        $str .= $this->writeFillModelMethod($tableSchema);
        $str .= $this->writeCreateMethod($tableSchema);
        $str .= $this->writeCommitMethod($tableSchema);
        return $str;
    }

    private function writeSaveMethod() {
        return "
    /**
    * Save $this->_Table into database
    * @return True, if saved into database. False, if otherwise.
    **/
    public function save() {
        if (\$this->$this->_existsInDB && !\$this->$this->_modelChanged) {
            return false;
        }

        \$res = (\$this->$this->_existsInDB) ? \$this->create() : \$this->commit();
        if (\$res) {
            \$this->\$this->$this->_modelChanged = false;
        }
        return \$res;
    }\r\n";
    }

    private function writeFillModelMethod($tableSchema) {
        $reader = "\$reader";
        $item = "\$item";
        $str = "
    protected static function $this->_fillModelFnName($reader) {
        $item = new $this->_Table();";

        foreach ($tableSchema as $fieldInfo) {
            $fieldName = $fieldInfo['field'];
            $fieldValue = $reader . '["' . $fieldName . '"]';
            $str .= "
        $item->" . "_". $this->capitalize_string($fieldName) . " = $fieldValue;";
        }

        $str .= "
        $item->$this->_modelChanged = false;
        $item->$this->_existsInDB = true;
        return $item;
    }\r\n";
        return $str;
    }

    private function writeCreateMethod($tableSchema) {
        $field = "field";
        foreach ($tableSchema as $fieldInfo) {
            $fieldNames[] = $fieldInfo[$field];
        }

        $str = "
    private function create() {
        \$query = \"INSERT INTO $this->_Table (`" . implode("`,`", $fieldNames) . "`) VALUES (" . ":" . implode(",:", $fieldNames) . ")\";
        \$result = \$this->createBase(\$query, array(";

        foreach ($tableSchema as $fieldInfo) {
            $name = $fieldInfo[$field];
            $type = $this->parseType($fieldInfo["type"]);
            $pdoType = $this->getPDOTypeAsString($type["name"]);
            $str .= "
            ':$name' => array(\$this->_" . $this->capitalize_string($name) . ", $pdoType),";
        }
        $str = substr($str, 0, -1);
        $str .= "
        ));
        \$this->$this->_existsInDB = (\$result === null || \$result === -1);
        return \$this->$this->_existsInDB;
    }\r\n";
        return $str;
    }

    private function writeCommitMethod($tableSchema) {
        $field = "field";
        $primaryKey = null;
        foreach ($tableSchema as $fieldInfo) {
            if (strtolower($fieldInfo["key"]) === 'pri') {
                $primaryKey = $fieldInfo;
            }
        }
        if ($primaryKey === null) {
            //TODO: Should just exit method?
            die("Can't generate update method without primary key");
        }

        $str = "
    private function commit() {
        \$query = \"UPDATE $this->_Table SET ";

        // fill in update params
        foreach ($tableSchema as $fieldInfo) {
            if ($fieldInfo[$field] !== $primaryKey[$field]) {
                $str .= "`$fieldInfo[$field]`=:$fieldInfo[$field],";
            }
        }
        // remove last char (the ',' character) from loop
        $str = substr($str, 0, -1);

        $primaryFieldName = $primaryKey[$field];
        $str .= " WHERE `$primaryFieldName`=:$primaryFieldName\";
        \$result = \$this->process(\$query, array(";

        foreach ($tableSchema as $fieldInfo) {
            $name = $fieldInfo[$field];
            $type = $this->parseType($fieldInfo["type"]);
            $pdoType = $this->getPDOTypeAsString($type["name"]);
            $str .= "
            ':$name' => array(\$this->_" . $this->capitalize_string($name) . ", $pdoType),";
        }
        $str .= "
        ));
        return \$result == null;
    }\r\n";

        return $str;
    }

    function capitalize_string($field) {
        $str = strtoupper(substr($field, 0, 1)) . substr($field, 1);
        if ($this->contains_substr($str, '_id')) {
            $str = substr($str, 0, strlen($str)-3) . 'ID';
        }
        return $str;
    }

    function contains_substr($mainStr, $str, $loc = false) {
        if ($loc === false) return (strpos($mainStr, $str) !== false);
        if (strlen($mainStr) < strlen($str)) return false;
        if (($loc + strlen($str)) > strlen($mainStr)) return false;
        return (strcmp(substr($mainStr, $loc, strlen($str)), $str) == 0);
    }

    private function generateVarPropertyAndMethods($fieldInfo)
    {
        $field = $fieldInfo["field"];
        $capitalizedField = $this->capitalize_string($field);
        $fieldValue = $capitalizedField . "Value";

        $type = $this->parseType($fieldInfo["type"]);
        $pdoType = $this->getPDOTypeAsString($type["name"]);

        $nullConstraint = null;
        if ($fieldInfo["null"] !== "YES") {
            $nullConstraint = "
        if (\$$fieldValue == NULL) {
            die('$fieldValue can not be null');
        }";
        }

        $key = $fieldInfo["key"];
        $isPrimaryKey = strtolower($key) === 'pri';

        $defaultValue = $fieldInfo["default"];

        $extra = $fieldInfo["extra"];
        $canAutoIncrement = strpos($extra, 'auto_increment') !== FALSE;

        $str = "
    private \$_$capitalizedField;
    public function get$capitalizedField() {
        return \$this->_$capitalizedField;
    }\r\n";

        if (!$canAutoIncrement) {
            $str .= "
    public function set$capitalizedField(\$$fieldValue) { ";
            if ($nullConstraint !== null) {
                $str .= $nullConstraint;
            }
            $str .= "
        if (\$this->_$capitalizedField === \$$fieldValue) return;
        \$this->_$capitalizedField = \$$fieldValue;
        \$this->$this->_modelChanged = true;
    }\r\n";
        }

        if ($isPrimaryKey) {
            $str .= $this->writeFindByMethod($field, $pdoType, $nullConstraint);
        } else {
            $str .= $this->writeFindAllByMethod($field, $pdoType, $nullConstraint);
        }

        $str .= "\r\n";

        return $str;
    }

    private function writeFindByMethod($field, $pdoType, $nullConstraint=null) {
        $capField = $this->capitalize_string($field);
        $str = "
    public static function findBy$capField(\$$capField"."Value) {";
        if ($nullConstraint !== null) {
            $str .= $nullConstraint;
        }
        $str .= "
        \$$this->_Table = new $this->_Table();
        \$query = 'SELECT * FROM $this->_Table WHERE `$field` = :val';
        \$result = \$$this->_Table->getOne(\$query, array(
            ':val' => array(\$$capField"."Value, $pdoType),
        ));
        return $this->_Table::$this->_fillModelFnName(\$result);
    }";
        return $str;
    }

    private function writeFindAllByMethod($field, $pdoType, $nullConstraint=null) {
        $capField = $this->capitalize_string($field);
        $str = "
    public static function findAllBy$capField(\$$capField"."Value) {";
        if ($nullConstraint !== null) {
            $str .= $nullConstraint;
        }
        $str .= "
        \$$this->_Table = new $this->_Table();
        \$query = 'SELECT * FROM $this->_Table WHERE `$field`= :val';
        \$result = \$$this->_Table->getAll(\$query, array(
            ':val' => array(\$$capField"."Value, $pdoType),
        ));
        foreach (\$result as \$entry => \$value) {
            \$items[] = $this->_Table::$this->_fillModelFnName(\$value);
        }
        return \$items;
    }";
        return $str;
    }

    /**
     * Use regex to parse type and size
     * @param $fieldType - The raw string
     * @return array - An array describing the type and size
     */
    private function parseType($fieldType) {
        preg_match('/((?<fieldType>\w+)(\((?<size>\d+)\))?)/', $fieldType, $matches);
        $name = $this->getCorrespondingPHPTypeFromMySQL($matches["fieldType"]);
        $size = array_key_exists("size", $matches) ? $matches["size"] : -1;
        return array(
            "name" => $name,
            "size" => $size
        );
    }

    private function getCorrespondingPHPTypeFromMySQL($fieldType) {
        switch (strtolower($fieldType)) {
            case "int":
            case "tinyint":
            case "mediumint":
            case "longint":
                return "int";
            case "decimal":
                return "double";
            case "varchar":
            case "text":
            case "tinytext":
            case "mediumtext":
            case "longtext":
                return "string";
            case "bit":
                return "bool";
            case "datetime":
            case "date":
                return "date";
            case "blob":
            case "tinyblob":
            case "mediumblob":
            case "longblob":
                return "byte[]";
        }
        die("Support for $fieldType has not been implemented");
    }

    private function getPDOType($type) {
        switch ($type) {
            case "int":
                return PDO::PARAM_INT;
            case "double":
            case "string":
            case "date":
                return PDO::PARAM_STR;
            case "byte[]":
                return PDO::PARAM_LOB;
            case "bool":
                return PDO::PARAM_BOOL;
        }
        die("Support for converting '$type'' to PDO has not been implemented");
    }

    private function getPDOTypeAsString($type) {
        switch($this->getPDOType($type)) {
            case PDO::PARAM_STR:
                return "PDO::PARAM_STR";
            case PDO::PARAM_INT:
                return "PDO::PARAM_INT";
            case PDO::PARAM_BOOL:
                return "PDO::PARAM_BOOL";
            case PDO::PARAM_LOB:
                return "PDO::PARAM_LOB";
        }
        die("Corresponding string output for '$type' has not been added.");
    }

    // End of schema to file methods
    protected function modelChanged() {
        return false;
    }
}

?>