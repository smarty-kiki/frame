<?php

define('ENTITY_RELATIONSHIP_DELETED_SUFFIX', '_with_deleted');

abstract class entity implements JsonSerializable, Serializable
{
    /*{{{*/
    const INIT_VERSION = 0;

    public $id;
    public $version;
    public $create_time;
    public $update_time;
    public $delete_time;

    private $just_deleted;
    private $just_force_deleted;

    public $structs = [];
    public $attributes = [];
    protected $json_attributes = [];

    public static $struct_type_maps = [
        'varchar' => 'text',
        'text' => 'text',
        'int' => 'number',
        'bigint' => 'number',
        'enum' => 'enum',
    ];

    public static $entity_display_name = '';
    public static $entity_description = '';
    public static $struct_types = [];
    public static $struct_display_names = [];
    public static $struct_descriptions = [];
    public static $struct_formats = [];
    public static $struct_format_descriptions = [];

    public static $null_entity_mock_attributes = [];

    private $relationships = [];
    private $relationship_refs = [];

    protected static function init()
    {
        $static = new static();
        $static->attributes = $static->structs;
        $static->id = self::generate_id();
        $static->version = self::INIT_VERSION;
        $static->create_time = $static->update_time = datetime();
        $static->delete_time = null;

        $static->just_deleted = false;
        $static->just_force_deleted = false;

        local_cache_set($static);

        return $static;
    }

    final public static function convert_struct_format($datatype, $struct_format = null)
    {
        if (is_array($struct_format)) {
            return 'enum';
        }

        foreach (self::$struct_type_maps as $pattern => $type) {
            if (stristr($datatype, $pattern)) {
                return $type;
            }
        }

        return 'text';
    }

    final public static function generate_id()
    {
        return generate_id(get_called_class());
    }

    final public function just_new()
    {
        return self::INIT_VERSION === $this->version;
    }

    final public function just_updated()
    {
        return $this->attributes != $this->structs;
    }

    final public function is_deleted()
    {
        return ! is_null($this->delete_time);
    }

    final public function is_not_deleted()
    {
        return is_null($this->delete_time);
    }

    final public function just_deleted()
    {
        return $this->just_deleted;
    }

    final public function delete()
    {
        $this->just_deleted = true;
        $this->delete_time = datetime();
    }

    final public function restore()
    {
        $this->just_deleted = false;
        $this->delete_time = null;
    }

    final public function just_force_deleted()
    {
        return $this->just_force_deleted;
    }

    final public function force_delete()
    {
        $this->just_force_deleted = true;
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

        return array_merge([
            'id' => $this->id,
            'version' => $this->version,
            'create_time' => $this->create_time,
            'update_time' => $this->update_time,
            'delete_time' => $this->delete_time,
        ], $this->attributes, $this->json_attributes);
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

        if (isset($this->relationships[$property])) {
            return $this->relationships[$property];
        }

        if (isset($this->relationship_refs[$property])) {
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

        if (isset($this->relationship_refs[$property])) {

            if (isset($this->relationships[$property])) {

                $this->relationship_refs[$property]->update($value, $this, $this->relationships[$property]);
            } else {
                $this->relationship_refs[$property]->update($value, $this);
            }

            return $this->relationships[$property] = $value;
        }

        if (array_key_exists($property, $this->attributes)) {

            if (isset(static::$struct_formats[$property])) {

                $format = static::$struct_formats[$property];

                $format_description = static::$struct_format_descriptions[$property] ?? $property.' 格式错误';

                if (is_array($format)) {
                    otherwise(isset($format[$value]), $format_description);
                } else {
                    otherwise(preg_match($format, $value), $format_description);
                }
            }

            return $this->attributes[$property] = $value;
        }
    }

    final public function __unset($property)
    {
        otherwise(isset($this->relationships[$property]), '只能 unset 实体的关联关系');

        unset($this->relationships[$property]);
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

        $this->relationship_refs[$relationship_name] = instance('has_one', [$entity_name, $foreign_key]);
        $this->relationship_refs[$relationship_name.ENTITY_RELATIONSHIP_DELETED_SUFFIX] = instance('has_one', [$entity_name, $foreign_key, true]);
    }

    protected function belongs_to($relationship_name, $entity_name = null, $foreign_key = null)
    {
        if (is_null($entity_name)) {
            $entity_name = $relationship_name;
        }

        if (is_null($foreign_key)) {
            $foreign_key = $entity_name.'_id';
        }

        $this->relationship_refs[$relationship_name] = instance('belongs_to', [$entity_name, $foreign_key]);
        $this->relationship_refs[$relationship_name.ENTITY_RELATIONSHIP_DELETED_SUFFIX] = instance('belongs_to', [$entity_name, $foreign_key, true]);
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

        $this->relationship_refs[$relationship_name] = instance('has_many', [$entity_name, $foreign_key]);
        $this->relationship_refs[$relationship_name.ENTITY_RELATIONSHIP_DELETED_SUFFIX] = instance('has_many', [$entity_name, $foreign_key, true]);
    }

    public function relationship_batch_load($relationship_name, array $from_entities)
    {
        otherwise(array_key_exists($relationship_name, $this->relationship_refs), 'entity ['.get_class($this).'] has not a relationship called ['.$relationship_name.']');

        $relationship_ref = $this->relationship_refs[$relationship_name];

        return $relationship_ref->batch_load($from_entities, $relationship_name);
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

    public function __toString()
    {
        return '空';
    }
}/*}}}*/

abstract class relationship_ref
{
    /*{{{*/
    abstract public function load(entity $from_entity);
    abstract public function batch_load(array $from_entity, $relationship_name);
    abstract public function update($values, entity $from_entity, $old_value);
}/*}}}*/

class has_one extends relationship_ref
{
    /*{{{*/
    private $entity_name;
    private $foreign_key;
    private $with_deleted;

    public function __construct($entity_name, $foreign_key, $with_deleted = false)
    {
        $this->entity_name = $entity_name;
        $this->foreign_key = $foreign_key;
        $this->with_deleted = $with_deleted;
    }

    public function load(entity $from_entity)
    {
        return dao($this->entity_name, $this->with_deleted)->find_by_foreign_key($this->foreign_key, $from_entity->id);
    }

    public function batch_load(array $from_entities, $relationship_name)
    {
        $ids = array_keys($from_entities);

        $entities = dao($this->entity_name, $this->with_deleted)->find_all_by_foreign_keys($this->foreign_key, $ids);

        foreach ($entities as $entity) {

            $from_entity = $from_entities[$entity->{$this->foreign_key}];

            $from_entity->{$relationship_name} = $entity;
        }

        return $entities;
    }

    public function update($entity, entity $from_entity, $old_entity = null)
    {
        if ($old_entity instanceof entity) {
            $old_entity->{$this->foreign_key} = 0;
        }

        if ($entity instanceof entity) {
            $entity->{$this->foreign_key} = $from_entity->id;
        }
    }
}/*}}}*/

class belongs_to extends relationship_ref
{
    /*{{{*/
    private $entity_name;
    private $foreign_key;
    private $with_deleted;

    public function __construct($entity_name, $foreign_key, $with_deleted = false)
    {
        $this->entity_name = $entity_name;
        $this->foreign_key = $foreign_key;
        $this->with_deleted = $with_deleted;
    }

    public function load(entity $from_entity)
    {
        return dao($this->entity_name, $this->with_deleted)->find($from_entity->{$this->foreign_key});
    }

    public function batch_load(array $from_entities, $relationship_name)
    {
        $ids_keys = [];

        foreach ($from_entities as $from_entity) {

            if ($from_entity instanceof entity && $from_entity->is_not_null()) {

                $ids_keys[$from_entity->{$this->foreign_key}] = null;
            }
        }

        $ids = array_keys($ids_keys);

        $entities = dao($this->entity_name, $this->with_deleted)->find($ids);

        foreach ($from_entities as $from_entity) {

            if (array_key_exists($from_entity->{$this->foreign_key}, $entities)) {

                $from_entity->{$relationship_name} = $entities[$from_entity->{$this->foreign_key}];
            }
        }

        return $entities;
    }

    public function update($entity, entity $from_entity, $old_entity = null)
    {
        if ($entity instanceof entity) {
            $from_entity->{$this->foreign_key} = $entity->id;
        } else {
            $from_entity->{$this->foreign_key} = 0;
        }
    }
}/*}}}*/

class has_many extends relationship_ref
{
    /*{{{*/
    private $entity_name;
    private $foreign_key;
    private $with_deleted;

    public function __construct($entity_name, $foreign_key, $with_deleted = false)
    {
        $this->entity_name = $entity_name;
        $this->foreign_key = $foreign_key;
        $this->with_deleted = $with_deleted;
    }

    public function load(entity $from_entity)
    {
        return dao($this->entity_name, $this->with_deleted)->find_all_by_foreign_key($this->foreign_key, $from_entity->id);
    }

    public function batch_load(array $from_entities, $relationship_name)
    {
        $ids = array_keys($from_entities);

        $entities = dao($this->entity_name, $this->with_deleted)->find_all_by_foreign_keys($this->foreign_key, $ids);

        $entities_indexed_by_foreign_key = [];

        foreach ($entities as $entity) {

            $foreign_id = $entity->{$this->foreign_key};

            if (! array_key_exists($foreign_id,  $entities_indexed_by_foreign_key)) {

                $entities_indexed_by_foreign_key[$foreign_id] = [];
            }

            $entities_indexed_by_foreign_key[$foreign_id][$entity->id] = $entity;
        }

        foreach ($from_entities as $from_entity) {

            $from_entity->{$relationship_name} = $entities_indexed_by_foreign_key[$from_entity->id] ?? [];
        }

        return $entities;
    }

    public function update($entities, entity $from_entity, $old_entities = [])
    {
        foreach ($old_entities as $old_entity) {
            $old_entity->{$this->foreign_key} = $from_entity->id;
        }

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
    protected $with_deleted;

    public function __construct()
    {/*{{{*/
        $this->class_name = substr(get_class($this), 0, -4);
    }/*}}}*/

    public function set_with_deleted($with_deleted)
    {/*{{{*/
        $this->with_deleted = $with_deleted;
    }/*}}}*/

    final protected function with_deleted_sql($alias = null)
    {/*{{{*/
        $alias = $alias ? $alias.'.': '';

        if ($this->with_deleted) {
            return '';
        } else {
            return 'and '.$alias.'delete_time is null';
        }
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

            $with_deleted_sql = '';
            if (! $this->with_deleted) {
                $with_deleted_sql = 'and delete_time is null';
            }

            $row = db_query_first('select * from `'.$this->table_name.'` where id = :id '.$with_deleted_sql, [':id' => $id], $this->db_config_key);
            if ($row) {
                $entity = $this->row_to_entity($row);
                local_cache_set($entity);
            } else {
                $entity = null_entity::create($this->class_name);
            }
        }

        return $entity;
    }/*}}}*/

    public function find_by_column(array $columns)
    {/*{{{*/
        if (! $this->with_deleted) {
            $columns['delete_time'] = null;
        } else {
            unset($columns['delete_time']);
        }

        list($where, $binds) = db_simple_where_sql($columns);

        return $this->find_by_sql('select * from `'.$this->table_name."` where $where order by id", $binds);
    }/*}}}*/

    public function find_by_foreign_key(string $foreign_key, $value)
    {/*{{{*/
        $with_deleted_sql = '';
        if (! $this->with_deleted) {
            $with_deleted_sql = 'and delete_time is null';
        }

        $sql_template = "select * from `$this->table_name` where $foreign_key = :foreign_key $with_deleted_sql";

        return $this->find_by_sql($sql_template, [
            ':foreign_key' => $value,
        ]);
    }/*}}}*/

    public function find_all_by_foreign_keys(string $foreign_key, array $values)
    {/*{{{*/
        $with_deleted_sql = '';
        if (! $this->with_deleted) {
            $with_deleted_sql = 'and delete_time is null';
        }

        $sql_template = "select * from `$this->table_name` where $foreign_key in :foreign_keys $with_deleted_sql";

        return $this->find_all_by_sql($sql_template, [
            ':foreign_keys' => $values,
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

        $with_deleted_sql = '';
        if (! $this->with_deleted) {
            $with_deleted_sql = 'and delete_time is null';
        }

        $sql = [
            'sql_template' => '
                select * from `'.$this->table_name.'`
                where id in :ids '.$with_deleted_sql.'
                order by find_in_set(id, :set)
            ',
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
        $with_deleted_sql = '';
        if (! $this->with_deleted) {
            $with_deleted_sql = 'where delete_time is null';
        }

        return $this->find_all_by_sql('select * from `'.$this->table_name.'` '.$with_deleted_sql.' order by id', []);
    }/*}}}*/

    public function find_all_by_column(array $columns = [])
    {/*{{{*/
        if ($columns) {

            if (! $this->with_deleted) {
                $columns['delete_time'] = null;
            } else {
                unset($columns['delete_time']);
            }

            list($where, $binds) = db_simple_where_sql($columns);

            return $this->find_all_by_sql('select * from `'.$this->table_name."` where $where order by id", $binds);
        } else {
            return $this->find_all();
        }
    }/*}}}*/

    public function find_all_by_foreign_key(string $foreign_key, $value)
    {/*{{{*/
        $with_deleted_sql = '';
        if (! $this->with_deleted) {
            $with_deleted_sql = 'and delete_time is null';
        }

        $sql_template = "select * from `$this->table_name` where $foreign_key = :foreign_key $with_deleted_sql";

        return $this->find_all_by_sql($sql_template, [
            ':foreign_key' => $value,
        ]);
    }/*}}}*/

    protected function find_all_by_condition($condition, array $binds = [])
    {/*{{{*/
        return $this->find_all_by_sql('select * from `'.$this->table_name.'` where '.  $condition, $binds);
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

    protected function find_all_grouped_entities_by_sql($group_key, $sql_template, array $binds = [])
    {/*{{{*/
        $entities = [];

        $rows = db_query($sql_template, $binds, $this->db_config_key);

        foreach ($rows as $row) {
            $entity = local_cache_get($this->class_name, $row['id']);
            if (is_null($entity)) {
                $entity = $this->row_to_entity($row);
                local_cache_set($entity);
            }

            $group_value = $entity->{$group_key};

            if (! isset($entities[$group_value])) {
                $entities[$group_value] = [];
            }
            $entities[$group_value][$entity->id] = $entity;
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
        $with_deleted_sql = '';
        if (! $this->with_deleted) {
            $with_deleted_sql = 'where delete_time is null';
        }

        $sql = 'select count(*) as count from `'.$this->table_name.'` '.$with_deleted_sql;

        return db_query_value('count', $sql, [], $this->db_config_key);
    }/*}}}*/

    protected function count_by_condition($condition, array $binds = [])
    {/*{{{*/
        $sql = 'select count(*) as count from `'.$this->table_name.'` where '.$condition;

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
        $rows['update_time'] = datetime();
        $rows['delete_time'] = $entity->delete_time;

        return $rows;
    }/*}}}*/

    final private function row_to_entity($rows)
    {/*{{{*/
        $entity = new $this->class_name();

        $entity->id = $rows['id'];
        $entity->version = $rows['version'];
        $entity->create_time = $rows['create_time'];
        $entity->update_time = $rows['update_time'];
        $entity->delete_time = $rows['delete_time'];

        unset($rows['id']);
        unset($rows['version']);
        unset($rows['create_time']);
        unset($rows['update_time']);
        unset($rows['delete_time']);

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

function dao($class_name, $with_deleted = false)
{
    $dao = instance($class_name.'_dao');
    $dao->set_with_deleted($with_deleted);

    return $dao;
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

function relationship_batch_load($entities, $relationship_chain)
{
    if (empty($entities)) {
        return;
    }

    if ($entities instanceof entity) {

        $entities = [$entities->id => $entities];
    }

    $relationships = explode('.', $relationship_chain);

    foreach ($relationships as $relationship) {

        if ($entity = reset($entities)) {

            $entities = $entity->relationship_batch_load($relationship, $entities);
        }
    }

    return $entities;
}       
