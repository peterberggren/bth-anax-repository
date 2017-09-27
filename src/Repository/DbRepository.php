<?php

namespace LRC\Repository;

/**
 * Base class for database-backed repositories for data access.
 */
class DbRepository implements SoftRepositoryInterface
{
    /**
     * @var \Anax\Database\DatabaseQueryBuilder     Database service.
     */
    protected $db;
    
    /**
     * @var string  Database table name.
     */
    protected $table;
    
    /**
     * @var string  Model class name.
     */
    protected $modelClass;
    
    /**
     * @var string  Soft deletion attribute.
     */
    protected $deleted;
    
    
    /**
     * Constructor.
     *
     * @param \Anax\Database\DatabaseQueryBuilder   $db         Database service.
     * @param string                                $table      Database table name.
     * @param string                                $modelClass Model class name.
     * @param string                                $deleted    Soft deletion attribute.
     */
    public function __construct($db, $table, $modelClass, $deleted)
    {
        $this->db = $db;
        $this->table = $table;
        $this->modelClass = $modelClass;
        $this->deleted = $deleted;
    }
    
    
    /**
     * Find and return first entry by key.
     *
     * @param string $column    Key column name.
     * @param mixed  $value     Key value.
     *
     * @return mixed            Model instance.
     */
    public function find($column, $value)
    {
        return $this->getFirst("$column = ?", [$value]);
    }
    
    
    /**
     * Find and return first entry by key, ignoring soft-deleted entries.
     *
     * @param string $column    Key column name.
     * @param mixed  $value     Key value.
     *
     * @return mixed            Model instance.
     */
    public function findSoft($column, $value)
    {
        return $this->getFirstSoft("$column = ?", [$value]);
    }
    
        
    /**
     * Retrieve first entry, optionally filtered by search criteria.
     * 
     * @param string $conditions    Where conditions.
     * @param array  $values        Array of condition values to bind.
     * 
     * @return mixed                Model instance.
     */
    public function getFirst($conditions = null, $values = [])
    {
        return $this->executeQuery(null, $conditions, $values)
            ->fetchClass($this->modelClass);
    }
    
    
    /**
     * Retrieve first entry ignoring soft-deleted ones, optionally filtered by search criteria.
     * 
     * @param string $conditions    Where conditions.
     * @param array  $values        Array of condition values to bind.
     * 
     * @return mixed                Model instance.
     */
    public function getFirstSoft($conditions = null, $values = [])
    {
        return $this->executeQuery(null, $conditions, $values, null, true)
            ->fetchClass($this->modelClass);
    }
    
    
    /**
     * Retrieve all entries, optionally filtered by search criteria.
     * 
     * @param string $conditions    Where conditions.
     * @param array  $values        Array of condition values to bind.
     * 
     * @return array                Array of all matching entries.
     */
    public function getAll($conditions = null, $values = [])
    {
        return $this->executeQuery(null, $conditions, $values)
            ->fetchAllClass($this->modelClass);
    }
    
    
    /**
     * Retrieve all entries ignoring soft-deleted ones, optionally filtered by search criteria.
     * 
     * @param string $conditions    Where conditions.
     * @param array  $values        Array of condition values to bind.
     * 
     * @return array                Array of all matching entries.
     */
    public function getAllSoft($conditions = null, $values = [])
    {
        return $this->executeQuery(null, $conditions, $values, null, true)
            ->fetchAllClass($this->modelClass);
    }
    
    
    /**
     * Save entry by inserting if ID is missing and updating if ID exists.
     * 
     * @param mixed $model  Model instance.
     *
     * @return void
     */
    public function save($model)
    {
        if (isset($model->id)) {
            return $this->update($model);
        }
        
        return $this->create($model);
    }
    
    
    /**
     * Delete entry.
     *
     * @param mixed $model  Model instance.
     */
    public function delete($model)
    {
        $this->db->connect()
            ->deleteFrom($this->table)
            ->where('id = ?')
            ->execute([$model->id]);
        $model->id = null;
    }
    
    
    /**
     * Soft delete entry.
     *
     * @param mixed $model  Model instance.
     */
    public function deleteSoft($model)
    {
        $this->db->connect()
            ->update($this->table, [$this->deleted])
            ->where('id = ?')
            ->execute([date('Y-m-d H:i:s'), $model->id]);
    }
    
    
    /**
     * Restore soft-deleted entry.
     *
     * @param mixed $model  Model instance.
     */
    public function restoreSoft($model)
    {
        $this->db->connect()
            ->update($this->table, [$this->deleted])
            ->where('id = ?')
            ->execute([null, $model->id]);
    }


    /**
     * Count entries, optionally filtered by search criteria.
     *
     * @param string $conditions    Where conditions.
     * @param array  $values        Array of condition values to bind.
     * 
     * @return int                  Number of entries.
     */
    public function count($conditions = null, $values = [])
    {
        $res = $this->executeQuery('COUNT(id) AS num', $conditions, $values)
            ->fetch();
        return (isset($res->num) ? (int)$res->num : 0);
    }
    
    
    /**
     * Count entries ignoring soft-deleted ones, optionally filtered by search criteria.
     *
     * @param string $conditions    Where conditions.
     * @param array  $values        Array of condition values to bind.
     * 
     * @return int                  Number of entries.
     */
    public function countSoft($conditions = null, $values = [])
    {
        $res = $this->executeQuery('COUNT(id) AS num', $conditions, $values, null, true)
            ->fetch();
        return (isset($res->num) ? (int)$res->num : 0);
    }
    
    
    /**
     * Execute query for selection methods.
     * 
     * @param string $select                        Selection criteria.
     * @param string $conditions                    Where conditions.
     * @param array  $values                        Array of where condition values to bind.
     * @param string $order                         Order by conditions.
     * @param bool   $soft                          Whether to take soft deletion into account.
     * 
     * @return \Anax\Database\DatabaseQueryBuilder  Database service instance with executed internal query.
     */
    private function executeQuery($select = null, $conditions = null, $values = [], $order = null, $soft = false)
    {
        $query = $this->db->connect();
        $query = (!is_null($select) ? $query->select($select) : $query->select());
        $query = $query->from($this->table);
        if (!is_null($conditions)) {
            $query = $query->where($conditions);
        }
        if ($soft) {
            $softCond = $this->deleted . ' IS NULL';
            $query = (!is_null($conditions) ? $query->andWhere($softCond) : $query->where($softCond));
        }
        if (!is_null($order)) {
            $query = $query->orderby($order);
        }
        return $query->execute($values);
    }
    
    
    /**
     * Create new entry.
     * 
     * @param mixed $model  Model instance.
     */
    private function create($model)
    {
        $props = get_object_vars($model);
        unset($props['id']);
        $this->db
            ->connect()
            ->insert($this->table, array_keys($props))
            ->execute(array_values($props));
        $model->id = $this->db->lastInsertId();
    }
    
    
    /**
     * Update entry.
     * 
     * @param mixed $model  Model instance.
     */
    private function update($model)
    {
        $props = get_object_vars($model);
        unset($props['id']);
        $values = array_values($props);
        $values[] = $model->id;
        $this->db
            ->connect()
            ->update($this->table, array_keys($props))
            ->where('id = ?')
            ->execute($values);
    }
}