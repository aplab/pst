<?php

declare(strict_types=1);

namespace Aplab\Pst\Lib\MysqliManager;

use Aplab\Pst\Lib\Path;
use SplFileInfo;
use Symfony\Component\HttpKernel\KernelInterface;

class MysqliManager
{
    const DEFAULT_HOST = 'localhost';

    const DEFAULT_PORT = '3306';

    const DEFAULT_CONNECTION_NAME = 'default';

    const DEFAULT_CONFIG_FILENAME = '.mysqli_manager.ini';

    protected SplFileInfo|Path $configLocation;

    protected array $configuration;

    protected array $connections = [];

    public static bool $debug = false;

    public function __construct(KernelInterface $kernel)
    {
        $this->configLocation = new SplFileInfo(
            (new Path(
                $kernel->getProjectDir(),
                static::DEFAULT_CONFIG_FILENAME
            ))->toString()
        );
    }

    public function getConfiguration(): false|array
    {
        return parse_ini_file($this->configLocation->getRealPath(), true, INI_SCANNER_TYPED);
    }

    public function getConnection($name = self::DEFAULT_CONNECTION_NAME): Connection
    {
        return $this->connections[$name] ?? new Connection($this, $name);
    }
}
