<?php namespace Aplab\Pst\Lib;

use Exception;
use InvalidArgumentException;

class Path
{
    protected string $path;

    public function __construct()
    {
        $tmp = array();
        $a = func_get_args();
        if (empty($a)) {
            $msg = 'Path cannot be empty';
            throw new InvalidArgumentException($msg);
        }
        array_walk_recursive($a, function ($v) use (& $tmp) {
            $tmp[] = strval($v);
        });
        $tmp = join('/', $tmp);
        $tmp = Tools::normalize_path($tmp);
        $this->path = $tmp;
    }

    public function toString(): string
    {
        return $this->path;
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    public function normalize(): string
    {
        $this->path = Tools::normalize_path($this->path);
        return $this->path;
    }

    public function absolutize(): string
    {
        $this->path = Tools::absolute_path($this->path);
        return $this->path;
    }

    /**
     * Содержит то что передано в параметре
     *
     * @param mixed
     * @return boolean
     */
    public function contain(): bool
    {
        $param = new self(func_get_args());
        $param = $param->toArray();
        $path = $this->toArray();
        return sizeof(array_intersect_assoc($path, $param)) === sizeof($param);
    }

    /**
     * Содержит то что передано в параметре
     *
     * @param mixed
     * @return boolean
     */
    public function containedIn()
    {
        $param = new self(func_get_args());
        $param = $param->toArray();
        $path = $this->toArray();
        return sizeof(array_intersect_assoc($param, $path)) === sizeof($path);
    }

    public function toArray(): array
    {
        return explode('/', $this->path);
    }

    public function isDir(): bool
    {
        return is_dir($this->path);
    }

    public function isFile(): bool
    {
        return is_file($this->path);
    }

    public function isLink(): bool
    {
        return is_link($this->path);
    }

    public function fileExists(): bool
    {
        clearstatcache();
        return file_exists($this->path);
    }

    /**
     * clearstatcache() не требуется, функция unlink() очистит данный кэш
     * автоматически. http://ru2.php.net/manual/ru/function.clearstatcache.php
     *
     * @return boolean
     * @throws Exception
     */
    public function unlink(): bool
    {
        if (!$this->fileExists()) {
            return true;
        }
        unlink($this->path);
        if ($this->fileExists()) {
            $msg = 'Unable to unlink: ' . $this->path;
            throw new Exception($msg);
        }
        return true;
    }

    public function dirname(): Path
    {
        return new self(dirname($this->path));
    }

    public function substract(): Path
    {
        $param = new self(func_get_args());
        if (!$this->contain($param)) {
            return new self($this);
        }
        return new self(array_diff_assoc($this->toArray(), $param->toArray()));
    }

    public function extension($lcase = null): ?string
    {
        $extension = pathinfo($this->path, PATHINFO_EXTENSION);
        return $extension ? ($lcase ? strtolower($extension) : $extension) : null;
    }
}
