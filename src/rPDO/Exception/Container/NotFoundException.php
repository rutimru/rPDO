<?php
/**
 * Этот файл является частью пакета rPDO.
 *
 * Авторское право (c) Vitaly Surkov <surkov@rutim.ru>
 *
 * Для получения полной информации об авторских правах и лицензии, пожалуйста, ознакомьтесь с LICENSE
 * файл, который был распространен с этим исходным кодом.
 */

namespace rPDO\Exception\Container;


use Psr\Container\NotFoundExceptionInterface;
use rPDO\rPDOException;

class NotFoundException extends rPDOException implements NotFoundExceptionInterface {}
