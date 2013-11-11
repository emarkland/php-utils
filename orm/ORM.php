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

    private $DBTYPE_TO_PDOTYPE = array(
        ''
    );

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
    public function generateFile()
    {
        $file = $this->createFile();
        foreach ($this->getSchema() as $fieldInfo) {
            fwrite($file, $this->createVar($fieldInfo));
        }
        $this->closeFile($file);
    }

    private function createFile() {
        $filename = $this->_Table . ".php";
        $fPtr = fopen($filename, "w") or die("Can't create file $filename");

        // add headers

        return $fPtr;
    }

    private function closeFile($file){
        fwrite($file, "<?php>");
        fclose($file);
    }

    private function createVar($fieldInfo)
    {
        $field = $fieldInfo["field"];
        $fieldValue = $field . "Value";

        $type = parseType($fieldInfo["type"]);
        $pdoType = getPDOType($type["type"]);

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
                return \$_$field;
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
                    \$_$field = $newFieldValue;
                }
            ";
        }

        $str .= "
            public findAllBy$field($field" . "Value) {
                $nullConstraint
                \$query = 'SELECT * FROM $this->_Table WHERE `$field`= :val';
                return \$this->getAll(\$query, array(
                    ':val' => array($fieldValue, $pdoType),
                ));
            }
        ";
        /**
         * /*
         * private ${fieldName};
         * public function set{FieldName}() {
         * }
         * public function get{FieldName}() {
         *      return $fieldName;
         * }
         *
         * if
         */
    }

    /**
     * Use regex to parse type and size
     * @param $fieldType - The raw string
     * @return array - An array describing the type and size
     */
    private function parseType($fieldType) {
        preg_match('/((?<fieldType>\w+)(\((?<size>\d+)\))?)/', $fieldType, $matches);

        $fType = "string";
        switch (strtolower($matches["fieldType"])) {
            case "int":
                $fType = "int";
                break;
            case "varchar":
            case "text":
                $fType = "string";
                break;
            case "bit":
                $fType = "bool";
                break;
            case "datetime":
            case "date":
                $fType = "date";
                break;

        }

        return array(
            "name" => $fType,
            "size" => $matches["size"]
        );
    }

    private function getPDOType($type) {
        switch ($type) {
            case
        }
    }
    // End of schema to file methods
    protected function modelChanged() {
        return false;
    }
}
?>