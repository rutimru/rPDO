<?php
/**
 * This file is part of the rPDO package.
 *
 * Copyright (c) Jason Coward <jason@opengeek.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace rPDO\Om\mysql;

use rPDO\rPDO;

/**
 * Provides MySQL data source management for an rPDO instance.
 *
 * These are utility functions that only need to be loaded under special
 * circumstances, such as creating tables, adding indexes, altering table
 * structures, etc.  rPDOManager class implementations are specific to a
 * database driver and this instance is implemented for MySQL.
 *
 * @package rPDO
 * @subpackage om.mysql
 */
class rPDOManager extends \rPDO\Om\rPDOManager {
    public function createSourceContainer($dsnArray = null, $username= null, $password= null, $containerOptions= array ()) {
        $created= false;
        if ($this->rpdo->getConnection(array(rPDO::OPT_CONN_MUTABLE => true))) {
            if ($dsnArray === null) $dsnArray = rPDO::parseDSN($this->rpdo->getOption('dsn'));
            if ($username === null) $username = $this->rpdo->getOption('username', null, '');
            if ($password === null) $password = $this->rpdo->getOption('password', null, '');
            if (is_array($dsnArray) && is_string($username) && is_string($password)) {
                $sql= 'CREATE DATABASE `' . $dsnArray['dbname'] . '`';
                $charset = $this->rpdo->getOption('charset', $containerOptions);
                $collation = $this->rpdo->getOption('collation', $containerOptions);
                if (!empty($charset)) {
                    $sql .= ' CHARACTER SET ' . $charset;
                }
                if (!empty($collation)) {
                    $sql.= ' COLLATE ' . $collation;
                }
                try {
                    $pdo = new \PDO("mysql:host={$dsnArray['host']}", $username, $password, array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));
                    $result = $pdo->exec($sql);
                    if ($result !== false) {
                        $created = true;
                    } else {
                        $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not create source container:\n{$sql}\nresult = " . var_export($result, true));
                    }
                } catch (\PDOException $pe) {
                    $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not connect to database server: " . $pe->getMessage());
                } catch (\Exception $e) {
                    $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not create source container: " . $e->getMessage());
                }
            }
        } else {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $created;
    }

    public function removeSourceContainer($dsnArray = null, $username= null, $password= null) {
        $removed= false;
        if ($this->rpdo->getConnection(array(rPDO::OPT_CONN_MUTABLE => true))) {
            if ($dsnArray === null) $dsnArray = rPDO::parseDSN($this->rpdo->getOption('dsn'));
            if ($username === null) $username = $this->rpdo->getOption('username', null, '');
            if ($password === null) $password = $this->rpdo->getOption('password', null, '');
            if (is_array($dsnArray) && is_string($username) && is_string($password)) {
                $sql= 'DROP DATABASE IF EXISTS ' . $this->rpdo->escape($dsnArray['dbname']);
                try {
                    $pdo = new \PDO("mysql:host={$dsnArray['host']}", $username, $password, array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));
                    $result = $pdo->exec($sql);
                    if ($result !== false) {
                        $removed = true;
                    } else {
                        $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not remove source container:\n{$sql}\nresult = " . var_export($result, true));
                    }
                } catch (\PDOException $pe) {
                    $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not connect to database server: " . $pe->getMessage());
                } catch (\Exception $e) {
                    $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not remove source container: " . $e->getMessage());
                }
            }
        } else {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $removed;
    }

    public function removeObjectContainer($className) {
        $removed= false;
        if ($this->rpdo->getConnection(array(rPDO::OPT_CONN_MUTABLE => true))) {
            $instance= $this->rpdo->newObject($className);
            if ($instance) {
                $sql= 'DROP TABLE ' . $this->rpdo->getTableName($className);
                $removed= $this->rpdo->exec($sql);
                if ($removed === false && $this->rpdo->errorCode() !== '' && $this->rpdo->errorCode() !== \PDO::ERR_NONE) {
                    $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, 'Could not drop table ' . $className . "\nSQL: {$sql}\nERROR: " . print_r($this->rpdo->pdo->errorInfo(), true));
                } else {
                    $removed= true;
                    $this->rpdo->log(rPDO::LOG_LEVEL_INFO, 'Dropped table' . $className . "\nSQL: {$sql}\n");
                }
            }
        } else {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $removed;
    }

    public function createObjectContainer($className) {
        $created= false;
        if ($this->rpdo->getConnection(array(rPDO::OPT_CONN_MUTABLE => true))) {
            $instance= $this->rpdo->newObject($className);
            if ($instance) {
                $tableName= $this->rpdo->getTableName($className);
                $existsStmt = $this->rpdo->query("SELECT COUNT(*) FROM {$tableName}");
                if ($existsStmt && $existsStmt->fetchAll()) {
                    return true;
                }
                $modelVersion= $this->rpdo->getModelVersion($className);
                $tableMeta= $this->rpdo->getTableMeta($className);
                $tableType= isset($tableMeta['engine']) ? $tableMeta['engine'] : 'InnoDB';
                $tableType= $this->rpdo->getOption(rPDO::OPT_OVERRIDE_TABLE_TYPE, null, $tableType);
                $legacyIndexes= version_compare($modelVersion, '1.1', '<');
                $fulltextIndexes= array ();
                $uniqueIndexes= array ();
                $stdIndexes= array ();
                $sql= 'CREATE TABLE ' . $tableName . ' (';
                $fieldMeta = $this->rpdo->getFieldMeta($className, true);
                $columns = array();
                foreach ($fieldMeta as $key => $meta) {
                    $columns[] = $this->getColumnDef($className, $key, $meta);
                    /* Legacy index support for pre-2.0.0-rc3 models */
                    if ($legacyIndexes && isset ($meta['index']) && $meta['index'] !== 'pk') {
                        if ($meta['index'] === 'fulltext') {
                            if (isset ($meta['indexgrp'])) {
                                $fulltextIndexes[$meta['indexgrp']][]= $key;
                            } else {
                                $fulltextIndexes[$key]= $key;
                            }
                        }
                        elseif ($meta['index'] === 'unique') {
                            if (isset ($meta['indexgrp'])) {
                                $uniqueIndexes[$meta['indexgrp']][]= $key;
                            } else {
                                $uniqueIndexes[$key]= $key;
                            }
                        }
                        elseif ($meta['index'] === 'fk') {
                            if (isset ($meta['indexgrp'])) {
                                $stdIndexes[$meta['indexgrp']][]= $key;
                            } else {
                                $stdIndexes[$key]= $key;
                            }
                        } else {
                            if (isset ($meta['indexgrp'])) {
                                $stdIndexes[$meta['indexgrp']][]= $key;
                            } else {
                                $stdIndexes[$key]= $key;
                            }
                        }
                    }
                }
                $sql .= implode(', ', $columns);
                if (!$legacyIndexes) {
                    $indexes = $this->rpdo->getIndexMeta($className);
                    $tableConstraints = array();
                    if (!empty ($indexes)) {
                        foreach ($indexes as $indexkey => $indexdef) {
                            $tableConstraints[] = $this->getIndexDef($className, $indexkey, $indexdef);
                        }
                    }
                } else {
                    /* Legacy index support for schema model versions 1.0 */
                    $pk= $this->rpdo->getPK($className);
                    if (is_array($pk)) {
                        $pkarray= array ();
                        foreach ($pk as $k) {
                            $pkarray[]= $this->rpdo->escape($k);
                        }
                        $pk= implode(',', $pkarray);
                    }
                    elseif ($pk) {
                        $pk= $this->rpdo->escape($pk);
                    }
                    if ($pk) {
                        $tableConstraints[]= "PRIMARY KEY ({$pk})";
                    }
                    if (!empty ($stdIndexes)) {
                        foreach ($stdIndexes as $indexkey => $index) {
                            if (is_array($index)) {
                                $indexset= array ();
                                foreach ($index as $indexmember) {
                                    $indexset[]= $this->rpdo->escape($indexmember);
                                }
                                $indexset= implode(',', $indexset);
                            } else {
                                $indexset= $this->rpdo->escape($indexkey);
                            }
                            $tableConstraints[]= "INDEX {$this->rpdo->escape($indexkey)} ({$indexset})";
                        }
                    }
                    if (!empty ($uniqueIndexes)) {
                        foreach ($uniqueIndexes as $indexkey => $index) {
                            if (is_array($index)) {
                                $indexset= array ();
                                foreach ($index as $indexmember) {
                                    $indexset[]= $this->rpdo->escape($indexmember);
                                }
                                $indexset= implode(',', $indexset);
                            } else {
                                $indexset= $this->rpdo->escape($indexkey);
                            }
                            $tableConstraints[]= "UNIQUE INDEX {$this->rpdo->escape($indexkey)} ({$indexset})";
                        }
                    }
                    if (!empty ($fulltextIndexes)) {
                        foreach ($fulltextIndexes as $indexkey => $index) {
                            if (is_array($index)) {
                                $indexset= array ();
                                foreach ($index as $indexmember) {
                                    $indexset[]= $this->rpdo->escape($indexmember);
                                }
                                $indexset= implode(',', $indexset);
                            } else {
                                $indexset= $this->rpdo->escape($indexkey);
                            }
                            $tableConstraints[]= "FULLTEXT INDEX {$this->rpdo->escape($indexkey)} ({$indexset})";
                        }
                    }
                }
                if (!empty($tableConstraints)) {
                    $sql .= ', ' . implode(', ', $tableConstraints);
                }
                $sql .= ")";
                if (!empty($tableType)) {
                    $sql .= " ENGINE={$tableType}";
                }
                $created= $this->rpdo->exec($sql);
                if ($created === false && $this->rpdo->errorCode() !== '' && $this->rpdo->errorCode() !== \PDO::ERR_NONE) {
                    $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, 'Could not create table ' . $tableName . "\nSQL: {$sql}\nERROR: " . print_r($this->rpdo->errorInfo(), true));
                } else {
                    $created= true;
                    $this->rpdo->log(rPDO::LOG_LEVEL_INFO, 'Created table ' . $tableName . "\nSQL: {$sql}\n");
                }
            }
        } else {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $created;
    }

    public function alterObjectContainer($className, array $options = array()) {
        if ($this->rpdo->getConnection(array(rPDO::OPT_CONN_MUTABLE => true))) {
            // TODO: Implement alterObjectContainer() method.
        } else {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
    }

    public function addConstraint($class, $name, array $options = array()) {
        if ($this->rpdo->getConnection(array(rPDO::OPT_CONN_MUTABLE => true))) {
            // TODO: Implement addConstraint() method.
        } else {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
    }

    public function addField($class, $name, array $options = array()) {
        $result = false;
        if ($this->rpdo->getConnection(array(rPDO::OPT_CONN_MUTABLE => true))) {
            $className = $this->rpdo->loadClass($class);
            if ($className) {
                $meta = $this->rpdo->getFieldMeta($className, true);
                if (is_array($meta) && array_key_exists($name, $meta)) {
                    $colDef = $this->getColumnDef($className, $name, $meta[$name]);
                    if (!empty($colDef)) {
                        $sql = "ALTER TABLE {$this->rpdo->getTableName($className)} ADD COLUMN {$colDef}";
                        if (isset($options['first']) && !empty($options['first'])) {
                            $sql .= " FIRST";
                        } elseif (isset($options['after']) && array_key_exists($options['after'], $meta)) {
                            $sql .= " AFTER {$this->rpdo->escape($options['after'])}";
                        }
                        if ($this->rpdo->exec($sql) !== false) {
                            $result = true;
                        } else {
                            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Error adding field {$class}->{$name}: " . print_r($this->rpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                        }
                    } else {
                        $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Error adding field {$class}->{$name}: Could not get column definition");
                    }
                } else {
                    $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Error adding field {$class}->{$name}: No metadata defined");
                }
            }
        } else {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $result;
    }

    public function addIndex($class, $name, array $options = array()) {
        $result = false;
        if ($this->rpdo->getConnection(array(rPDO::OPT_CONN_MUTABLE => true))) {
            $className = $this->rpdo->loadClass($class);
            if ($className) {
                $meta = $this->rpdo->getIndexMeta($className);
                if (is_array($meta) && array_key_exists($name, $meta)) {
                    $idxDef = $this->getIndexDef($className, $name, $meta[$name]);
                    if (!empty($idxDef)) {
                        $sql = "ALTER TABLE {$this->rpdo->getTableName($className)} ADD {$idxDef}";
                        if ($this->rpdo->exec($sql) !== false) {
                            $result = true;
                        } else {
                            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Error adding index {$name} to {$class}: " . print_r($this->rpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                        }
                    } else {
                        $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Error adding index {$name} to {$class}: Could not get index definition");
                    }
                } else {
                    $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Error adding index {$name} to {$class}: No metadata defined");
                }
            }
        } else {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $result;
    }

    public function alterField($class, $name, array $options = array()) {
        $result = false;
        if ($this->rpdo->getConnection(array(rPDO::OPT_CONN_MUTABLE => true))) {
            $className = $this->rpdo->loadClass($class);
            if ($className) {
                $meta = $this->rpdo->getFieldMeta($className, true);
                if (is_array($meta) && array_key_exists($name, $meta)) {
                    $colDef = $this->getColumnDef($className, $name, $meta[$name]);
                    if (!empty($colDef)) {
                        $sql = "ALTER TABLE {$this->rpdo->getTableName($className)} MODIFY COLUMN {$colDef}";
                        if (isset($options['first']) && !empty($options['first'])) {
                            $sql .= " FIRST";
                        } elseif (isset($options['after']) && array_key_exists($options['after'], $meta)) {
                            $sql .= " AFTER {$this->rpdo->escape($options['after'])}";
                        }
                        if ($this->rpdo->exec($sql) !== false) {
                            $result = true;
                        } else {
                            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Error altering field {$class}->{$name}: " . print_r($this->rpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                        }
                    } else {
                        $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Error altering field {$class}->{$name}: Could not get column definition");
                    }
                } else {
                    $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Error altering field {$class}->{$name}: No metadata defined");
                }
            }
        } else {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $result;
    }

    public function removeConstraint($class, $name, array $options = array()) {
        if ($this->rpdo->getConnection(array(rPDO::OPT_CONN_MUTABLE => true))) {
            // TODO: Implement removeConstraint() method.
        } else {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
    }

    public function removeField($class, $name, array $options = array()) {
        $result = false;
        if ($this->rpdo->getConnection(array(rPDO::OPT_CONN_MUTABLE => true))) {
            $className = $this->rpdo->loadClass($class);
            if ($className) {
                $sql = "ALTER TABLE {$this->rpdo->getTableName($className)} DROP COLUMN {$this->rpdo->escape($name)}";
                if ($this->rpdo->exec($sql) !== false) {
                    $result = true;
                } else {
                    $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Error removing field {$class}->{$name}: " . print_r($this->rpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                }
            }
        } else {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $result;
    }

    public function removeIndex($class, $name, array $options = array()) {
        $result = false;
        if ($this->rpdo->getConnection(array(rPDO::OPT_CONN_MUTABLE => true))) {
            $className = $this->rpdo->loadClass($class);
            if ($className) {
                $sql = "ALTER TABLE {$this->rpdo->getTableName($className)} DROP INDEX {$this->rpdo->escape($name)}";
                if ($this->rpdo->exec($sql) !== false) {
                    $result = true;
                } else {
                    $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Error removing index {$name} from {$class}: " . print_r($this->rpdo->errorInfo(), true), '', __METHOD__, __FILE__, __LINE__);
                }
            }
        } else {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Could not get writable connection", '', __METHOD__, __FILE__, __LINE__);
        }
        return $result;
    }

    protected function getColumnDef($class, $name, $meta, array $options = array()) {
        $pk= $this->rpdo->getPK($class);
        $pktype= $this->rpdo->getPKType($class);
        $dbtype= strtoupper($meta['dbtype']);
        $lobs= array ('TEXT', 'BLOB');
        $lobsPattern= '/(' . implode('|', $lobs) . ')/';
        $datetimeStrings= array('timestamp', 'datetime');
        $precision= isset ($meta['precision']) ? '(' . $meta['precision'] . ')' : '';
        $notNull= !isset ($meta['null']) ? false : ($meta['null'] === 'false' || empty($meta['null']));
        $null= $notNull ? ' NOT NULL' : ' NULL';
        $extra= '';
        if (isset($meta['index']) && $meta['index'] == 'pk' && !is_array($pk) && $pktype == 'integer' && isset ($meta['generated']) && $meta['generated'] == 'native') {
            $extra= ' AUTO_INCREMENT';
        }
        if (empty ($extra) && isset ($meta['extra'])) {
            $extra= ' ' . $meta['extra'];
        }
        $default= '';
        if (isset ($meta['default']) && !preg_match($lobsPattern, $dbtype)) {
            $defaultVal= $meta['default'];
            if (($defaultVal === null || strtoupper($defaultVal) === 'NULL') || (in_array($this->rpdo->driver->getPhpType($dbtype), $datetimeStrings) && $defaultVal === 'CURRENT_TIMESTAMP')) {
                $default= ' DEFAULT ' . $defaultVal;
            } else {
                $default= ' DEFAULT ' . $this->rpdo->quote($defaultVal, \PDO::PARAM_STR);
            }
        }
        $attributes= (isset ($meta['attributes'])) ? ' ' . $meta['attributes'] : '';
        if (strpos(strtolower($attributes), 'unsigned') !== false) {
            $result = $this->rpdo->escape($name) . ' ' . $dbtype . $precision . $attributes . $null . $default . $extra;
        } else {
            $result = $this->rpdo->escape($name) . ' ' . $dbtype . $precision . $null . $default . $attributes . $extra;
        }
        return $result;
    }

    protected function getIndexDef($class, $name, $meta, array $options = array()) {
        $result = '';
        if (isset($meta['type']) && $meta['type'] == 'FULLTEXT') {
            $indexType = 'FULLTEXT';
        } else if ( ! empty($meta['primary'])) {
            $indexType = 'PRIMARY KEY';
        } else if ( ! empty($meta['unique'])) {
            $indexType = 'UNIQUE KEY';
        } else {
            $indexType = 'INDEX';
        }
        $index = $meta['columns'];
        if (is_array($index)) {
            $indexset= array ();
            foreach ($index as $indexmember => $indexmemberdetails) {
                $indexMemberDetails = $this->rpdo->escape($indexmember);
                if (isset($indexmemberdetails['length']) && !empty($indexmemberdetails['length'])) {
                    $indexMemberDetails .= " ({$indexmemberdetails['length']})";
                }
                $indexset[]= $indexMemberDetails;
            }
            $indexset= implode(',', $indexset);
            if (!empty($indexset)) {
                switch ($indexType) {
                    case 'PRIMARY KEY':
                        $result= "{$indexType} ({$indexset})";
                        break;
                    default:
                        $result= "{$indexType} {$this->rpdo->escape($name)} ({$indexset})";
                        break;
                }
            }
        }
        return $result;
    }
}
