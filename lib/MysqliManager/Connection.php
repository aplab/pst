<?php
/**
 * Created by PhpStorm.
 * User: polyanin
 * Date: 13.09.2018
 * Time: 17:07
 */

namespace Aplab\Pst\Lib\MysqliManager;


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
        $config->charset = $configuration['charset'] ?? $manager::DEFAULT_CHARSET;
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
        $this->set_charset($config->charset);
        $this->select_db($config->dbname);
    }

    /**
     * @return mysqli_driver
     */
    public function getDriver()
    {
        $hash = spl_object_hash($this);
        return $this->{$hash}['driver'];
    }

    /**
     * execute query
     *
     * @param string $sql
     * @param int $result_mode
     * @return Result
     */
    public function query($sql, $result_mode = MYSQLI_STORE_RESULT)
    {
        if (MysqliManager::$debug) {
            $trace = debug_backtrace(null, 2);
            $trace = array_pop($trace);
            $service_info = 'sql: ' . preg_replace('/\\s{2,}/', ' ', $sql);
            if ($trace) {
                $service_info = ' line: ' . $trace['line'] . ' ' . $service_info;
                $service_info = 'file: ' . $trace['file'] . $service_info;
            }
            var_dump($service_info);
        }
        try {
            $this->real_query($sql);
            /** @noinspection PhpMethodParametersCountMismatchInspection */
            return new Result($this);
        } catch (mysqli_sql_exception $e) {
            /** @noinspection PhpUndefinedFieldInspection */
            $e->sql   = preg_replace('/\\s{2,}/', ' ', $sql);
            $trace    = $e->getTrace();
            $function = __FUNCTION__;
            $class    = get_class($this);
            $break    = false;
            foreach ($trace as $data_item) {
                if ($break) {
                    /** @noinspection PhpUndefinedFieldInspection */
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

    /**
     * mysql_real_escape_string wrapper shortcut
     *
     * @param string $string
     * @return string|false
     */
    public function e($string)
    {
        return $this->real_escape_string($string);
    }

    /**
     * mysql_real_escape_string wrapper advanced and shortcut
     * It handles multi-dimensional arrays recursively.
     *
     * @param string|array $string
     * @param bool $quote
     * @param bool $double
     * @return false|string
     */
    public function q($string, $quote = true, $double = true)
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
     *
     * @param string|array $value
     * @return array|string
     */
    public function bq($value)
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
     *
     * @param boolean $reload
     * @return array
     */
    public function getListTables($reload = false)
    {
        $hash = spl_object_hash($this);
        if ($reload || (!isset($this->data['list_tables']))) {
            $this->{$hash}['list_tables'] = $this->query('SHOW TABLES')->fetch_col();
        }
        return $this->{$hash}['list_tables'];
    }

    /**
     * Returns a list of table fields in the current database
     *
     * @param void
     * @return array
     */
    public function listFields($table)
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
     *
     * @param string $table
     * @param boolean $reload
     * @return boolean
     */
    public function tableExists($table, $reload = false)
    {
        return in_array($table, $this->getListTables($reload));
    }

    /**
     * Returns true if table is empty, false otherwise
     *
     * @param string $table
     * @return boolean
     */
    public function isEmpty($table)
    {
        /** @noinspection SqlNoDataSourceInspection */
        /** @noinspection SqlResolve */
        $sql = 'SELECT 1 FROM `' . $this->e($table) . '` LIMIT 1';
        return !$this->query($sql)->num_rows;
    }

    /**
     * @param $table
     * @param bool|false $post_check
     * @throws Exception
     */
    public function drop($table, $post_check = false)
    {
        /** @noinspection SqlNoDataSourceInspection */
        /** @noinspection SqlResolve */
        $this->query('DROP TABLE `' . $this->e($table) . '`');
        if ($post_check && $this->tableExists($table, true)) {
            $msg = 'Unable to drop table';
            throw new Exception($msg);
        }
    }

    /**
     * @param $table
     * @param bool|false $post_check
     * @throws Exception
     */
    public function dropIfExists($table, $post_check = false)
    {
        /** @noinspection SqlNoDataSourceInspection */
        /** @noinspection SqlResolve */
        $this->query('DROP TABLE IF EXISTS `' . $this->e($table) . '`');
        if ($post_check && $this->tableExists($table, true)) {
            $msg = 'Unable to drop table';
            throw new Exception($msg);
        }
    }

    /**
     * Delete the table if it is empty
     *
     * @param $table
     * @param bool|false $post_check
     * @throws Exception
     */
    public function dropIfEmpty($table, $post_check = false)
    {
        if ($this->isEmpty($table)) {
            /** @noinspection SqlNoDataSourceInspection */
            /** @noinspection SqlResolve */
            $this->query('DROP TABLE IF EXISTS `' . $this->e($table) . '`');
            if ($post_check && $this->tableExists($table, true)) {
                $msg = 'Unable to drop table';
                throw new Exception($msg);
            }
        }
    }

    /**
     * Split a string that represents multiple sql queries, separated by a separator ";"
     *
     * @param string $query
     * @return array
     */
    public function splitMultiQuery($query)
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
     *
     * @param void
     * @return string
     */
    public function selectSchema()
    {
        return $this->query('select schema()')->fetch_one();
    }
}
