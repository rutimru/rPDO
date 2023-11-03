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

/**
 * Предоставляет реализацию xPDOCache на базе wincache.
 *
 * Для этого требуется расширение wincache для PHP версии 1.1.0 или более поздней. Более ранние версии
 * не было пользовательских методов кэширования.
 *
 * @package rPDO\Cache
 */
class rPDOWinCache extends rPDOCache {
    public function __construct(& $xpdo, $options = array()) {
        parent :: __construct($xpdo, $options);
        if (function_exists('wincache_ucache_info')) {
            $this->initialized = true;
        } else {
            $this->vpdo->log(rPDO::LOG_LEVEL_ERROR, "rPDOWinCache[{$this->key}]: Error creating wincache provider; rPDOWinCache requires the PHP wincache extension, version 1.1.0 or later.");
        }
    }

    public function add($key, $var, $expire= 0, $options= array()) {
        $added= wincache_ucache_add(
            $this->getCacheKey($key),
            $var,
            $expire
        );
        return $added;
    }

    public function set($key, $var, $expire= 0, $options= array()) {
        $set= wincache_ucache_set(
            $this->getCacheKey($key),
            $var,
            $expire
        );
        return $set;
    }

    public function replace($key, $var, $expire= 0, $options= array()) {
        $replaced = false;
        if (wincache_ucache_exists($key)) {
            $replaced= wincache_ucache_set(
                $this->getCacheKey($key),
                $var,
                $expire
            );
        }
        return $replaced;
    }

    public function delete($key, $options= array()) {
        $deleted = false;
        if ($this->getOption(rPDO::OPT_CACHE_MULTIPLE_OBJECT_DELETE, $options, false)) {
            $deleted= $this->flush($options);
        } else {
            $deleted= wincache_ucache_delete($this->getCacheKey($key));
        }

        return $deleted;
    }

    public function get($key, $options= array()) {
        $value= wincache_ucache_get($this->getCacheKey($key));
        return $value;
    }

    public function flush($options= array()) {
        return wincache_ucache_clear();
    }
}
