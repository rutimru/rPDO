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
use rPDO\rPDOException;

/**
 * An rPDOCriteria derivative for constructing complex SQL statements using a model-aware API.
 *
 * @package rPDO\Om
 */
abstract class rPDOQuery extends rPDOCriteria {
    const SQL_AND = 'AND';
    const SQL_OR = 'OR';
    const SQL_JOIN_CROSS = 'JOIN';
    const SQL_JOIN_LEFT = 'LEFT JOIN';
    const SQL_JOIN_RIGHT = 'RIGHT JOIN';
    const SQL_JOIN_NATURAL_LEFT = 'NATURAL LEFT JOIN';
    const SQL_JOIN_NATURAL_RIGHT = 'NATURAL RIGHT JOIN';
    const SQL_JOIN_STRAIGHT = 'STRAIGHT_JOIN';

    /**
     * An array of symbols and keywords indicative of SQL operators.
     *
     * @var array
     * @todo Refactor this to separate rPDOQuery operators from db-specific conditional statement identifiers.
     */
    protected $_operators= array (
        '=',
        '!=',
        '<',
        '<=',
        '>',
        '>=',
        '<=>',
        ' LIKE ',
        ' IS NULL',
        ' IS NOT NULL',
        ' BETWEEN ',
        ' IN ',
        ' IN(',
        ' NOT(',
        ' NOT (',
        ' NOT IN ',
        ' NOT IN(',
        ' EXISTS (',
        ' EXISTS(',
        ' NOT EXISTS (',
        ' NOT EXISTS(',
        ' COALESCE(',
        ' GREATEST(',
        ' INTERVAL(',
        ' LEAST(',
        'MATCH(',
        'MATCH (',
        'MAX(',
        'MIN(',
        'AVG('
    );
    protected $_quotable= array ('string', 'password', 'date', 'datetime', 'timestamp', 'time', 'json', 'array', 'float');
    protected $_class= null;
    protected $_alias= null;
    protected $_tableClass = null;
    public $graph= array ();
    public $query= array (
        'command' => 'SELECT',
        'distinct' => '',
        'columns' => '',
        'from' => array (
            'tables' => array (),
            'joins' => array (),
        ),
        'set' => array (),
        'where' => array (),
        'groupby' => array (),
        'having' => array (),
        'orderby' => array (),
        'offset' => '',
        'limit' => '',
    );

    /**
     * Make sure a clause is valid and does not contain SQL injection attempts.
     *
     * @param string $clause The string clause to validate.
     *
     * @return bool True if the clause is valid.
     */
    public static function isValidClause($clause) {
        $output = rtrim($clause, ' ;');
        $output = preg_replace("/\\\\'.*?\\\\'/", '{mask}', $output);
        $output = preg_replace('/\\".*?\\"/', '{mask}', $output);
        $output = preg_replace("/'.*?'/", '{mask}', $output);
        $output = preg_replace('/".*?"/', '{mask}', $output);
        if (preg_match('/sleep\s*\(\s*\d+\s*\)/i', $output) > 0) {
            return false;
        }
        if (preg_match('/benchmark\s*\(\s*.+,.+\s*\)/i', $output) > 0) {
            return false;
        }
        return strpos($output, ';') === false && strpos(strtolower($output), 'union') === false;
    }

    /**
     * Construct a new rPDOQuery instance.
     *
     * @param rPDO &$rpdo
     * @param string $class
     * @param mixed|rPDOCriteria $criteria
     */
    public function __construct(& $rpdo, $class, $criteria= null) {
        parent :: __construct($rpdo);
        if ($class= $this->rpdo->loadClass($class)) {
            $this->_class= $class;
            $this->_alias= $this->rpdo->getAlias($this->_class);
            $this->_tableClass = $this->rpdo->getTableClass($this->_class);
            $this->query['from']['tables'][0]= array (
                'table' => $this->rpdo->getTableName($this->_class),
                'alias' => & $this->_alias
            );
            if ($criteria !== null) {
                if (is_object($criteria)) {
                    $this->wrap($criteria);
                }
                else {
                    $this->where($criteria);
                }
            }
        }
    }

    /**
     * Get the name of the class represented by this instance.
     *
     * @return string The class name represented by this instance.
     */
    public function getClass() {
        return $this->_class;
    }

    /**
     * Get the alias for the class represented by this instance.
     *
     * @return string The alias of the class represented by this instance.
     */
    public function getAlias() {
        return $this->_alias;
    }

    /**
     * Get the table class for the class represented by this instance.
     *
     * @return string The name of the table class.
     */
    public function getTableClass() {
        return $this->_tableClass;
    }

    /**
     * Set the type of SQL command you want to build.
     *
     * The default is SELECT, though it also supports DELETE and UPDATE.
     *
     * @param string $command The type of SQL statement represented by this object.  Default is 'SELECT'.
     * @return rPDOQuery Returns the current object for convenience.
     */
    public function command($command= 'SELECT') {
        $command= strtoupper(trim($command));
        if (preg_match('/(SELECT|UPDATE|DELETE)/', $command)) {
            $this->query['command']= $command;
            if (in_array($command, array('DELETE','UPDATE'))) $this->_alias= $this->rpdo->getTableName($this->_class);
        }
        return $this;
    }

    /**
     * Set the DISTINCT attribute of the query.
     *
     * @param null|boolean $on Defines how to set the distinct attribute:
     *  - null (default) indicates the distinct attribute should be toggled
     *  - any other value is treated as a boolean, i.e. true to set DISTINCT, false to unset
     * @return rPDOQuery Returns the current object for convenience.
     */
    public function distinct($on = null) {
        if ($on === null) {
            if (empty($this->query['distinct']) || $this->query['distinct'] !== 'DISTINCT') {
                $this->query['distinct']= 'DISTINCT';
            } else {
                $this->query['distinct']= '';
            }
        } else {
            $this->query['distinct']= $on == true ? 'DISTINCT' : '';
        }
        return $this;
    }

    /**
     * Sets a SQL alias for the table represented by the main class.
     *
     * @param string $alias An alias for the main table for the SQL statement.
     * @return rPDOQuery Returns the current object for convenience.
     */
    public function setClassAlias($alias= '') {
        $this->_alias= $alias;
        return $this;
    }

    /**
     * Specify columns to return from the SQL query.
     *
     * @param string $columns Columns to return from the query.
     * @return rPDOQuery Returns the current object for convenience.
     */
    public function select($columns= '*') {
        if (!is_array($columns)) {
            $columns= trim($columns);
            if ($columns == '*' || $columns === $this->_alias . '.*' || $columns === $this->rpdo->escape($this->_alias) . '.*') {
                $columns= $this->rpdo->getSelectColumns($this->_class, $this->_alias, $this->_alias . '_');
            }
            $columns= explode(',', $columns);
            foreach ($columns as $colKey => $column) $columns[$colKey] = trim($column);
        }
        if (is_array ($columns)) {
            if (!is_array($this->query['columns'])) {
                $this->query['columns']= $columns;
            } else {
                $this->query['columns']= array_merge($this->query['columns'], $columns);
            }
        }
        return $this;
    }

    /**
     * Specify the SET clause(s) for a SQL UPDATE query.
     *
     * @param array $values An associative array of fields and the values to set them to.
     * @return rPDOQuery Returns a reference to the current instance for convenience.
     */
    public function set(array $values) {
        $fieldMeta= $this->rpdo->getFieldMeta($this->_class);
        $fieldAliases= $this->rpdo->getFieldAliases($this->_class);
        foreach ($values as $key => $value) {
            $type= null;
            if (!array_key_exists($key, $fieldMeta)) {
                if (array_key_exists($key, $fieldAliases)) {
                    $key = $fieldAliases[$key];
                } else {
                    continue;
                }
            }
            if (array_key_exists($key, $fieldMeta)) {
                if ($value === null) {
                    $type= \PDO::PARAM_NULL;
                }
                elseif (!in_array($fieldMeta[$key]['phptype'], $this->_quotable)) {
                    $type= \PDO::PARAM_INT;
                }
                elseif (strpos($value, '(') === false && !$this->isConditionalClause($value)) {
                    $type= \PDO::PARAM_STR;
                }
                $this->query['set'][$key]= array('value' => $value, 'type' => $type);
            }
        }
        return $this;
    }

    /**
     * Join a table represented by the specified class.
     *
     * @param string $class The classname (or relation alias for aggregates and
     * composites) of representing the table to be joined.
     * @param string $alias An optional alias to represent the joined table in
     * the constructed query.
     * @param string $type The type of join to perform.  See the rPDOQuery::SQL_JOIN
     * constants.
     * @param mixed $conditions Conditions of the join specified in any rPDO
     * compatible criteria object or expression.
     * @param string $conjunction A conjunction to be applied to the condition
     * or conditions supplied.
     * @param array $binding Optional bindings to accompany the conditions.
     * @param int $condGroup An optional identifier for adding the conditions
     * to a specific set of conjoined expressions.
     * @return rPDOQuery Returns the current object for convenience.
     */
    public function join($class, $alias= '', $type= rPDOQuery::SQL_JOIN_CROSS, $conditions= array (), $conjunction= rPDOQuery::SQL_AND, $binding= null, $condGroup= 0) {
        if ($this->rpdo->loadClass($class)) {
            $alias= $alias ? $alias : $class;
            $target= & $this->query['from']['joins'];
            $targetIdx= count($target);
            $target[$targetIdx]= array (
                'table' => $this->rpdo->getTableName($class),
                'class' => $class,
                'alias' => $alias,
                'type' => $type,
                'conditions' => array ()
            );
            if (empty ($conditions)) {
                $fkMeta= $this->rpdo->getFKDefinition($this->_class, $alias);
                if ($fkMeta) {
                    $parentAlias= isset ($this->_alias) ? $this->_alias : $this->_class;
                    $local= $fkMeta['local'];
                    $foreign= $fkMeta['foreign'];
                    $conditions= $this->rpdo->escape($parentAlias) . '.' . $this->rpdo->escape($local) . ' =  ' . $this->rpdo->escape($alias) . '.' . $this->rpdo->escape($foreign);
                    if (isset($fkMeta['criteria']['local'])) {
                        $localCriteria = array();
                        if (is_array($fkMeta['criteria']['local'])) {
                            foreach ($fkMeta['criteria']['local'] as $critKey => $critVal) {
                                if (is_numeric($critKey)) {
                                    $localCriteria[] = $critVal;
                                } else {
                                    $localCriteria["{$this->_class}.{$critKey}"] = $critVal;
                                }
                            }
                        }
                        if (!empty($localCriteria)) {
                            $conditions = array($localCriteria, $conditions);
                        }
                        $foreignCriteria = array();
                        if (is_array($fkMeta['criteria']['foreign'])) {
                            foreach ($fkMeta['criteria']['foreign'] as $critKey => $critVal) {
                                if (is_numeric($critKey)) {
                                    $foreignCriteria[] = $critVal;
                                } else {
                                    $foreignCriteria["{$parentAlias}.{$critKey}"] = $critVal;
                                }
                            }
                        }
                        if (!empty($foreignCriteria)) {
                            $conditions = array($foreignCriteria, $conditions);
                        }
                    }
                }
            }
            $this->condition($target[$targetIdx]['conditions'], $conditions, $conjunction, $binding, $condGroup);
        }
        return $this;
    }

    public function innerJoin($class, $alias= '', $conditions= array (), $conjunction= rPDOQuery::SQL_AND, $binding= null, $condGroup= 0) {
        return $this->join($class, $alias, rPDOQuery::SQL_JOIN_CROSS, $conditions, $conjunction, $binding, $condGroup);
    }

    public function leftJoin($class, $alias= '', $conditions= array (), $conjunction= rPDOQuery::SQL_AND, $binding= null, $condGroup= 0) {
        return $this->join($class, $alias, rPDOQuery::SQL_JOIN_LEFT, $conditions, $conjunction, $binding, $condGroup);
    }

    public function rightJoin($class, $alias= '', $conditions= array (), $conjunction= rPDOQuery::SQL_AND, $binding= null, $condGroup= 0) {
        return $this->join($class, $alias, rPDOQuery::SQL_JOIN_RIGHT, $conditions, $conjunction, $binding, $condGroup);
    }

    /**
     * Add a FROM clause to the query.
     *
     * @param string $class The class representing the table to add.
     * @param string $alias An optional alias for the class.
     * @return rPDOQuery Returns the instance.
     */
    public function from($class, $alias= '') {
        if ($class= $this->rpdo->loadClass($class)) {
            $alias= $alias ? $alias : $class;
            $this->query['from']['tables'][]= array (
                'table' => $this->rpdo->getTableName($class),
                'alias' => $alias
            );
        }
        return $this;
    }

    /**
     * Add a condition to the query.
     *
     * @param string $target The target clause for the condition.
     * @param mixed $conditions A valid rPDO criteria expression.
     * @param string $conjunction The conjunction to use when appending this condition, i.e., AND or OR.
     * @param mixed $binding A value or PDO binding representation of a value for the condition.
     * @param integer $condGroup A numeric identifier for associating conditions into groups.
     * @return rPDOQuery Returns the instance.
     */
    public function condition(& $target, $conditions= '1', $conjunction= rPDOQuery::SQL_AND, $binding= null, $condGroup= 0) {
        $condGroup= intval($condGroup);
        if (!isset ($target[$condGroup])) $target[$condGroup]= array ();
        try {
            $target[$condGroup][] = $this->parseConditions($conditions, $conjunction);
        } catch (rPDOException $e) {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, $e->getMessage());
            $this->where("2=1");
        }
        return $this;
    }

    /**
     * Add a WHERE condition to the query.
     *
     * @param mixed $conditions A valid rPDO criteria expression.
     * @param string $conjunction The conjunction to use when appending this condition, i.e., AND or OR.
     * @param mixed $binding A value or PDO binding representation of a value for the condition.
     * @param integer $condGroup A numeric identifier for associating conditions into groups.
     * @return rPDOQuery Returns the instance.
     */
    public function where($conditions= '', $conjunction= rPDOQuery::SQL_AND, $binding= null, $condGroup= 0) {
        $this->condition($this->query['where'], $conditions, $conjunction, $binding, $condGroup);
        return $this;
    }

    public function andCondition($conditions, $binding= null, $group= 0) {
        $this->where($conditions, rPDOQuery::SQL_AND, $binding, $group);
        return $this;
    }
    public function orCondition($conditions, $binding= null, $group= 0) {
        $this->where($conditions, rPDOQuery::SQL_OR, $binding, $group);
        return $this;
    }

    /**
     * Add an ORDER BY clause to the query.
     *
     * @param string $column Column identifier to sort by.
     * @param string $direction The direction to sort by, ASC or DESC.
     * @return rPDOQuery Returns the instance.
     */
    public function sortby($column, $direction= 'ASC') {
        /* The direction can only be ASC or DESC; anything else is bogus */
        if (!in_array(strtoupper($direction), array('ASC', 'DESC', 'ASCENDING', 'DESCENDING'), true)) {
            $direction = '';
        }

        if (!static::isValidClause($column)) {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, 'SQL injection attempt detected in sortby column; clause rejected');
        } elseif (!empty($column)) {
            $this->query['sortby'][] = array('column' => $column, 'direction' => $direction);
        }
        return $this;
    }

    /**
     * Add an GROUP BY clause to the query.
     *
     * @param string $column Column identifier to group by.
     * @param string $direction The direction to sort by, ASC or DESC.
     * @return rPDOQuery Returns the instance.
     */
    public function groupby($column, $direction= '') {
        $this->query['groupby'][]= array ('column' => $column, 'direction' => $direction);
        return $this;
    }

    public function having($conditions) {
        try {
            $this->query['having'][] = $this->parseConditions((array)$conditions);
        } catch (rPDOException $e) {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, $e->getMessage());
            $this->where("2=1");
        }
        return $this;
    }

    /**
     * Add a LIMIT/OFFSET clause to the query.
     *
     * @param integer $limit The number of records to return.
     * @param integer $offset The location in the result set to start from.
     * @return rPDOQuery Returns the instance.
     */
    public function limit($limit, $offset= 0) {
        $this->query['limit']= (int)$limit;
        $this->query['offset']= (int)$offset;
        return $this;
    }

    /**
     * Bind an object graph to the query.
     *
     * @param mixed $graph An array or JSON graph of related objects.
     * @return rPDOQuery Returns the instance.
     */
    public function bindGraph($graph) {
        if (is_string($graph)) {
            $graph= $this->rpdo->fromJSON($graph);
        }
        if (is_array ($graph)) {
            if ($this->graph !== $graph) {
                $this->graph= $graph;
                $this->select($this->rpdo->getSelectColumns($this->_class, $this->_alias, $this->_alias . '_'));
                foreach ($this->graph as $relationAlias => $subRelations) {
                    $this->bindGraphNode($this->_class, $this->_alias, $relationAlias, $subRelations);
                }
                if ($pk= $this->rpdo->getPK($this->_class)) {
                    if (is_array ($pk)) {
                        foreach ($pk as $key) {
                            $this->sortby($this->rpdo->escape($this->_alias) . '.' . $this->rpdo->escape($key), 'ASC');
                        }
                    } else {
                        $this->sortby($this->rpdo->escape($this->_alias) . '.' . $this->rpdo->escape($pk), 'ASC');
                    }
                }
            }
        }
        return $this;
    }

    /**
     * Bind the node of an object graph to the query.
     *
     * @param string $parentClass The class representing the relation parent.
     * @param string $parentAlias The alias the class is assuming.
     * @param string $classAlias The class representing the related graph node.
     * @param array $relations Child relations of the current graph node.
     */
    public function bindGraphNode($parentClass, $parentAlias, $classAlias, $relations) {
        if ($fkMeta= $this->rpdo->getFKDefinition($parentClass, $classAlias)) {
            $class= $fkMeta['class'];
            $local= $fkMeta['local'];
            $foreign= $fkMeta['foreign'];
            $this->select($this->rpdo->getSelectColumns($class, $classAlias, $classAlias . '_'));
            $expression= $this->rpdo->escape($parentAlias) . '.' . $this->rpdo->escape($local) . ' = ' .  $this->rpdo->escape($classAlias) . '.' . $this->rpdo->escape($foreign);
            if (isset($fkMeta['criteria']['local'])) {
                $localCriteria = array();
                if (is_array($fkMeta['criteria']['local'])) {
                    foreach ($fkMeta['criteria']['local'] as $critKey => $critVal) {
                        if (is_numeric($critKey)) {
                            $localCriteria[] = $critVal;
                        } else {
                            $localCriteria["{$classAlias}.{$critKey}"] = $critVal;
                        }
                    }
                }
                if (!empty($localCriteria)) {
                    $expression = array($localCriteria, $expression);
                }
                $foreignCriteria = array();
                if (is_array($fkMeta['criteria']['foreign'])) {
                    foreach ($fkMeta['criteria']['foreign'] as $critKey => $critVal) {
                        if (is_numeric($critKey)) {
                            $foreignCriteria[] = $critVal;
                        } else {
                            $foreignCriteria["{$parentAlias}.{$critKey}"] = $critVal;
                        }
                    }
                }
                if (!empty($foreignCriteria)) {
                    $expression = array($foreignCriteria, $expression);
                }
            }
            $this->leftJoin($class, $classAlias, $expression);
            if (!empty ($relations)) {
                foreach ($relations as $relationAlias => $subRelations) {
                    $this->bindGraphNode($class, $classAlias, $relationAlias, $subRelations);
                }
            }
        }
    }

    /**
     * Hydrates a graph of related objects from a single result set.
     *
     * @param array|\PDOStatement $rows A collection of result set rows or an
     * executed PDOStatement to fetch rows from to hydrating the graph.
     * @param bool $cacheFlag Indicates if the objects should be cached and
     * optionally, by specifying an integer value, for how many seconds.
     * @return array A collection of objects with all related objects from the
     * graph pre-populated.
     */
    public function hydrateGraph($rows, $cacheFlag = true) {
        $instances= array ();
        $collectionCaching = $this->rpdo->getOption(rPDO::OPT_CACHE_DB_COLLECTIONS, array(), 1);
        if (is_object($rows)) {
            if ($cacheFlag && $this->rpdo->_cacheEnabled && $collectionCaching > 0) {
                $cacheRows = array();
            }
            while ($row = $rows->fetch(\PDO::FETCH_ASSOC)) {
                $this->hydrateGraphParent($instances, $row);
                if ($cacheFlag && $this->rpdo->_cacheEnabled && $collectionCaching > 0) {
                    $cacheRows[]= $row;
                }
            }
            if ($cacheFlag && $this->rpdo->_cacheEnabled && $collectionCaching > 0) {
                $this->rpdo->toCache($this, $cacheRows, $cacheFlag);
            }
        } elseif (is_array($rows)) {
            foreach ($rows as $row) {
                $this->hydrateGraphParent($instances, $row);
            }
        }
        return $instances;
    }

    public function hydrateGraphParent(& $instances, $row) {
        $hydrated = false;
        $instance = $this->rpdo->call($this->getClass(), '_loadInstance', array(& $this->rpdo, $this->getClass(), $this->getAlias(), $row));
        if (is_object($instance)) {
            $pk= $instance->getPrimaryKey();
            if (is_array($pk)) $pk= implode('-', $pk);
            if (isset ($instances[$pk])) {
                $instance= & $instances[$pk];
            }
            foreach ($this->graph as $relationAlias => $subRelations) {
                $this->hydrateGraphNode($row, $instance, $relationAlias, $subRelations);
            }
            $instances[$pk]= $instance;
            $hydrated = true;
        }
        return $hydrated;
    }

    /**
     * Hydrates a node of the object graph.
     *
     * @param array $row The result set representing the current node.
     * @param rPDOObject $instance The rPDOObject instance to be hydrated from the node.
     * @param string $alias The alias identifying the object in the parent relationship.
     * @param array $relations Child relations of the current node.
     */
    public function hydrateGraphNode(& $row, & $instance, $alias, $relations) {
        $relObj= null;
        if ($relationMeta= $instance->getFKDefinition($alias)) {
            if ($row[$alias.'_'.$relationMeta['foreign']] != null) {
                $relObj = $this->rpdo->call($relationMeta['class'], '_loadInstance', array(& $this->rpdo, $relationMeta['class'], $alias, $row));
                if ($relObj) {
                    if (strtolower($relationMeta['cardinality']) == 'many') {
                        $instance->addMany($relObj, $alias);
                    } else {
                        $instance->addOne($relObj, $alias);
                    }
                }
            }
        }
        // if (!empty($relations) && $relObj instanceof rPDOObject) {
        //     foreach ($relations as $relationAlias => $subRelations) {
        //         if (is_array($subRelations) && !empty($subRelations)) {
        //             foreach ($subRelations as $subRelation) {
        //                 $this->hydrateGraphNode($row, $relObj, $relationAlias, $subRelation);
        //             }
        //         } else {
        //             $this->hydrateGraphNode($row, $relObj, $relationAlias, null);
        //         }
        //     }
        // }
    }

    /**
     * Constructs the SQL query from the rPDOQuery definition.
     *
     * @return boolean Returns true if a SQL statement was successfully constructed.
     */
    abstract public function construct();

    /**
     * Prepares the rPDOQuery for execution.
     *
     * @param array $bindings
     * @param bool $byValue
     * @param null|int|bool $cacheFlag
     *
     * @return \PDOStatement The PDOStatement representing the prepared query.
     */
    public function prepare($bindings= array (), $byValue= true, $cacheFlag= null) {
        $this->stmt= null;
        if ($this->construct() && $this->stmt= $this->rpdo->prepare($this->sql)) {
            $this->bind($bindings, $byValue, $cacheFlag);
        } else {
            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, 'Could not construct or prepare query because it is invalid or could not connect: ' . $this->sql);
        }
        return $this->stmt;
    }

    /**
     * Parses an rPDO condition expression into one or more rPDOQueryConditions.
     *
     * @param mixed  $conditions A valid rPDO condition expression.
     * @param string $conjunction The optional conjunction for the condition( s ).
     *
     * @throws \rPDO\rPDOException
     * @return array||rPDOQueryCondition An rPDOQueryCondition or array of rPDOQueryConditions.
     */
    public function parseConditions($conditions, $conjunction = rPDOQuery::SQL_AND) {
        $result= array ();
        $pk= $this->rpdo->getPK($this->_class);
        $pktype= $this->rpdo->getPKType($this->_class);
        $fieldMeta= $this->rpdo->getFieldMeta($this->_class, true);
        $fieldAliases= $this->rpdo->getFieldAliases($this->_class);
        $command= strtoupper($this->query['command']);
        $alias= $command == 'SELECT' ? $this->_alias : $this->rpdo->getTableName($this->_class, false);
        $alias= trim($alias, $this->rpdo->_escapeCharOpen . $this->rpdo->_escapeCharClose);
        if (is_array($conditions)) {
            if (isset($conditions[0]) && is_scalar($conditions[0]) && !$this->isConditionalClause($conditions[0]) && is_array($pk) && count($conditions) == count($pk)) {
                $iteration= 0;
                foreach ($pk as $k) {
                    if (!isset ($conditions[$iteration])) {
                        $conditions[$iteration]= null;
                    }
                    $isString= in_array($fieldMeta[$k]['phptype'], $this->_quotable);
                    $field= array();
                    $field['sql']= $this->rpdo->escape($alias) . '.' . $this->rpdo->escape($k) . " = ?";
                    $field['binding']= array (
                        'value' => $conditions[$iteration],
                        'type' => $isString ? \PDO::PARAM_STR : \PDO::PARAM_INT,
                        'length' => 0
                    );
                    $field['conjunction']= $conjunction;
                    $result[$iteration]= new rPDOQueryCondition($field);
                    $iteration++;
                }
            } else {
                foreach ($conditions as $key => $val) {
                    if (is_int($key)) {
                        if (is_array($val)) {
                            $result[]= $this->parseConditions($val, $conjunction);
                            continue;
                        } elseif ($this->isConditionalClause($val)) {
                            $result[]= new rPDOQueryCondition(array('sql' => $val, 'binding' => null, 'conjunction' => $conjunction));
                            continue;
                        } else {
                            $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Error parsing condition with key {$key}: " . print_r($val, true));
                            continue;
                        }
                    } elseif (is_scalar($val) || is_array($val) || $val === null) {
                        $alias= $command == 'SELECT' ? $this->_alias : $this->rpdo->getTableName($this->_class, false);
                        $alias= trim($alias, $this->rpdo->_escapeCharOpen . $this->rpdo->_escapeCharClose);
                        $operator= '=';
                        $conj = $conjunction;
                        $key_operator= explode(':', $key);
                        if ($key_operator && count($key_operator) === 2) {
                            $key= $key_operator[0];
                            $operator= strtoupper($key_operator[1]);
                        }
                        elseif ($key_operator && count($key_operator) === 3) {
                            $conj= $key_operator[0];
                            $key= $key_operator[1];
                            $operator= strtoupper($key_operator[2]);
                        }
                        if (strpos($key, '.') !== false) {
                            $key_parts= explode('.', $key);
                            $alias= trim($key_parts[0], " {$this->rpdo->_escapeCharOpen}{$this->rpdo->_escapeCharClose}");
                            $key= $key_parts[1];
                        }
                        if (!array_key_exists($key, $fieldMeta)) {
                            if (array_key_exists($key, $fieldAliases)) {
                                $key= $fieldAliases[$key];
                            } elseif ($this->isConditionalClause($key)) {
                                continue;
                            }
                        }
                        if (!empty($key)) {
                            if ($val === null) {
                                $type= \PDO::PARAM_NULL;
                                if (!in_array($operator, array('IS', 'IS NOT'))) {
                                    $operator= $operator === '!=' ? 'IS NOT' : 'IS';
                                }
                            }
                            elseif (isset($fieldMeta[$key]) && !in_array($fieldMeta[$key]['phptype'], $this->_quotable)) {
                                $type= \PDO::PARAM_INT;
                            }
                            else {
                                $type= \PDO::PARAM_STR;
                            }
                            if (in_array($operator, array('IN', 'NOT IN')) && is_array($val)) {
                                $vals = array();
                                foreach ($val as $v) {
                                    if ($v === null) {
                                        $vals[] = null;
                                    } else {
                                        switch ($type) {
                                            case \PDO::PARAM_INT:
                                                $vals[] = (integer) $v;
                                                break;
                                            case \PDO::PARAM_STR:
                                                $vals[] = $this->rpdo->quote($v);
                                                break;
                                            default:
                                                $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Error parsing {$operator} condition with key {$key}: " . print_r($v, true));
                                                break;
                                        }
                                    }
                                }
                                if (empty($vals)) {
                                    $this->rpdo->log(rPDO::LOG_LEVEL_ERROR, "Encountered empty {$operator} condition with key {$key}");
                                }
                                $val = "(" . implode(',', $vals) . ")";
                                $sql = "{$this->rpdo->escape($alias)}.{$this->rpdo->escape($key)} {$operator} {$val}";
                                $result[]= new rPDOQueryCondition(array('sql' => $sql, 'binding' => null, 'conjunction' => $conj));
                                continue;
                            }
                            $field= array ();
                            $field['sql']= $this->rpdo->escape($alias) . '.' . $this->rpdo->escape($key) . ' ' . $operator . ' ?';
                            $field['binding']= array (
                                'value' => $val,
                                'type' => $type,
                                'length' => 0
                            );
                            $field['conjunction']= $conj;
                            $result[]= new rPDOQueryCondition($field);
                        } else {
                            throw new rPDOException("Invalid query expression");
                        }
                    }
                }
            }
        }
        elseif ($this->isConditionalClause($conditions)) {
            $result= new rPDOQueryCondition(array(
                'sql' => $conditions
            ,'binding' => null
            ,'conjunction' => $conjunction
            ));
        }
        elseif (($pktype == 'integer' && is_numeric($conditions)) || ($pktype == 'string' && is_string($conditions) && static::isValidClause($conditions))) {
            if ($pktype == 'integer') {
                $param_type= \PDO::PARAM_INT;
            } else {
                $param_type= \PDO::PARAM_STR;
            }
            $field['sql']= $this->rpdo->escape($alias) . '.' . $this->rpdo->escape($pk) . ' = ?';
            $field['binding']= array ('value' => $conditions, 'type' => $param_type, 'length' => 0);
            $field['conjunction']= $conjunction;
            $result = new rPDOQueryCondition($field);
        }
        else {
            $result = new rPDOQueryCondition([
                'sql' => $conditions,
                'binding' => null,
                'conjunction' => $conjunction
            ]);
        }
        return $result;
    }

    /**
     * Determines if a string contains a conditional operator.
     *
     * @param string $string The string to evaluate.
     *
     * @throws \rPDO\rPDOException
     * @return boolean True if the string is a complete conditional SQL clause.
     */
    public function isConditionalClause($string) {
        $matched= false;
        if (is_string($string)) {
            if (!static::isValidClause($string)) {
                throw new rPDOException("SQL injection attempt detected: {$string}");
            }
            foreach ($this->_operators as $operator) {
                if (strpos(strtoupper($string), $operator) !== false) {
                    $matched= true;
                    break;
                }
            }
        }
        return $matched;
    }

    /**
     * Builds conditional clauses from rPDO condition expressions.
     *
     * @param array|rPDOQueryCondition $conditions An array of conditions or an rPDOQueryCondition instance.
     * @param string $conjunction Either rPDOQuery:SQL_AND or rPDOQuery::SQL_OR
     * @param boolean $isFirst Indicates if this is the first condition in an array.
     * @return string The generated SQL clause.
     */
    public function buildConditionalClause($conditions, & $conjunction = rPDOQuery::SQL_AND, $isFirst = true) {
        $clause= '';
        if (is_array($conditions)) {
            $groups= count($conditions);
            $currentGroup= 1;
            $first = true;
            $origConjunction = $conjunction;
            $groupConjunction = $conjunction;
            foreach ($conditions as $groupKey => $group) {
                $groupClause = '';
                $groupClause.= $this->buildConditionalClause($group, $groupConjunction, $first);
                if ($first) {
                    $conjunction = $groupConjunction;
                }
                if (!empty($groupClause)) $clause.= $groupClause;
                $currentGroup++;
                $first = false;
            }
            $conjunction = $origConjunction;
            if ($groups > 1 && !empty($clause)) {
                $clause = " ( {$clause} ) ";
            }
            if (!$isFirst && !empty($clause)) {
                $clause = ' ' . $groupConjunction . ' ' . $clause;
            }
        } elseif (is_object($conditions) && $conditions instanceof rPDOQueryCondition) {
            if ($isFirst) {
                $conjunction = $conditions->conjunction;
            } else {
                $clause.= ' ' . $conditions->conjunction . ' ';
            }
            $clause.= $conditions->sql;
            if (!empty ($conditions->binding)) {
                $this->bindings[]= $conditions->binding;
            }
        }
        if ($this->rpdo->getDebug() === true) {
            $this->rpdo->log(rPDO::LOG_LEVEL_DEBUG, "Returning clause:\n{$clause}\nfrom conditions:\n" . print_r($conditions, 1));
        }
        return $clause;
    }

    /**
     * Wrap an existing rPDOCriteria into this rPDOQuery instance.
     *
     * @param rPDOCriteria|rPDOQuery $criteria
     */
    public function wrap($criteria) {
        if ($criteria instanceof rPDOQuery) {
            $this->_class= $criteria->_class;
            $this->_alias= $criteria->_alias;
            $this->graph= $criteria->graph;
            $this->query= $criteria->query;
        }
        $this->sql= $criteria->sql;
        $this->stmt= $criteria->stmt;
        $this->bindings= $criteria->bindings;
        $this->cacheFlag= $criteria->cacheFlag;
    }

    public function __debugInfo()
    {
        return [
            '_class' => $this->_class,
            '_alias' => $this->_alias,
            '_tableClass' => $this->_tableClass,
            'graph' => $this->graph,
            'query' => $this->query,
            'sql' => $this->toSQL(),
            'bindings' => $this->bindings,
        ];
    }
}
