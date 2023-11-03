<?php
/**
 * Этот файл является частью пакета rPDO.
 *
 * Авторское право (c) Vitaly Surkov <surkov@rutim.ru>
 *
 * Для получения полной информации об авторских правах и лицензии, пожалуйста, ознакомьтесь с LICENSE
 * файл, который был распространен с этим исходным кодом.
 */

namespace rPDO;

use ArrayAccess;

class rPDOMap implements ArrayAccess
{
    /**
     * @var array An object/relational map by class.
     */
    private $map;
    /**
     * @var rPDO The xPDO instance that owns this map.
     */
    private $rpdo;

    public function __construct(rPDO &$rpdo)
    {
        $this->map = [];
        $this->rpdo =& $rpdo;
    }

    #[\ReturnTypeWillChange]
    public function offsetExists($offset): bool
    {
        if (!isset($this->map[$offset])) {
            $this->_checkClass($offset);
        }
        return isset($this->map[$offset]);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        if (!isset($this->map[$offset])) {
            $this->_checkClass($offset);
        }
        return $this->map[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $this->map[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->map[$offset]);
    }

    private function _checkClass($class)
    {
        $driverClass = $this->rpdo->getDriverClass($class);
        if ($driverClass !== false && isset($driverClass::$metaMap)) {
            $this->map[$class] = $driverClass::$metaMap;
        }
    }
}
