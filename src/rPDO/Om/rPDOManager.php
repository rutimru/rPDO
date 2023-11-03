<?php
/**
 * This file is part of the rPDO package.
 *
 * Copyright (c) Jason Coward <jason@opengeek.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace rPDO\Om;

use rPDO\rPDO;
use rPDO\Transport\rPDOTransport;

/**
 * Provides data source management for an rPDO instance.
 *
 * These are utility functions that only need to be loaded under special
 * circumstances, such as creating tables, adding indexes, altering table
 * structures, etc.  rPDOManager class implementations are specific to a
 * database driver and should include this base class in order to extend it.
 *
 * @package rPDO\Om
 */
abstract class rPDOManager {
    /**
     * @var rPDO A reference to the rPDO instance using this manager.
     * @access public
     */
    public $vpdo= null;
    /**
     * @var rPDOGenerator The generator class for forward and reverse
     * engineering tasks (loaded only on demand).
     */
    public $generator= null;
    /**
     * @var rPDOTransport The data transport class for migrating data.
     */
    public $transport= null;

    /**
     * Get a rPDOManager instance.
     *
     * @param rPDO &$vpdo A reference to a specific rPDO instance.
     */
    public function __construct(& $vpdo) {
        if ($vpdo !== null && $vpdo instanceof rPDO) {
            $this->vpdo= & $vpdo;
        }
    }

    /**
     * Creates the physical container representing a data source.
     *
     * @param array|null $dsnArray An array of rPDO configuration properties.
     * @param string|null $username Database username with privileges to create tables.
     * @param string|null $password Database user password.
     * @param array $containerOptions An array of options for controlling the creation of the container.
     * @return boolean True if the database is created successfully or already exists.
     */
    abstract public function createSourceContainer($dsnArray = null, $username= null, $password= null, $containerOptions= array());

    /**
     * Drops a physical data source container, if it exists.
     *
     * @param string|null $dsnArray Represents the database connection string.
     * @param string|null $username Database username with privileges to drop tables.
     * @param string|null $password Database user password.
     * @return bool Returns true on successful drop, false on failure.
     */
    abstract public function removeSourceContainer($dsnArray = null, $username= null, $password= null);

    /**
     * Creates the container for a persistent data object.
     *
     * An object container is a synonym for a database table.
     *
     * @param string $className The class of object to create a source container for.
     * @return boolean Returns true on successful creation, false on failure.
     */
    abstract public function createObjectContainer($className);

    /**
     * Alter the structure of an existing persistent data object container.
     *
     * @param string $className The class of object to alter the container of.
     * @param array $options An array of options describing the alterations to be made.
     * @return boolean Returns true on successful alteration, false on failure.
     */
    abstract public function alterObjectContainer($className, array $options = array());

    /**
     * Drop an object container (i.e. database table), if it exists.
     *
     * @param string $className The object container to drop.
     * @return boolean Returns true on successful drop, false on failure.
     */
    abstract public function removeObjectContainer($className);

    /**
     * Add a field to an object container, e.g. ADD COLUMN.
     *
     * @param string $class The object class to add the field to.
     * @param string $name The name of the field to add.
     * @param array $options An array of options for the process.
     * @return boolean True if the column is added successfully, otherwise false.
     */
    abstract public function addField($class, $name, array $options = array());

    /**
     * Alter an existing field of an object container, e.g. ALTER COLUMN.
     *
     * @param string $class The object class to alter the field of.
     * @param string $name The name of the field to alter.
     * @param array $options An array of options for the process.
     * @return boolean True if the column is altered successfully, otherwise false.
     */
    abstract public function alterField($class, $name, array $options = array());

    /**
     * Remove a field from an object container, e.g. DROP COLUMN.
     *
     * @param string $class The object class to drop the field from.
     * @param string $name The name of the field to drop.
     * @param array $options An array of options for the process.
     * @return boolean True if the column is dropped successfully, otherwise false.
     */
    abstract public function removeField($class, $name, array $options = array());

    /**
     * Add an index to an object container, e.g. ADD INDEX.
     *
     * @param string $class The object class to add the index to.
     * @param string $name The name of the index to add.
     * @param array $options An array of options for the process.
     * @return boolean True if the index is added successfully, otherwise false.
     */
    abstract public function addIndex($class, $name, array $options = array());

    /**
     * Remove an index from an object container, e.g. DROP INDEX.
     *
     * @param string $class The object class to drop the index from.
     * @param string $name The name of the index to drop.
     * @param array $options An array of options for the process.
     * @return boolean True if the index is dropped successfully, otherwise false.
     */
    abstract public function removeIndex($class, $name, array $options = array());

    /**
     * Add a constraint to an object container, e.g. ADD CONSTRAINT.
     *
     * @param string $class The object class to add the constraint to.
     * @param string $name The name of the constraint to add.
     * @param array $options An array of options for the process.
     * @return boolean True if the constraint is added successfully, otherwise false.
     */
    abstract public function addConstraint($class, $name, array $options = array());

    /**
     * Remove a constraint from an object container, e.g. DROP CONSTRAINT.
     *
     * @param string $class The object class to drop the constraint from.
     * @param string $name The name of the constraint to drop.
     * @param array $options An array of options for the process.
     * @return boolean True if the constraint is dropped successfully, otherwise false.
     */
    abstract public function removeConstraint($class, $name, array $options = array());

    /**
     * Gets an XML schema parser / generator for this manager instance.
     *
     * @return rPDOGenerator A generator class for this manager.
     */
    public function getGenerator() {
        if ($this->generator === null || !$this->generator instanceof rPDOGenerator) {
            $generatorClass = '\\rPDO\\Om\\'  . $this->vpdo->config['dbtype'] . '\\rPDOGenerator';
            $this->generator= new $generatorClass ($this);
            if ($this->generator === null || !$this->generator instanceof rPDOGenerator) {
                $this->vpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not load rPDOGenerator [{$generatorClass}] class.");
            }
        }
        return $this->generator;
    }

    /**
     * Gets a data transport mechanism for this rPDOManager instance.
     *
     * @return rPDOTransport
     */
    public function getTransport() {
        // if ($this->transport === null || !$this->transport instanceof rPDOTransport) {
        //     $transportClass= $this->vpdo->getOption('rPDOTransport.class', null, 'rPDOTransport');
        //     $this->transport= new $transportClass($this);
        //     if ($this->transport === null || !$this->transport instanceof rPDOTransport) {
        //         $this->vpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not load rPDOTransport [{$transportClass}] class.");
        //     }
        // }
        return $this->transport;
    }

    /**
     * Get the SQL necessary to define a column for a specific database engine.
     *
     * @param string $class The name of the class the column represents a field of.
     * @param string $name The name of the field and physical database column.
     * @param string $meta The metadata defining the field.
     * @param array $options An array of options for the process.
     * @return string A string of SQL representing the column definition.
     */
    abstract protected function getColumnDef($class, $name, $meta, array $options = array());

    /**
     * Get the SQL necessary to define an index for a specific database engine.
     *
     * @param string $class The name of the class the index is defined for.
     * @param string $name The name of the index.
     * @param string $meta The metadata defining the index.
     * @param array $options An array of options for the process.
     * @return string A string of SQL representing the index definition.
     */
    abstract protected function getIndexDef($class, $name, $meta, array $options = array());
}
