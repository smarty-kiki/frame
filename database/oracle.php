<?php
/**
 *  todo::close
 */

function _oracle_connection($host, $port, $database, $username, $password, $charset, $options = [])
{/*{{{*/
    static $container = [];

    $dsn = "oci:dbname=//{$host}:{$port}/{$database};charset={$charset}";

    $identifier = $dsn.'|'.$username.'|'.$password;

    if (!isset($container[$identifier])) {

        $container[$identifier] = new PDO($dsn, $username, $password, $options);
    }

    return $container[$identifier];
}/*}}}*/

function _oracle_database_closure($config_key, $type, closure $closure)
{/*{{{*/
    $config = config_midware('oracle', $config_key);

    $type = db_force_type_write()? 'write': $type;

    $connection = _oracle_connection(
        $host = array_rand($config[$type]),
        $port = $config[$type][$host],
        $config['database'],
        $config['username'],
        $config['password'],
        $config['charset'],
        $config['options']
    );

    return call_user_func($closure, $connection);
}/*}}}*/

function _oracle_sql_binds($sql_template, array $binds)
{/*{{{*/
    $res_binds = [];

    foreach ($binds as $key => $value) {
        if (is_array($value)) {
            $subbind_keys = [];
            foreach ($value as $i => $sub_value) {
                $subbind_keys[] = $subbind_key = $key.$i.'p';
                $res_binds[$subbind_key] = $sub_value;
            }

            $sql_template = str_replace($key, '('.implode(',', $subbind_keys).')', $sql_template);
        } else {
            $res_binds[$key] = $value;
        }
    }

    return [$sql_template, $res_binds];
}/*}}}*/

function db_force_type_write($bool = null)
{/*{{{*/
    static $container = false;

    if (! is_null($bool)) {
        $container = $bool;
    }

    return $container;
}/*}}}*/

function db_query($sql_template, array $binds = [], $config_key = 'default')
{/*{{{*/
    list($sql_template, $binds) = _oracle_sql_binds($sql_template, $binds);

    return _oracle_database_closure($config_key, 'read', function ($connection) use ($sql_template, $binds) {

        $st = $connection->prepare($sql_template);

        $st->execute($binds);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    });
}/*}}}*/

function db_query_first($sql_template, array $binds = [], $config_key = 'default')
{/*{{{*/
    $sql_template = str_finish($sql_template, ' limit 1');

    list($sql_template, $binds) = _oracle_sql_binds($sql_template, $binds);

    return _oracle_database_closure($config_key, 'read', function ($connection) use ($sql_template, $binds) {

        $st = $connection->prepare($sql_template);

        $st->execute($binds);

        return $st->fetch(PDO::FETCH_ASSOC);
    });
}/*}}}*/

function db_query_column($column, $sql_template, array $binds = [], $config_key = 'default')
{/*{{{*/
    $rows = db_query($sql_template, $binds, $config_key);

    $res = [];

    foreach ($rows as $row) {
        $res[] = $row[$column];
    }

    return $res;
}/*}}}*/

function db_query_value($value, $sql_template, array $binds = [], $config_key = 'default')
{/*{{{*/
    $row = db_query_first($sql_template, $binds, $config_key);

    return $row[$value];
}/*}}}*/

function db_update($sql_template, array $binds = [], $config_key = 'default')
{/*{{{*/
    list($sql_template, $binds) = _oracle_sql_binds($sql_template, $binds);

    return _oracle_database_closure($config_key, 'write', function ($connection) use ($sql_template, $binds) {

        $st = $connection->prepare($sql_template);

        $st->execute($binds);

        return $st->rowCount();
    });
}/*}}}*/

function db_delete($sql_template, array $binds = [], $config_key = 'default')
{/*{{{*/
    list($sql_template, $binds) = _oracle_sql_binds($sql_template, $binds);

    return _oracle_database_closure($config_key, 'write', function ($connection) use ($sql_template, $binds) {

        $st = $connection->prepare($sql_template);

        $st->execute($binds);

        return $st->rowCount();
    });
}/*}}}*/

function db_insert($sql_template, array $binds = [], $config_key = 'default')
{/*{{{*/
    list($sql_template, $binds) = _oracle_sql_binds($sql_template, $binds);

    return _oracle_database_closure($config_key, 'write', function ($connection) use ($sql_template, $binds) {

        $st = $connection->prepare($sql_template);

        $st->execute($binds);

        return $connection->lastInsertId();
    });
}/*}}}*/

function db_write($sql_template, array $binds = [], $config_key = 'default')
{/*{{{*/
    list($sql_template, $binds) = _oracle_sql_binds($sql_template, $binds);

    return _oracle_database_closure($config_key, 'write', function ($connection) use ($sql_template, $binds) {

        $st = $connection->prepare($sql_template);

        $st->execute($binds);

        return $st->rowCount();
    });
}/*}}}*/

function db_structure($sql, $config_key = 'default')
{/*{{{*/
    return _oracle_database_closure($config_key, 'schema', function ($connection) use ($sql) {

        $st = $connection->prepare($sql);

        $st->execute();

        return $st->rowCount();
    });
}/*}}}*/

function db_transaction(closure $action, $config_key = 'default')
{/*{{{*/
    db_force_type_write(true);

    return _oracle_database_closure($config_key, 'write', function ($connection) use ($action) {

        $began = $connection->beginTransaction();

        if (!$began) {
            throw new Exception('can not start transaction');
        }

        try {
            $res = $action();

            $connection->commit();

            return $res;
        } catch (Exception $ex) {
            $connection->rollBack();

            throw $ex;
        } finally {
            db_force_type_write(false);
        }
    });
}/*}}}*/

function db_simple_where_sql(array $wheres)
{/*{{{*/
    if (empty($wheres)) {
        return ['1 = 1', []];
    }

    $where_sqls = $binds = [];

    foreach ($wheres as $column => $value) {
        if (is_array($value)) {
            $where_sqls[] = "`$column` in :w_$column";
        } else {
            $column_info = explode(' ', $column);
            $column = $column_info[0];
            $symbol = $column_info[1] ?? '=';
            $where_sqls[] = "`$column` $symbol :w_$column";
        }
        $binds[":w_$column"] = $value;
    }

    return [implode(' and ', $where_sqls), $binds];
}/*}}}*/

function db_simple_insert($table, array $data, $config_key = 'default')
{/*{{{*/
    $columns = $values = $binds = [];

    foreach ($data as $column => $value) {
        $columns[] = $column;
        $values[] = ":$column";
        $binds[":$column"] = $value;
    }

    $sql_template = 'insert into `'.$table.'` (`'.implode('`, `', $columns).'`) values ('.implode(', ', $values).')';

    return db_insert($sql_template, $binds, $config_key);
}/*}}}*/

function db_simple_multi_insert($table, array $datas, $config_key = 'default')
{/*{{{*/
    $data_sql_templates = $columns = $binds = [];

    foreach ($datas as $k => $data) {

        $values = [];

        foreach ($data as $column => $value) {
            $columns[$column] = true;
            $values[] = ":i_$column$k";
            $binds[":i_$column$k"] = $value;
        }

        $data_sql_templates[] = '('.implode(', ', $values).')';
    }

    $sql_template = 'insert into `'.$table.'` (`'.implode('`, `', array_keys($columns)).'`) values '.implode(', ', $data_sql_templates);

    return db_insert($sql_template, $binds, $config_key);
}/*}}}*/

function db_simple_update($table, array $wheres, array $data, $config_key = 'default')
{/*{{{*/
    list($where, $binds) = db_simple_where_sql($wheres);

    $update = [];

    foreach ($data as $column => $value) {
        $update[] = "`$column` = :u_$column";
        $binds[":u_$column"] = $value;
    }

    $sql_template = 'update `'.$table.'` set '.implode(', ', $update).' where '.$where;

    return db_update($sql_template, $binds, $config_key);
}/*}}}*/

function db_simple_delete($table, array $wheres, $config_key = 'default')
{/*{{{*/
    list($where, $binds) = db_simple_where_sql($wheres);

    $sql_template = 'delete from `'.$table.'` where '.$where;

    return db_delete($sql_template, $binds, $config_key);
}/*}}}*/

function db_simple_query($table, array $wheres, $option_sql = 'order by id', $config_key = 'default')
{/*{{{*/
    list($where, $binds) = db_simple_where_sql($wheres);

    return db_query('select * from `'.$table.'` where '.$where.' '.$option_sql, $binds, $config_key);
}/*}}}*/

function db_simple_query_first($table, array $wheres, $option_sql = '', $config_key = 'default')
{/*{{{*/
    list($where, $binds) = db_simple_where_sql($wheres);

    return db_query_first('select * from `'.$table.'` where '.$where.' '.$option_sql, $binds, $config_key);
}/*}}}*/

function db_simple_query_indexed($table, $indexed, array $wheres, $option_sql = 'order by id', $config_key = 'default')
{/*{{{*/
    $datas = db_simple_query($table, $wheres, $option_sql, $config_key);

    $res = [];

    foreach ($datas as $data) {
        $res[$data[$indexed]] = $data;
    }

    return $res;
}/*}}}*/

function db_simple_query_value($table, $value, array $wheres, $option_sql = '', $config_key = 'default') {}
{/*{{{*/
  list($where, $binds) = db_simple_where_sql($wheres);

  $row = db_query_value($value, "select `$value` from `$table` where $where $option_sql", $binds, $config_key);

  return $row[$value];
}/*}}}*/
