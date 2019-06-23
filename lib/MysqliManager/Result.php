<?php
/**
 * Created by PhpStorm.
 * User: polyanin
 * Date: 13.09.2018
 * Time: 17:08
 */

namespace Aplab\Pst\Lib\MysqliManager;


use mysqli_result;

class Result extends mysqli_result
{
    /**
     * Returns the value of the first column of the first row of the result
     *
     * @return int|NULL
     */
    public function fetch_one()
    {
        $row = $this->fetch_row();
        if (is_array($row) && isset($row[0])) {
            return $row[0];
        }
        return null;
    }

    /**
     * Selects all rows from the result set and places them in associative array
     *
     * @param void
     * @return array
     */
    public function fetch_assoc_all()
    {
        return $this->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Selects all rows from the result set and places them in object
     *
     * @param string
     * @param array $params
     * @return array
     */
    public function fetch_object_all($class = null, array $params = null)
    {
        $ret = array();
        if (is_null($class) && is_null($params)) {
            $row = $this->fetch_object();
            while ($row) {
                $ret[] = $row;
                $row = $this->fetch_object();
            }
            return $ret;
        }
        if (is_null($params)) {
            $row = $this->fetch_object($class);
            while ($row) {
                $ret[] = $row;
                $row = $this->fetch_object($class);
            }
            return $ret;
        }
        $row = $this->fetch_object($class, $params);
        while ($row) {
            $ret[] = $row;
            $row = $this->fetch_object($class, $params);
        }
        return $ret;
    }

    /**
     * Returns a specific column
     *
     * @param void
     * @return array|false
     */
    public function fetch_col($n = null)
    {
        $n = $n ?: 0;
        $ret = array();
        $row = $this->fetch_row();
        while ($row) {
            $ret[] = $row[$n];
            $row = $this->fetch_row();
        }
        return $ret;
    }

    /**
     * Returns the first line of the result
     *
     * @param void
     * @return array
     */
    public function fetch_assoc_first()
    {
        return $this->fetch_assoc();
    }

    /**
     * Возвращает массив с ключом $key и значением $value
     * Если $key не задан то обычный индекс по порядку.
     * Если $value не задан то вся строка.
     * Третий параметр если false и при этом задан ключ, то ключа не будет в подмассиве результата
     *
     * @param string $key
     * @param string $value
     * @param bool $key_present
     * @return array
     */
    public function fetch_all_index($key = null, $value = null, $key_present = true)
    {
        if (is_null($key) && is_null($value)) {
            return $this->fetch_assoc_all();
        }
        $ret = array();
        if (is_null($value)) {
            $row = $this->fetch_assoc();
            while ($row) {
                $k = $row[$key];
                if (!$key_present) {
                    unset ($row[$key]);
                }
                $ret[$k] = $row;
                $row = $this->fetch_assoc();
            }
            return $ret;
        }
        if (is_null($key)) {
            $row = $this->fetch_assoc();
            while ($row) {
                $ret[] = $row[$value];
                $row = $this->fetch_assoc();
            }
            return $ret;
        }
        $row = $this->fetch_assoc();
        while ($row) {
            $ret[$row[$key]] = $row[$value];
            $row = $this->fetch_assoc();
        }
        return $ret;
    }
}
