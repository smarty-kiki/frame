<?php

function db_force_type_write($bool = null) { }

function db_query($sql_template, array $binds = [], $database = 'default') { }
function db_query_first($sql_template, array $binds = [], $database = 'default') { }
function db_query_column($column, $sql_template, array $binds = [], $database = 'default') { }
function db_query_value($value, $sql_template, array $binds = [], $database = 'default') { }

function db_update($sql_template, array $binds = [], $database = 'default') { }
function db_delete($sql_template, array $binds = [], $database = 'default') { }
function db_insert($sql_template, array $binds = [], $database = 'default') { }
function db_write($sql_template, array $binds = [], $database = 'default') { }

function db_structure($sql, $database = 'default') { }

function db_transaction(closure $action, $database = 'default') { }

function db_close() {}

function db_simple_where_sql(array $wheres) {}

function db_simple_insert($table, array $data, $config_key = 'default') { }
function db_simple_multi_insert($table, array $datas, $config_key = 'default') {}

function db_simple_update($table, array $wheres, array $data, $config_key = 'default') {}

function db_simple_delete($table, array $wheres, $config_key = 'default') {}

function db_simple_query($table, array $wheres, $option_sql = 'order by id', $config_key = 'default') {}
function db_simple_query_first($table, array $wheres, $option_sql = '', $config_key = 'default') {}
function db_simple_query_indexed($table, $indexed, array $wheres, $option_sql = 'order by id', $config_key = 'default') {}
function db_simple_query_value($table, $value, array $wheres, $option_sql = '', $config_key = 'default') {}
