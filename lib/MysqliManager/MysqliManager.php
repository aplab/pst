<?php
/**
 * Created by PhpStorm.
 * User: polyanin
 * Date: 13.09.2018
 * Time: 15:41
 */

namespace Aplab\Pst\Lib\MysqliManager;


use Aplab\Pst\Lib\Path;
use SplFileInfo;
use Symfony\Component\HttpKernel\KernelInterface;

class MysqliManager
{
    /**
     * @var string
     */
    const DEFAULT_HOST = 'localhost';

    /**
     * @var string
     */
    const DEFAULT_PORT = '3306';

    /**
     * @var string
     */
    const DEFAULT_CONNECTION_NAME = 'default';

    /**
     * @var string
     */
    const DEFAULT_CONFIG_FILENAME = '.mysqli_manager.ini';

    /**
     * @var Path
     */
    protected $configLocation;

    /**
     * @var array
     */
    protected $configuration;

    /**
     * @var Connection[]
     */
    protected $connections = [];

    /**
     * Debug flag
     * @var bool
     */
    public static $debug = false;

    /**
     * MysqliManager constructor.
     * @param KernelInterface $kernel
     */
    public function __construct(KernelInterface $kernel)
    {
        /** @noinspection PhpUndefinedMethodInspection */
        $this->configLocation = new SplFileInfo(
            new Path(
                $kernel->getProjectDir(),
                static::DEFAULT_CONFIG_FILENAME
            )
        );
    }

    /**
     * @return array|bool
     */
    public function getConfiguration()
    {
        return parse_ini_file($this->configLocation->getRealPath(), true, INI_SCANNER_RAW);
    }

    /**
     * @param string $name
     * @return Connection
     */
    public function getConnection($name = self::DEFAULT_CONNECTION_NAME)
    {
        return $this->connections[$name] ?? new Connection($this, $name);
    }
}
