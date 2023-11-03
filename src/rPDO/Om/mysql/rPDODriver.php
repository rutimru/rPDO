<?php
/**
 * Этот файл является частью пакета rPDO.
 *
 * Авторское право (c) Vitaly Surkov <surkov@rutim.ru>
 *
 * Для получения полной информации об авторских правах и лицензии, пожалуйста, ознакомьтесь с LICENSE
 * файл, который был распространен с этим исходным кодом.
 */

namespace rPDO\Om\mysql;

use rPDO\rPDO;

/**
 * Предоставляет абстракцию драйвера mysql для экземпляра rPDO.
 *
 * Это базовые метаданные и методы, используемые во всей платформе. rPDODriver - драйвер rPDODriver
 * реализации класса специфичны для драйвера PDO, и этот экземпляр
 * реализован для mysql.
 *
 * @package rPDO\Om\mysql
 */
class rPDODriver extends \rPDO\Om\rPDODriver {
    public $quoteChar = "'";
    public $escapeOpenChar = '`';
    public $escapeCloseChar = '`';
    public $_currentTimestamps= array (
        'CURRENT_TIMESTAMP',
        'CURRENT_TIMESTAMP()',
        'NOW()',
        'LOCALTIME',
        'LOCALTIME()',
        'LOCALTIMESTAMP',
        'LOCALTIMESTAMP()',
        'SYSDATE()'
    );
    public $_currentDates= array (
        'CURDATE()',
        'CURRENT_DATE',
        'CURRENT_DATE()'
    );
    public $_currentTimes= array (
        'CURTIME()',
        'CURRENT_TIME',
        'CURRENT_TIME()'
    );

    /**
     * Получите экземпляр mysql rPDODriver.
     *
     * @param rPDO &$rpdo Ссылка на конкретный экземпляр rPDO.
     */
    function __construct(rPDO &$rpdo) {
        parent :: __construct($rpdo);
        $this->dbtypes['integer']= array('/INT/i');
        $this->dbtypes['boolean']= array('/^BOOL/i');
        $this->dbtypes['float']= array('/^DEC/i','/^NUMERIC$/i','/^FLOAT$/i','/^DOUBLE/i','/^REAL/i');
        $this->dbtypes['string']= array('/CHAR/i','/TEXT/i','/^ENUM$/i','/^SET$/i','/^TIME$/i','/^YEAR$/i');
        $this->dbtypes['timestamp']= array('/^TIMESTAMP$/i');
        $this->dbtypes['datetime']= array('/^DATETIME$/i');
        $this->dbtypes['date']= array('/^DATE$/i');
        $this->dbtypes['binary']= array('/BINARY/i','/BLOB/i');
        $this->dbtypes['bit']= array('/^BIT$/i');
    }
}
