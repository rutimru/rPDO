<?php
namespace rPDO\Console;

use rPDO\Console\Command\ParseSchema;
use rPDO\Console\Command\WriteSchema;

class Application extends \Symfony\Component\Console\Application
{
    protected static $name = 'rPDO Console';
    protected static $version = '1.0.0';

    public function __construct(){
        parent::__construct(self::$name, self::$version);
    }

    public function loadCommands()
    {
        $this->add(new ParseSchema());
        $this->add(new WriteSchema());
    }
}