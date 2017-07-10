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
