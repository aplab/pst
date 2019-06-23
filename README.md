# pst
PHP small tools

## Installation

Package is available on [Packagist](https://packagist.org/packages/aplab/pst),
you can install it using [Composer](http://getcomposer.org).

```shell
composer require aplab/pst dev-master
```

add to services.yaml example

```shell
    Aplab\Pst\Lib\MysqliManager\MysqliManager:
        arguments: ['@kernel']
```

## Configuration

put .mysqli_manager.ini.dist into ```projectDir``` and then rename it to .mysqli_manager.ini 


