<?php

function _mysql_connection($host, $port, $database, $username, $password, $charset, $collation, $options = [])
{/*{{{*/
    static $container = [];

    $dsn = "mysql:host={$host};dbname={$database};port={$port}";

    $identifier = $dsn.'|'.$username.'|'.$password;

    if (!isset($container[$identifier])) {

        $connection = new PDO($dsn, $username, $password, $options);

        $connection->prepare("set names '{$charset}' collate '{$collation}'")->execute();

        $container[$identifier] = $connection;
    }

    return $container[$identifier];
}/*}}}*/

function _mysql_database_closure($config_key, $type, closure $closure)
{/*{{{*/
    static $configs = [];

    if (empty($configs)) {
        $configs = config('mysql');
    }

    $type = db_force_type_write()? 'write': $type;

    $config = $configs[$config_key];

    $connection = _mysql_connection(
        $host = array_rand($config[$type]),
        $port = $config[$type][$host],
        $config['database'],
        $config['username'],
        $config['password'],
        $config['charset'],
        $config['collation'],
        $configs['options']
    );

    return call_user_func($closure, $connection);
}/*}}}*/

function _mysql_sql_binds($sql_template, array $binds)
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
    list($sql_template, $binds) = _mysql_sql_binds($sql_template, $binds);

    return _mysql_database_closure($config_key, 'read', function ($connection) use ($sql_template, $binds) {

        $st = $connection->prepare($sql_template);

        $st->execute($binds);

        return $st->fetchAll(PDO::FETCH_ASSOC);
    });
}/*}}}*/

function db_query_first($sql_template, array $binds = [], $config_key = 'default')
{/*{{{*/
    $sql_template = str_finish($sql_template, ' limit 1');

    list($sql_template, $binds) = _mysql_sql_binds($sql_template, $binds);

    return _mysql_database_closure($config_key, 'read', function ($connection) use ($sql_template, $binds) {

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
    list($sql_template, $binds) = _mysql_sql_binds($sql_template, $binds);

    return _mysql_database_closure($config_key, 'write', function ($connection) use ($sql_template, $binds) {

        $st = $connection->prepare($sql_template);

        $st->execute($binds);

        return $st->rowCount();
    });
}/*}}}*/

function db_delete($sql_template, array $binds = [], $config_key = 'default')
{/*{{{*/
    list($sql_template, $binds) = _mysql_sql_binds($sql_template, $binds);

    return _mysql_database_closure($config_key, 'write', function ($connection) use ($sql_template, $binds) {

        $st = $connection->prepare($sql_template);

        $st->execute($binds);

        return $st->rowCount();
    });
}/*}}}*/

function db_insert($sql_template, array $binds = [], $config_key = 'default')
{/*{{{*/
    list($sql_template, $binds) = _mysql_sql_binds($sql_template, $binds);

    return _mysql_database_closure($config_key, 'write', function ($connection) use ($sql_template, $binds) {

        $st = $connection->prepare($sql_template);

        $st->execute($binds);

        return $connection->lastInsertId();
    });
}/*}}}*/

function db_write($sql_template, array $binds = [], $config_key = 'default')
{/*{{{*/
    list($sql_template, $binds) = _mysql_sql_binds($sql_template, $binds);

    return _mysql_database_closure($config_key, 'write', function ($connection) use ($sql_template, $binds) {

        $st = $connection->prepare($sql_template);

        $st->execute($binds);

        return $st->rowCount();
    });
}/*}}}*/

function db_structure($sql, $config_key = 'default')
{/*{{{*/
    return _mysql_database_closure($config_key, 'schema', function ($connection) use ($sql) {

        $st = $connection->prepare($sql);

        $st->execute();

        return $st->rowCount();
    });
}/*}}}*/

function db_transaction(closure $action, $config_key = 'default')
{/*{{{*/
    db_force_type_write(true);

    return _mysql_database_closure($config_key, 'write', function ($connection) use ($action) {

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
