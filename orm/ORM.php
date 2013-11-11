<?php
require_once("MySQLAbstract.php");
/**
 * ORM Needs....
Primary Key Support
Foreign Key Support?

Get/Set Methods
FindAllBy{BLAH, Options}
FindBy(Primary/Foreign Key)
Create/Commit() --> Save()?

varchar --> string (with max size)
datetime --> date
int --> int (32) or long (64)
bit --> boolean
text -> string (unlimited size)
indexSupport? (how to handle multiple indices?)


// establish connection to database
// generate query to fetch schema
// generate files into an output directory from schemas
// should validate that files are correctly generated
 */

class ORM extends MySQLAbstract {
    private $_Table;
    private $_modelChanged = "\$_modelChanged";

    public function __construct($table, $host=null, $dbName=null, $user=null, $pass=null, $options=null) {
        parent::__construct($host, $dbName, $user, $pass, $options);
        $this->_Table = $table;
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
    public function generateFile() {
        $file = $this->createFile();
        $tableSchema = $this->getSchema();
        foreach ($tableSchema as $fieldInfo) {
            fwrite($file, $this->createVar($fieldInfo, $tableSchema));
        }
        $this->closeFile($file, $tableSchema);
    }

    private function createFile() {
        $filename = $this->_Table . ".php";
        $fPtr = fopen($filename, "w") or die("Can't create file $filename");

        $str = "
        <?php

        require_once(\"MySQLAbstract.php\");
        class $this->_Table extends MySQLAbstract {
        ";
        // add headers
        fwrite($fPtr, $str);
        return $fPtr;
    }

    private function closeFile($file, $tableSchema){
        $str = "
            private $this->_modelChanged = false;
            protected function modelChanged() {
                return \$this->$this->_modelChanged;
            }
        }
        ?>
        ";
        fwrite($file, $str);
        fclose($file);
    }

    private function createVar($fieldInfo, $tableSchema)
    {
        $field = $fieldInfo["field"];
        $fieldValue = $field . "Value";

        $type = $this->parseType($fieldInfo["type"]);
        $pdoType = $this->getPDOTypeAsString($type["name"]);

        $null = $fieldInfo["null"];
        $isNullable = $null === "YES";
        $nullConstraint = "";

        $key = $fieldInfo["key"];
        $isPrimaryKey = strtolower($key) === 'pri';

        $defaultValue = $fieldInfo["default"];

        $extra = $fieldInfo["extra"];
        $canAutoIncrement = strpos($extra, 'auto_increment') !== FALSE;

        $str = "
            private \$_$field;
            public function get$field() {
                return \$this->_$field;
            }
        ";

        if (!$canAutoIncrement) {
            $newFieldValue = "\$new" . $fieldValue;

            $nullConstraint = '';
            if ($isNullable) {
                $nullConstraint = "
                    if ($newFieldValue == NULL) {
                        die('\'$newFieldValue\' can't be null');
                    }
                ";
            }

            $str .= "
                public function set$field($newFieldValue) {
                    $nullConstraint
                    \$this->_$field = $newFieldValue;
                    \$this->$this->_modelChanged = true;
                }
            ";
        }

        $fieldValueAsVar = "\$$fieldValue";
        if ($isPrimaryKey) {
            $resultPlaceHolder = '$result';
            $str .= "
                public function findBy$field($fieldValueAsVar) {
                    $nullConstraint
                    \$query = 'SELECT * FROM $this->_Table WHERE `$field` = :val';
                    $resultPlaceHolder = \$this->getOne(\$query, array(
                        ':val' => array($fieldValueAsVar, $pdoType),
                    ));
                    " . $this->setValueFromFetch(false, $resultPlaceHolder, $tableSchema) . "
                }
            ";
        } else {
            $resultPlaceHolder = '$results';
            $str .= "
                public function findAllBy$field($fieldValueAsVar) {
                    $nullConstraint
                    \$query = 'SELECT * FROM $this->_Table WHERE `$field`= :val';
                    $resultPlaceHolder = \$this->getAll(\$query, array(
                        ':val' => array($fieldValueAsVar, $pdoType),
                    ));
                    " . $this->setValueFromFetch(true, $resultPlaceHolder, $tableSchema) . "
                }
            ";
        }
        return $str;
    }

    private function setValueFromFetch($isMulti, $resultPlaceHolder, $tableSchema) {
        $str = '';

        $itemVal = $resultPlaceHolder;
        if ($isMulti) {
            $itemVal = '$val';
            $str = "foreach ($resultPlaceHolder as \$entry => $itemVal) {";
        }
        $itemVar = "\$item";
        $i = "$itemVar = new " . $this->_Table . '();';

        foreach ($tableSchema as $fieldInfo) {
            $fieldName = $fieldInfo['field'];
            $fieldValue = $itemVal . '["' . $fieldName . '"]';
            $i .= "$itemVar->" . "_$fieldName = $fieldValue;";
        }

        if ($isMulti) {
            $str .= $i;
            $str .= "
                \$items[] = $itemVar;
            }
            return \$items;";
        } else {
            $str = $i;
            $str .= "return $itemVar;";
        }
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

/**
 * TEST
 **/
$test = new ORM("contact", "localhost", "pjhannon-db", "root", "password");
$test->generateFile();

?>