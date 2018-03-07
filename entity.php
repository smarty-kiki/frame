<?php

abstract class entity implements JsonSerializable, Serializable
{
    /*{{{*/
    const INIT_VERSION = 0;

    public $id;
    public $version;
    public $create_time;
    public $update_time;
    public $delete_time;

    private $is_force_deleted;

    public $structs = [];
    public $attributes = [];
    protected $json_attributes = [];

    public static $null_entity_mock_attributes = [];

    private $relationships = [];
    private $relationship_refs = [];

    protected static function init()
    {
        $static = new static();
        $static->attributes = $static->structs;
        $static->id = self::generate_id();
        $static->version = self::INIT_VERSION;
        $static->is_force_deleted = false;
        $static->create_time = $static->update_time = now();
        $static->delete_time = null;

        local_cache_set($static);

        return $static;
    }

    final public static function generate_id()
    {
        return generate_id();
    }

    final public function is_new()
    {
        return self::INIT_VERSION === $this->version;
    }

    final public function is_updated()
    {
        return $this->attributes != $this->structs;
    }

    final public function is_deleted()
    {
        return ! is_null($this->delete_time);
    }

    final public function delete()
    {
        $this->delete_time = now();
    }

    final public function restore()
    {
        $this->delete_time = null;
    }

    final public function is_force_deleted()
    {
        return $this->is_force_deleted;
    }

    final public function force_delete()
    {
        $this->is_force_deleted = true;
    }

    public function is_null()
    {
        return false;
    }

    public function is_not_null()
    {
        return ! $this->is_null();
    }

    final public function get_dao()
    {
        return dao(get_class($this));
    }

    public function jsonSerialize()
    {
        foreach ($this->json_attributes as $key => $attribute) {
            if (empty($attribute)) {
                $method = "get_$key";

                if (method_exists($this, $method)) {
                    $this->json_attributes[$key] = $this->$method();
                }
            }
        }

        return array_merge($this->attributes, $this->json_attributes);
    }

    public function serialize()
    {
        $serializable = get_object_vars($this);

        unset($serializable['relationships']);

        return serialize($serializable);
    }

    public function unserialize($serialized)
    {
        $unserialized = unserialize($serialized);

        foreach($unserialized as $property => $value) {

            $this->{$property} = $value;

        }
    }

    public function __get($property)
    {
        $method = "get_$property";

        if (method_exists($this, $method)) {
            return $this->$method();
        }

        if (array_key_exists($property, $this->relationships)) {
            return $this->relationships[$property];
        }

        if (array_key_exists($property, $this->relationship_refs)) {
            return $this->load_relationship_from_ref($property);
        }

        return $this->attributes[$property];
    }

    final public function __set($property, $value)
    {
        $method = "prepare_set_$property";

        if (method_exists($this, $method)) {
            $value = $this->$method($value);
        }

        if (array_key_exists($property, $this->relationship_refs)) {

            $this->relationship_refs[$property]->update($value, $this);

            return $this->relationships[$property] = $value;
        }

        if (array_key_exists($property, $this->attributes)) {
            return $this->attributes[$property] = $value;
        }
    }

    private function load_relationship_from_ref($relationship_name)
    {
        $relationship_ref = $this->relationship_refs[$relationship_name];

        return $this->relationships[$relationship_name] = $relationship_ref->load($this);
    }

    protected function has_one($relationship_name, $entity_name = null, $foreign_key = null)
    {
        $self_entity_name = get_class($this);

        if (is_null($entity_name)) {
            $entity_name = $relationship_name;
        }

        if (is_null($foreign_key)) {
            $foreign_key = $self_entity_name.'_id';
        }

        return $this->relationship_refs[$relationship_name] = new has_one($entity_name, $self_entity_name, $foreign_key);
    }

    protected function belongs_to($relationship_name, $entity_name = null, $foreign_key = null)
    {
        if (is_null($entity_name)) {
            $entity_name = $relationship_name;
        }

        if (is_null($foreign_key)) {
            $foreign_key = $entity_name.'_id';
        }

        return $this->relationship_refs[$relationship_name] = new belongs_to($entity_name, $foreign_key);
    }

    protected function has_many($relationship_name, $entity_name = null, $foreign_key = null)
    {
        $self_entity_name = get_class($this);

        if (is_null($entity_name)) {
            $entity_name = $relationship_name;
        }

        if (is_null($foreign_key)) {
            $foreign_key = $self_entity_name.'_id';
        }

        return $this->relationship_refs[$relationship_name] = new has_many($entity_name, $self_entity_name, $foreign_key);
    }
}/*}}}*/

class null_entity extends entity
{
    /*{{{*/
    private $mock_entity_name = null;

    public static function create($mock_entity_name = null)
    {
        $null_entity = new static;
        $null_entity->mock_entity_name = $mock_entity_name;

        return $null_entity;
    }

    public function is_null()
    {
        return true;
    }

    public function __call($method, $args)
    {
        return;
    }

    public function __get($property)
    {
        if ($property === 'id') {
            return 0;
        }

        $mock_entity_name = $this->mock_entity_name;
        if (! is_null($mock_entity_name)) {

            $null_entity_mock_attribute_list = $mock_entity_name::$null_entity_mock_attributes;
            if (array_key_exists($property, $null_entity_mock_attribute_list)) {

                return $null_entity_mock_attribute_list[$property];
            }
        }

        return self::create($property);
    }
}/*}}}*/

interface relationship_ref
{
    /*{{{*/
    public function load(entity $from_entity);
    public function update($values, entity $from_entity);
}/*}}}*/

class has_one implements relationship_ref
{
    /*{{{*/
    private $entity_name;
    private $foreign_key;

    public function __construct($entity_name, $from_entity_name, $foreign_key)
    {
        $this->entity_name = $entity_name;
        $this->foreign_key = $foreign_key;
    }

    public function load(entity $from_entity)
    {
        return dao($this->entity_name)->find_by_foreign_key($this->foreign_key, $from_entity->id);
    }

    public function update($entity, entity $from_entity)
    {
        $entity->{$this->foreign_key} = $from_entity->id;
    }
}/*}}}*/

class belongs_to implements relationship_ref
{
    /*{{{*/
    private $entity_name;
    private $foreign_key;

    public function __construct($entity_name, $foreign_key)
    {
        $this->entity_name = $entity_name;
        $this->foreign_key = $foreign_key;
    }

    public function load(entity $from_entity)
    {
        return dao($this->entity_name)->find($from_entity->{$this->foreign_key});
    }

    public function update($entity, entity $from_entity)
    {
        $from_entity->{$this->foreign_key} = $entity->id;
    }
}/*}}}*/

class has_many implements relationship_ref
{
    /*{{{*/
    private $entity_name;
    private $foreign_key;

    public function __construct($entity_name, $from_entity_name, $foreign_key)
    {
        $this->entity_name = $entity_name;
        $this->foreign_key = $foreign_key;
    }

    public function load(entity $from_entity)
    {
        return dao($this->entity_name)->find_all_by_foreign_key($this->foreign_key, $from_entity->id);
    }

    public function update($entities, entity $from_entity)
    {
        foreach ($entities as $entity) {
            $entity->{$this->foreign_key} = $from_entity->id;
        }
    }
}/*}}}*/

class dao
{
    /*{{{*/
    protected $class_name;
    protected $table_name;
    protected $db_config_key;

    public function __construct()
    {/*{{{*/
        $this->class_name = substr(get_class($this), 0, -4);
    }/*}}}*/

    public function find($id_or_ids)
    {/*{{{*/
        if (is_array($ids = $id_or_ids)) {
            return $this->find_all_by_ids($ids);
        } else {
            return $this->find_by_id($id = $id_or_ids);
        }
    }/*}}}*/

    private function find_by_id($id)
    {/*{{{*/
        if (empty($id)) {
            return null_entity::create($this->class_name);
        }

        $entity = local_cache_get($this->class_name, $id);

        if (is_null($entity)) {
            $row = db_query_first('select * from `'.$this->table_name.'` where id = ?', [$id], $this->db_config_key);
            if ($row) {
                $entity = $this->row_to_entity($row);
                local_cache_set($entity);
            } else {
                $entity = null_entity::create($this->class_name);
            }
        }

        return $entity;
    }/*}}}*/

    public function find_by_foreign_key(string $foreign_key, $value)
    {/*{{{*/
        $sql_template = "select * from `$this->table_name` where $foreign_key = :foreign_key";

        return $this->find_by_sql($sql_template, [
            ':foreign_key' => $value,
        ]);
    }/*}}}*/

    protected function find_by_condition($condition, array $binds = [])
    {/*{{{*/
        return $this->find_by_sql('select * from `'.$this->table_name.'` where '.$condition, $binds);
    }/*}}}*/

    protected function find_by_sql($sql_template, array $binds = [])
    {/*{{{*/
        $row = db_query_first($sql_template, $binds, $this->db_config_key);

        if (empty($row)) {
            return null_entity::create($this->class_name);
        }

        $entity = local_cache_get($this->class_name, $row['id']);
        if (!is_null($entity)) {
            return $entity;
        }

        $entity = $this->row_to_entity($row);
        local_cache_set($entity);

        return $entity;
    }/*}}}*/

    private function find_all_by_ids(array $ids)
    {/*{{{*/
        if (empty($ids)) {
            return [];
        }

        $sql = [
            'sql_template' => 'select * from `'.$this->table_name.'` where id in :ids order by find_in_set(id, :set)',
            'binds' => [
                ':ids' => $ids,
                ':set' => implode(',', $ids),
            ],
        ];

        $rows = db_query($sql['sql_template'], $sql['binds'], $this->db_config_key);

        $entities = [];

        foreach ($rows as $row) {
            $entity = local_cache_get($this->class_name, $row['id']);
            if (is_null($entity)) {
                $entity = $this->row_to_entity($row);
                local_cache_set($entity);
            }
            $entities[$entity->id] = $entity;
        }

        return $entities;
    }/*}}}*/

    public function find_all()
    {/*{{{*/
        return $this->find_all_by_sql('select * from `'.$this->table_name.'` order by id', []);
    }/*}}}*/

    public function find_all_by_column(array $columns = [])
    {/*{{{*/
        if ($columns) {

            list($where, $binds) = db_simple_where_sql($columns);

            return $this->find_all_by_sql('select * from `'.$this->table_name."` where $where order by id", $binds);
        } else {
            return $this->find_all();
        }
    }/*}}}*/

    public function find_all_by_foreign_key(string $foreign_key, $value)
    {/*{{{*/
        $sql_template = "select * from `$this->table_name` where $foreign_key = :foreign_key";

        return $this->find_all_by_sql($sql_template, [
            ':foreign_key' => $value,
        ]);
    }/*}}}*/

    protected function find_all_by_condition($condition, array $binds = [])
    {/*{{{*/
        return $this->find_all_by_sql('select * from `'.$this->table_name.'` where '.$condition, $binds);
    }/*}}}*/

    protected function find_all_by_sql($sql_template, array $binds = [])
    {/*{{{*/
        $entities = [];

        $rows = db_query($sql_template, $binds, $this->db_config_key);

        foreach ($rows as $row) {
            $entity = local_cache_get($this->class_name, $row['id']);
            if (is_null($entity)) {
                $entity = $this->row_to_entity($row);
                local_cache_set($entity);
            }
            $entities[$entity->id] = $entity;
        }

        return $entities;
    }/*}}}*/

    public function find_all_paginated_by_current_page_and_condition($current_page, $page_size, $condition, array $binds = [])
    {/*{{{*/
        $res = [
            'list' => [],
            'pagination' => [
                'page_size' => $page_size,
                'current_page' => $current_page,
                'count' => 0,
                'pages' => 0,
            ],
        ];

        $count = $this->count_by_condition($condition, $binds);
        if (! $count) {
            return $res;
        } else {
            $res['pagination']['count'] = $count;
            $res['pagination']['pages'] = ceil($count / $page_size);
        }

        $offset = $page_size * ($current_page - 1);

        $res['list'] = $this->find_all_by_condition($condition." limit $offset, $page_size", $binds);

        return $res;
    }/*}}}*/

    public function count()
    {/*{{{*/
        $sql = 'select count(*) as count from '.$this->table_name;

        return db_query_value('count', $sql, [], $this->db_config_key);
    }/*}}}*/

    protected function count_by_condition($condition, array $binds = [])
    {/*{{{*/
        $sql = 'select count(*) as count from '.$this->table_name.' where '.$condition;

        return db_query_value('count', $sql, $binds, $this->db_config_key);
    }/*}}}*/

    final private function get_dirty($entity)
    {/*{{{*/
        $rows = [];

        foreach ($entity->attributes as $column => $value) {
            if ($entity->structs[$column] !== $value) {
                $rows[$column] = $value;
            }
        }

        $rows['version'] = $entity->version + 1;
        $rows['update_time'] = now();
        $rows['delete_time'] = $entity->delete_time;

        return $rows;
    }/*}}}*/

    final private function row_to_entity($rows)
    {/*{{{*/
        $entity = new $this->class_name();

        $entity->id = $rows['id'];
        $entity->version = $rows['version'];
        $entity->attributes = $entity->structs = $rows;

        return $entity;
    }/*}}}*/

    final public function get_db_config_key()
    {/*{{{*/
        return $this->db_config_key;
    }/*}}}*/

    final public function dump_insert_sql($entity)
    {/*{{{*/
        $columns = $values = $binds = [];

        $insert = $entity->attributes + [
            'id' => $entity->id,
            'version' => $entity->version + 1,
            'create_time' => $entity->create_time,
            'update_time' => $entity->update_time,
        ];

        foreach ($insert as $column => $value) {
            $columns[] = $column;
            $values[] = ":$column";
            $binds[":$column"] = $value;
        }

        return [
            'sql_template' => 'insert into `'.$this->table_name.'` (`'.implode('`, `', $columns).'`) values ('.implode(', ', $values).')',
            'binds' => $binds,
        ];
    }/*}}}*/

    final public function dump_update_sql($entity)
    {/*{{{*/
        $binds = $update = [];

        $binds[':id'] = $entity->id;
        $binds[':old_version'] = $entity->version;

        foreach ($this->get_dirty($entity) as $column => $value) {
            $update[] = "$column = :$column";
            $binds[":$column"] = $value;
        }

        return [
            'sql_template' => 'update `'.$this->table_name.'` set '.implode(', ', $update).' where id = :id and version = :old_version',
            'binds' => $binds,
        ];
    }/*}}}*/

    final public function dump_delete_sql($entity)
    {/*{{{*/
        return [
            'sql_template' => 'delete from `'.$this->table_name.'` where id = :id',
            'binds' => [
                ':id' => $entity->id,
            ],
        ];
    }/*}}}*/
}/*}}}*/

function dao($class_name)
{
    return instance($class_name.'_dao');
}

function _local_cache_key($entity_type, $id)
{
    return $entity_type.'_'.$id;
}

function _local_cache($cached = null)
{
    static $container = [];

    if (is_null($cached)) {
        return $container;
    }

    return $container = $cached;
}

function local_cache_get($entity_type, $id)
{
    $cached = _local_cache();

    $key = _local_cache_key($entity_type, $id);

    if (isset($cached[$key])) {
        return $cached[$key];
    }

    return;
}

function local_cache_has($entity_type, $id)
{
    $cached = _local_cache();

    $key = _local_cache_key($entity_type, $id);

    return isset($cached[$key]);
}

function local_cache_get_all()
{
    return _local_cache();
}

function local_cache_set(entity $entity)
{
    $cached = _local_cache();

    $key = _local_cache_key(get_class($entity), $entity->id);

    $cached[$key] = $entity;

    _local_cache($cached);
}

function local_cache_delete($entity_type, $id)
{
    $cached = _local_cache();

    $key = _local_cache_key($entity_type, $id);

    unset($cached[$key]);

    _local_cache($cached);
}

function local_cache_delete_all()
{
    _local_cache([]);
}

function local_cache_flush_all()
{
    $cached = _local_cache();

    local_cache_delete_all();

    return $cached;
}

/**
 * input_entity
 *
 * @param mixed $entity_name
 * @param string $name
 * @access public
 * @return void
 */
function input_entity($entity_name, $message = null, $name = null)
{
    if (is_null($name)) {
        $name = $entity_name.'_id';
    }

    if (! $message) {
        $message = '无效的 '.$name;
    }

    if ($id = input($name)) {
        $entity = dao($entity_name)->find($id);

        otherwise($entity->is_not_null(), sprintf($message, $id));

        return $entity;
    }

    otherwise(false, sprintf($message, $id));
}
