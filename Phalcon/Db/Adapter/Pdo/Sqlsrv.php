<?php
namespace Phalcon\Db\Adapter\Pdo;

use Phalcon\Db\Column;
use Phalcon\Db\Result\PdoSqlsrv as ResultPdo;

/**
 * Phalcon\Db\Adapter\Pdo\Sqlsrv
 * Specific functions for the MsSQL database system
 * <code>
 * $config = array(
 * "host" => "192.168.0.11",
 * "dbname" => "blog",
 * "port" => 3306,
 * "username" => "sigma",
 * "password" => "secret"
 * );
 * $connection = new \Phalcon\Db\Adapter\Pdo\Sqlsrv($config);
 * </code>.
 *
 * @property \Phalcon\Db\Dialect\Sqlsrv $_dialect
 * @property \Phalcon\Config\Config $config
 */
class Sqlsrv extends \Phalcon\Db\Adapter\Pdo\AbstractPdo implements \Phalcon\Db\Adapter\AdapterInterface
{

    protected $type = 'sqlsrv';
    protected $dialectType = 'sqlsrv';

    /**
     * This method is automatically called in Phalcon\Db\Adapter\Pdo constructor.
     * Call it when you need to restore a database connection.
     *
     * @param array $descriptor
     *
     * @return bool
     */
    public function connect(?array $descriptor = []): void
    {
        if (is_null($descriptor) === true) {
            $descriptor = $this->descriptor;
        }

        if (!array_key_exists("dialectClass", $descriptor)) {
            $descriptor["dialectClass"] = 'Phalcon\\Db\\Dialect\\' . ucfirst($this->dialectType);
        }

        $dialectClass = $descriptor['dialectClass'];
        $this->descriptor = $descriptor;

        $dsn = "sqlsrv:server=" . $descriptor['host'] . ";database=" . $descriptor['dbname'] . ";";
        $dbusername = $descriptor['username'];
        $dbpassword = $descriptor['password'];

        $this->pdo = new \PDO($dsn, $dbusername, $dbpassword);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, true);

        /*
         * Create the instance only if the dialect is a string
         */
        if (is_string($dialectClass) === true) {
            $dialectObject = new $dialectClass();
            $this->dialect = $dialectObject;
        }
    }

    /**
     * Returns an array of Phalcon\Db\Column objects describing a table
     * <code>
     * print_r($connection->describeColumns("posts"));
     * </code>.
     *
     * @param string $table
     * @param string $schema
     *
     * @return \Phalcon\Db\Column
     */
    public function describeColumns(string $table, ?string $schema = null): array
    {
        $oldColumn = null;

        /*
         * Get primary keys
         */
        $primaryKeys = array();
        foreach ($this->fetchAll($this->dialect->getPrimaryKey($table, $schema)) as $field) {
            $primaryKeys[$field['COLUMN_NAME']] = true;
        }

        /*
         * Get the SQL to describe a table
         * We're using FETCH_NUM to fetch the columns
         * Get the describe
         * Field Indexes: 0:name, 1:type, 2:not null, 3:key, 4:default, 5:extra
         */
        foreach ($this->fetchAll($this->dialect->describeColumns($table, $schema)) as $field) {
            /*
             * By default the bind types is two
             */
            $definition = array('bindType' => Column::BIND_PARAM_STR);

            /*
             * By checking every column type we convert it to a Phalcon\Db\Column
             */
            $autoIncrement = false;
            $columnType = $field['TYPE_NAME'];
            switch ($columnType) {
                /*
                 * Smallint/Bigint/Integers/Int are int
                 */
                case 'int identity':
                case 'tinyint identity':
                case 'smallint identity':
                    $definition['type'] = Column::TYPE_INTEGER;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_INT;
                    $autoIncrement = true;
                    break;
                case 'bigint':
                    $definition['type'] = Column::TYPE_BIGINTEGER;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_INT;
                    break;
                case 'decimal':
                case 'money':
                case 'smallmoney':
                    $definition['type'] = Column::TYPE_DECIMAL;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_DECIMAL;
                    break;
                case 'int':
                case 'tinyint':
                case 'smallint':
                    $definition['type'] = Column::TYPE_INTEGER;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_INT;
                    break;
                case 'numeric':
                    $definition['type'] = Column::TYPE_DOUBLE;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_DECIMAL;
                    break;
                case 'float':
                    $definition['type'] = Column::TYPE_FLOAT;
                    $definition['isNumeric'] = true;
                    $definition['bindType'] = Column::BIND_PARAM_DECIMAL;
                    break;

                /*
                 * Boolean
                 */
                case 'bit':
                    $definition['type'] = Column::TYPE_BOOLEAN;
                    $definition['bindType'] = Column::BIND_PARAM_BOOL;
                    break;

                /*
                 * Date are dates
                 */
                case 'date':
                    $definition['type'] = Column::TYPE_DATE;
                    break;

                /*
                 * Special type for datetime
                 */
                case 'datetime':
                case 'datetime2':
                case 'smalldatetime':
                    $definition['type'] = Column::TYPE_DATETIME;
                    break;

                /*
                 * Timestamp are dates
                 */
                case 'timestamp':
                    $definition['type'] = Column::TYPE_TIMESTAMP;
                    break;

                /*
                 * Chars are chars
                 */
                case 'char':
                case 'nchar':
                    $definition['type'] = Column::TYPE_CHAR;
                    break;

                case 'varchar':
                case 'nvarchar':
                    $definition['type'] = Column::TYPE_VARCHAR;
                    break;

                /*
                 * Text are varchars
                 */
                case 'text':
                case 'ntext':
                    $definition['type'] = Column::TYPE_TEXT;
                    break;

                /*
                 * blob type
                 */
                case 'varbinary':
                    $definition['type'] = Column::TYPE_BLOB;
                    break;

                /*
                 * By default is string
                 */
                default:
                    $definition['type'] = Column::TYPE_VARCHAR;
                    break;
            }

            /*
             * If the column type has a parentheses we try to get the column size from it
             */
            $definition['size'] = (int) $field['LENGTH'];
            $definition['precision'] = (int) $field['PRECISION'];

            if ($field['SCALE'] || $field['SCALE'] == '0') {
                $definition['size'] = $definition['precision'];
            }

            /*
             * Positions
             */
            if (!$oldColumn) {
                $definition['first'] = true;
            } else {
                $definition['after'] = $oldColumn;
            }

            /*
             * Check if the field is primary key
             */
            if (isset($primaryKeys[$field['COLUMN_NAME']])) {
                $definition['primary'] = true;
            }

            /*
             * Check if the column allows null values
             */
            if ($field['NULLABLE'] == 1) {
                $definition['notNull'] = false;
            }

            /*
             * Check if the column is auto increment
             */
            if ($autoIncrement) {
                $definition['autoIncrement'] = true;
            }

            /*
             * Check if the column is default values
             */
            if ($field['COLUMN_DEF'] != null) {
                $definition['default'] = $field['COLUMN_DEF'];
            }

            $columnName = $field['COLUMN_NAME'];
            $columns[] = new Column($columnName, $definition);
            $oldColumn = $columnName;
        }

        return $columns;
    }

    /**
     * Sends SQL statements to the database server returning the success state.
     * Use this method only when the SQL statement sent to the server is returning rows
     * <code>
     * //Querying data
     * $resultset = $connection->query("SELECTFROM robots WHERE type='mechanical'");
     * $resultset = $connection->query("SELECTFROM robots WHERE type=?", array("mechanical"));
     * </code>.
     *
     * @param string $sqlStatement
     * @param mixed  $bindParams
     * @param mixed  $bindTypes
     *
     * @return bool|\Phalcon\Db\ResultInterface
     */
    public function query($sqlStatement, $bindParams = [], $bindTypes = [])
    {
        $eventsManager = $this->eventsManager;

        /*
         * Execute the beforeQuery event if a EventsManager is available
         */
        if (is_object($eventsManager)) {
            $this->sqlStatement = $sqlStatement;
            $this->sqlVariables = $bindParams;
            $this->sqlBindTypes = $bindTypes;

            if ($eventsManager->fire('db:beforeQuery', $this, $bindParams) === false) {
                return false;
            }
        }

        $cursor = \PDO::CURSOR_SCROLL;
        if (strpos($sqlStatement, 'exec') !== false) {
            $cursor = \PDO::CURSOR_FWDONLY;
        }

        $statement = $this->pdo->prepare($sqlStatement, array(\PDO::ATTR_CURSOR => $cursor));
        if (is_array($bindParams) && is_object($statement)) {
            $statement = $this->executePrepared($statement, $bindParams, $bindTypes);
        } else {
            $statement->execute();
        }

        /*
         * Execute the afterQuery event if a EventsManager is available
         */
        if (is_object($statement)) {
            if (is_object($eventsManager)) {
                $eventsManager->fire('db:afterQuery', $this, $bindParams);
            }

            return new ResultPdo($this, $statement, $sqlStatement, $bindParams, $bindTypes);
        }

        return $statement;
    }

    protected function getDsnDefaults(): array
    {
        return [];
    }
}