<?php
/**
 * Этот файл является частью пакета rPDO.
 *
 * Авторское право (c) Vitaly Surkov <surkov@rutim.ru>
 *
 * Для получения полной информации об авторских правах и лицензии, пожалуйста, ознакомьтесь с LICENSE
 * файл, который был распространен с этим исходным кодом.
 */

namespace rPDO\Om;

use rPDO\rPDO;

/**
 * Предоставляет элементы и методы, специфичные для драйвера, для экземпляра rPDO.
 *
 * Это базовые элементы и методы, которые необходимо загружать каждый раз
 * во время установления соединения экземпляром rPDO. Реализации класса rPDODriver
 * специфичны для драйвера базы данных и должны включать этот базовый класс для того, чтобы
 * чтобы расширить его.
 *
 * @package rPDO\Om
 */
abstract class rPDODriver {
    /**
     * @var rPDO Ссылка на экземпляр rPDO, использующий этот менеджер.
     * @access public
     */
    public $rpdo= null;
    /**
     * @var array Описывает физические типы баз данных.
     */
    public $dbtypes= array ();
    /**
     * Массив констант/функций базы данных, которые представляют значения временных меток.
     * @var array
     */
    public $_currentTimestamps= array();
    /**
     * Массив констант/функций базы данных, представляющих значения дат.
     * @var array
     */
    public $_currentDates= array();
    /**
     * Массив констант/функций базы данных, которые представляют значения времени.
     * @var array
     */
    public $_currentTimes= array();
    public $quoteChar = '';
    public $escapeOpenChar = '';
    public $escapeCloseChar = '';

    /**
     * Получите экземпляр rPDODriver.
     *
     * @param rPDO $rpdo Ссылка на конкретный экземпляр rPDO.
     */
    public function __construct(rPDO &$rpdo) {
        if ($rpdo !== null && $rpdo instanceof rPDO) {
            $this->rpdo= & $rpdo;
            $this->rpdo->_quoteChar= $this->quoteChar;
            $this->rpdo->_escapeCharOpen= $this->escapeOpenChar;
            $this->rpdo->_escapeCharClose= $this->escapeCloseChar;
        }
    }

    /**
     * Возвращает тип поля PHP на основе указанного типа базы данных.
     *
     * @access public
     * @param string $dbtype Тип поля базы данных для преобразования.
     * @return string Связанный с ним тип PHP
     */
    public function getPhpType($dbtype) {
        $phptype = 'string';
        if ($dbtype !== null) {
            foreach ($this->dbtypes as $type => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $dbtype)) {
                        $phptype = $type;
                        break 2;
                    }
                }
            }
        }
        return $phptype;
    }
}
