# rPDO O/RB v1

[![Build Status](https://github.com/modxcms/xpdo/workflows/CI/badge.svg?branch=3.x)](https://github.com/modxcms/xpdo/workflows/CI/badge.svg?branch=3.x)

rPDO - это сверхлегкая объектно-реляционная мостовая библиотека для PHP. Это автономная библиотека, и ее можно использовать с любым фреймворком или контейнером DI.

## Установка

rPDO можно установить в ваш проект с помощью composer:

    composer require rutim/vpdo


## Использование

Класс `\rPDO\rPDO` является основной точкой доступа к фреймворку. Предоставьте массив конфигурации, описывающий соединения, которые вы хотите установить при создании экземпляра класса.

```php
require __DIR__ . '/../vendor/autoload.php';

$xpdoMySQL = \rPDO\rPDO::getInstance('aMySQLDatabase', [
    \rPDO\rPDO::OPT_CACHE_PATH => __DIR__ . '/../cache/',
    \rPDO\rPDO::OPT_HYDRATE_FIELDS => true,
    \rPDO\rPDO::OPT_HYDRATE_RELATED_OBJECTS => true,
    \rPDO\rPDO::OPT_HYDRATE_ADHOC_FIELDS => true,
    \rPDO\rPDO::OPT_CONNECTIONS => [
        [
            'dsn' => 'mysql:host=localhost;dbname=xpdotest;charset=utf8',
            'username' => 'test',
            'password' => 'test',
            'options' => [
                \rPDO\rPDO::OPT_CONN_MUTABLE => true,
            ],
            'driverOptions' => [],
        ],
    ],
]);
```
