<?php
/**
 * Этот файл является частью пакета rPDO.
 *
 * Авторское право (c) Vitaly Surkov <surkov@rutim.ru>
 *
 * Для получения полной информации об авторских правах и лицензии, пожалуйста, ознакомьтесь с LICENSE
 * файл, который был распространен с этим исходным кодом.
 */

namespace rPDO\Cache;

use rPDO\rPDO;
use Memcached;

/**
 * Предоставляет реализацию rPDOCache на базе memcached.
 *
 * Для этого требуется расширение memcached для PHP.
 *
 * @package rPDO\Cache
 */
class rPDOMemCached extends rPDOCache {
    protected $memcached = null;

    public function __construct(& $rpdo, $options = array()) {
        parent :: __construct($rpdo, $options);
        if (class_exists('Memcached', true)) {
            $this->memcached = new Memcached();
            if ($this->memcached) {
                $servers = explode(',', $this->getOption($this->key . '_memcached_server', $options, $this->getOption('memcached_server', $options, 'localhost:11211')));
                foreach ($servers as $server) {
                    $server = explode(':', $server);
                    $this->memcached->addServer($server[0], (integer) $server[1]);
                }
                $this->memcached->setOption(Memcached::OPT_COMPRESSION, (boolean) $this->getOption($this->key . '_memcached_compression', $options, $this->getOption('memcached_compression', $options, $this->getOption(Memcached::OPT_COMPRESSION, $options, true))));
                $this->initialized = true;
            } else {
                $this->memcached = null;
                $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "xPDOMemCached[{$this->key}]: Error creating memcached provider for server(s): " . $this->getOption($this->key . '_memcached_server', $options, $this->getOption('memcached_server', $options, 'localhost:11211')));
            }
        } else {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "xPDOMemCached[{$this->key}]: Error creating memcached provider; xPDOMemCached requires the PHP memcached extension.");
        }
    }

    public function add($key, $var, $expire= 0, $options= array()) {
        $added= $this->memcached->add(
            $this->getCacheKey($key),
            $var,
            $expire
        );
        return $added;
    }

    public function set($key, $var, $expire= 0, $options= array()) {
        $set= $this->memcached->set(
            $this->getCacheKey($key),
            $var,
            $expire
        );
        return $set;
    }

    public function replace($key, $var, $expire= 0, $options= array()) {
        $replaced= $this->memcached->replace(
            $this->getCacheKey($key),
            $var,
            $expire
        );
        return $replaced;
    }

    public function delete($key, $options= array()) {
        if ($this->getOption(rPDO::OPT_CACHE_MULTIPLE_OBJECT_DELETE, $options, false)) {
            $deleted= $this->flush($options);
        } else {
            $deleted= $this->memcached->delete($this->getCacheKey($key));
        }

        return $deleted;
    }

    public function get($key, $options= array()) {
        return $this->memcached->get($this->getCacheKey($key));
    }

    public function flush($options= array()) {
        return $this->memcached->flush();
    }
}
