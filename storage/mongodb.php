<?php

use \MongoDB\Driver\BulkWrite;
use \MongoDB\Driver\WriteConcern;
use \MongoDB\Driver\Manager;
use \MongoDB\Driver\Query;

use \MongoDB\BSON\ObjectID;

function _mongodb_connection($host, $port, $database, $username, $password)
{/*{{{*/
    static $container = [];

    $dsn = "mongodb://{$host}:{$port}/{$database}";

    if (! isset($container[$dsn])) {

        $options = [];

        if ($username) {
            $options['username'] = $username;
        }

        if ($password) {
            $options['password'] = $password;
        }

        $container[$dsn] = new Manager($dsn, $options);
    }

    return $container[$dsn];
}/*}}}*/

function _mongodb_database_closure($config_key, closure $closure)
{/*{{{*/
    $config = config_midware('mongodb', $config_key);

    $connection = _mongodb_connection(
        $config['host'],
        $config['port'],
        $config['database'],
        $config['username'],
        $config['password']
    );

    return call_user_func($closure, $connection, $config);
}/*}}}*/

function __mongodb_dbc($database, $table)
{/*{{{*/
    return $database.'.'.$table;
}/*}}}*/

function _mongodb_database_write($config_key, $table, BulkWrite $bulk)
{/*{{{*/
    return _mongodb_database_closure($config_key, function ($connection, $config) use ($table, $bulk) {

        $wc = new WriteConcern(WriteConcern::MAJORITY, $config['timeout'] * 1000);

        return $connection->executeBulkWrite(__mongodb_dbc($config['database'], $table), $bulk, $wc);

    });
}/*}}}*/

function _mongodb_database_read($config_key, $table, Query $query)
{/*{{{*/
    return _mongodb_database_closure($config_key, function ($connection, $config) use ($table, $query) {

        $cursor = $connection->executeQuery(__mongodb_dbc($config['database'], $table), $query);

        return $cursor->toArray();
    });
}/*}}}*/

function storage_insert($table, array $data, $config_key = 'default')
{/*{{{*/
    $bulk = new BulkWrite();

    $bulk->insert($data);

    $result = _mongodb_database_write($config_key, $table, $bulk);

    return $result->getInsertedCount();
}/*}}}*/

function storage_multi_insert($table, array $datas, $config_key = 'default')
{/*{{{*/
    $bulk = new BulkWrite();

    foreach ($datas as $data) {
        $bulk->insert($data);
    }

    $result = _mongodb_database_write($config_key, $table, $bulk);

    return $result->getInsertedCount();
}/*}}}*/

function storage_query($table, array $selections = [],  array $queries = [], array $sorts = [], $offset = 0, $limit = 1000, $config_key = 'default')
{/*{{{*/
    $projection = ['_id' => 0];

    foreach ($selections as $selection) {
        $projection[$selection] = 1;
    }

    $query = new Query($queries, [
        'projection' => $projection,
        "sort" => $sorts,
        "skip" => $offset,
        "limit" => $limit,
    ]);

    $res = _mongodb_database_read($config_key, $table, $query);

    return json_decode(json_encode($res), true);
}/*}}}*/

function storage_find($table, $id, $config_key = 'default')
{/*{{{*/
    $datas = storage_query(
        $table,
        $selections = [],
        $queries = [
            '_id' => new ObjectID($id),
        ],
        $sorts = [],
        $offset = 0,
        $limit = 1,
        $config_key
    );

    return reset($datas);
}/*}}}*/

function storage_update($table, array $queries = [], array $new_data, $config_key = 'default')
{/*{{{*/
    $bulk = new BulkWrite();
    $bulk->update(
        $queries,
        $new_data,
        [
            'upsert' => false
        ]
    );

    $result = _mongodb_database_write($config_key, $table, $bulk);

    return $result->getModifiedCount();
}/*}}}*/

function storage_delete($table, array $queries = [], $config_key = 'default')
{/*{{{*/
    $bulk = new BulkWrite();
    $bulk->delete($queries, [
        'limit' => 0,
    ]);

    $result = _mongodb_database_write($config_key, $table, $bulk);

    return $result->getDeletedCount();
}/*}}}*/
