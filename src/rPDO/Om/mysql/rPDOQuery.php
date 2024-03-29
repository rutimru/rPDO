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

/**
 * An implementation of rPDOQuery for the MySQL database engine.
 *
 * @package rPDO\Om\mysql
 */
class rPDOQuery extends \rPDO\Om\rPDOQuery {
    public function __construct(& $rpdo, $class, $criteria= null) {
        parent :: __construct($rpdo, $class, $criteria);
        $this->query['priority']= '';
    }

    public function construct() {
        $this->bindings= array ();
        $command= strtoupper($this->query['command']);
        $sql= $this->query['command'] . ' ';
        if ($command == 'SELECT') $sql.= $this->query['distinct'] ? $this->query['distinct'] . ' ' : '';
        if ($command == 'SELECT') $sql.= $this->query['priority'] ? $this->query['priority'] . ' ' : '';
        if ($command == 'SELECT') {
            $columns= array ();
            if (empty ($this->query['columns'])) {
                $this->select('*');
            }
            foreach ($this->query['columns'] as $alias => $column) {
                $ignorealias = is_int($alias);
                $escape = !preg_match('/\bAS\b/i', $column) && !preg_match('/\./', $column) && !preg_match('/\(/', $column);
                if ($escape) {
                    $column= $this->rpdo->escape(trim($column));
                } else {
                    $column= trim($column);
                }
                if (!$ignorealias) {
                    $alias = $escape ? $this->rpdo->escape($alias) : $alias;
                    $columns[]= "{$column} AS {$alias}";
                } else {
                    $columns[]= "{$column}";
                }
            }
            $sql.= implode(', ', $columns);
            $sql.= ' ';
        }
        if ($command != 'UPDATE') {
            $sql.= 'FROM ';
        }
        $tables= array ();
        foreach ($this->query['from']['tables'] as $table) {
            if ($command != 'SELECT') {
                $tables[]= $table['table'];
            } else {
                $tables[]= $table['table'] . ' AS ' . $this->rpdo->escape($table['alias']);
            }
        }
        $sql.= $this->query['from']['tables'] ? implode(', ', $tables) . ' ' : '';
        if (!empty ($this->query['from']['joins'])) {
            foreach ($this->query['from']['joins'] as $join) {
                $sql.= $join['type'] . ' ' . $join['table'] . ' ' . $this->rpdo->escape($join['alias']) . ' ';
                if (!empty ($join['conditions'])) {
                    $sql.= 'ON ';
                    $sql.= $this->buildConditionalClause($join['conditions']);
                    $sql.= ' ';
                }
            }
        }
        if ($command == 'UPDATE') {
            if (!empty($this->query['set'])) {
                $clauses = array();
                foreach ($this->query['set'] as $setKey => $setVal) {
                    $value = $setVal['value'];
                    $type = $setVal['type'];
                    if ($value !== null && in_array($type, array(\PDO::PARAM_INT, \PDO::PARAM_STR))) {
                        $value = $this->rpdo->quote($value, $type);
                    } elseif ($value === null) {
                        $value = 'NULL';
                    }
                    $clauses[] = $this->rpdo->escape($setKey) . ' = ' . $value;
                }
                if (!empty($clauses)) {
                    $sql.= 'SET ' . implode(', ', $clauses) . ' ';
                }
                unset($clauses);
            }
        }
        if (!empty ($this->query['where'])) {
            if ($where= $this->buildConditionalClause($this->query['where'])) {
                $sql.= 'WHERE ' . $where . ' ';
            }
        }
        if ($command == 'SELECT' && !empty ($this->query['groupby'])) {
            $groupby= reset($this->query['groupby']);
            $sql.= 'GROUP BY ';
            $sql.= $groupby['column'];
            if ($groupby['direction']) $sql.= ' ' . $groupby['direction'];
            while ($groupby= next($this->query['groupby'])) {
                $sql.= ', ';
                $sql.= $groupby['column'];
                if ($groupby['direction']) $sql.= ' ' . $groupby['direction'];
            }
            $sql.= ' ';
        }
        if (!empty ($this->query['having'])) {
            $sql.= 'HAVING ';
            $sql.= $this->buildConditionalClause($this->query['having']);
            $sql.= ' ';
        }
        if ($command == 'SELECT' && !empty ($this->query['sortby'])) {
            $sortby= reset($this->query['sortby']);
            $sql.= 'ORDER BY ';
            $sql.= $sortby['column'];
            if ($sortby['direction']) $sql.= ' ' . $sortby['direction'];
            while ($sortby= next($this->query['sortby'])) {
                $sql.= ', ';
                $sql.= $sortby['column'];
                if ($sortby['direction']) $sql.= ' ' . $sortby['direction'];
            }
            $sql.= ' ';
        }
        if ($limit= intval($this->query['limit'])) {
            $sql.= 'LIMIT ';
            if ($offset= intval($this->query['offset'])) $sql.= $offset . ', ';
            $sql.= $limit . ' ';
        }
        $this->sql= $sql;
        return (!empty ($this->sql));
    }
}
