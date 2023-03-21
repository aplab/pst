<?php namespace Aplab\Pst\Lib\MysqliManager;

use Aplab\Pst\Lib\Tools;
use mysqli;
use mysqli_driver;
use mysqli_sql_exception;
use stdClass;

class Connection extends mysqli
{
    /** @noinspection PhpMissingParentConstructorInspection */
    /**
     * Connection constructor.
     * @param MysqliManager $manager
     * @param string $name
     */
    public function __construct(MysqliManager $manager, $name = MysqliManager::DEFAULT_CONNECTION_NAME)
    {
        $configuration = ($manager->getConfiguration())[$name];
        $config = new stdClass();
        $config->host = $configuration['host'] ?? $manager::DEFAULT_HOST;
        $config->port = $configuration['port'] ?? $manager::DEFAULT_PORT;
        $config->username = $configuration['username'];
        $config->password = $configuration['password'];
        $config->socket = $configuration['socket'] ?? null;
        $config->dbname = $configuration['dbname'] ?? null;
        $config->persistent = $configuration['persistent'] ?? true;
        $config->init = $configuration['init'] ?? [];
        if (is_scalar($config->init)) {
            $config->init = [$config['init']];
        }
        $hash = spl_object_hash($this);
        $driver = new mysqli_driver();
        $driver->report_mode = MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT;
        $this->{$hash}['driver'] = $driver;
        //$this->{$hash}['config'] = $config;
        $this->init();
        if ($config->socket) {
            $this->real_connect(
                ($config->persistent ? 'p:' : '') . $config->host,
                $config->username,
                $config->password,
                $config->dbname,
                $config->port,
                $config->socket
            );
        } else {
            $this->real_connect(
                ($config->persistent ? 'p:' : '') . $config->host,
                $config->username,
                $config->password,
                $config->dbname,
                $config->port
            );
        }
        foreach ($config->init as $sql) {
            $this->query($sql);
        }
        $this->select_db($config->dbname);
    }

    public function getDriver(): mysqli_driver
    {
        $hash = spl_object_hash($this);
        return $this->{$hash}['driver'];
    }

    public function query(string $query, $result_mode = MYSQLI_STORE_RESULT): Result
    {
        if (MysqliManager::$debug) {
            $trace = debug_backtrace(0, 2);
            $trace = array_pop($trace);
            $service_info = 'sql: ' . preg_replace('/\\s{2,}/', ' ', $query);
            if ($trace) {
                $service_info = ' line: ' . $trace['line'] . ' ' . $service_info;
                $service_info = 'file: ' . $trace['file'] . $service_info;
            }
            var_dump($service_info);
        }
        try {
            $this->real_query($query);
            return new Result($this);
        } catch (mysqli_sql_exception $e) {
            /** @noinspection PhpDynamicFieldDeclarationInspection */
            $e->sql   = preg_replace('/\\s{2,}/', ' ', $query);
            $trace    = $e->getTrace();
            $function = __FUNCTION__;
            $class    = get_class($this);
            $break    = false;
            foreach ($trace as $data_item) {
                if ($break) {
                    /** @noinspection PhpDynamicFieldDeclarationInspection */
                    $e->called_from = $data_item;
                    break;
                }
                if ($function === $data_item['function'] &&
                    $class === $data_item['class']
                ) {
                    $break = true;
                }
            }
            throw $e;
        }
    }

    public function e(string $string): false|string
    {
        return $this->real_escape_string($string);
    }

    /**
     * mysql_real_escape_string wrapper advanced and shortcut
     * It handles multidimensional arrays recursively.
     */
    public function q(array|string $string, bool $quote = true, bool $double = true): array|string
    {
        if (is_array($string)) {
            $db = $this;
            array_walk($string, function (&$v) use ($db) {
                $v = $db->q($v);
            });
            return $string;
        }
        if ($quote) {
            if ($double) {
                return '"' . $this->real_escape_string($string) . '"';
            }
            return '\'' . $this->real_escape_string($string) . '\'';
        }
        return $this->real_escape_string($string);
    }

    /**
     * Put values into backquotes
     * Processes multidimensional arrays recursively.
     */
    public function bq(array|string $value): array|string
    {
        if (is_array($value)) {
            $db = $this;
            array_walk($value, function (&$v) use ($db) {
                $v = $db->bq($v);
            });
            return $value;
        }
        return '`' . $value . '`';
    }

    /**
     * Returns a list of tables in the current database
     */
    public function getListTables(bool $reload = false): array
    {
        $hash = spl_object_hash($this);
        if ($reload || (!isset($this->data['list_tables']))) {
            $this->{$hash}['list_tables'] = $this->query('SHOW TABLES')->fetch_col();
        }
        return $this->{$hash}['list_tables'];
    }

    /**
     * Returns a list of table fields in the current database
     */
    public function listFields(string $table): array
    {
        $hash = spl_object_hash($this);
        if (!isset($this->data['list_fields'][$table])) {
            $this->{$hash}['list_fields'][$table] =
                $this->query('SHOW COLUMNS FROM `'
                    . $this->escape_string($table) . '`')->fetch_col();
        }
        return $this->{$hash}['list_fields'][$table];
    }

    /**
     * Returns true if table exists into current database, false otherwise
     */
    public function tableExists(string $table, bool $reload = false): bool
    {
        return in_array($table, $this->getListTables($reload));
    }

    /**
     * Returns true if table is empty, false otherwise
     */
    public function isEmpty(string $table): bool
    {
        /** @noinspection SqlNoDataSourceInspection */
        /** @noinspection SqlResolve */
        $sql = 'SELECT 1 FROM `' . $this->e($table) . '` LIMIT 1';
        return !$this->query($sql)->num_rows;
    }

    /**
     * @throws Exception
     */
    public function drop(string $table, bool $post_check = false)
    {
        /** @noinspection SqlNoDataSourceInspection */
        $this->query('DROP TABLE `' . $this->e($table) . '`');
        if ($post_check && $this->tableExists($table, true)) {
            $msg = 'Unable to drop table';
            throw new Exception($msg);
        }
    }

    /**
     * @throws Exception
     */
    public function dropIfExists(string $table, bool $post_check = false)
    {
        /** @noinspection SqlNoDataSourceInspection */
        $this->query('DROP TABLE IF EXISTS `' . $this->e($table) . '`');
        if ($post_check && $this->tableExists($table, true)) {
            $msg = 'Unable to drop table';
            throw new Exception($msg);
        }
    }

    /**
     * Delete the table if it is empty
     */
    public function dropIfEmpty(string $table, bool $post_check = false)
    {
        if ($this->isEmpty($table)) {
            /** @noinspection SqlNoDataSourceInspection */
            $this->query('DROP TABLE IF EXISTS `' . $this->e($table) . '`');
            if ($post_check && $this->tableExists($table, true)) {
                $msg = 'Unable to drop table';
                throw new Exception($msg);
            }
        }
    }

    /**
     * Split a string that represents multiple sql queries, separated by a separator ";"
     */
    public function splitMultiQuery(string $query): array
    {
        $ret = array();
        $token = Tools::my_strtok($query, ";");
        while ($token) {
            $prev = $token;
            $ret[] = $prev;
            $token = Tools::my_strtok();

            if (!$token) {
                return $ret;
            }
        }
        return $ret;
    }

    /**
     * Returns current database name
     */
    public function selectSchema(): string
    {
        return $this->query('select schema()')->fetch_one();
    }
}
