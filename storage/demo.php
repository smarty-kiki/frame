<?php

function storage_insert($table, array $data, $config_key = 'default') {}
function storage_multi_insert($table, array $datas, $config_key = 'default') {}

function storage_query($table, array $selections = [],  array $queries = [], array $sorts = [], $offset = 0, $limit = 1000, $config_key = 'default') {}
function storage_find($table, $id, $config_key = 'default') {}

function storage_update($table, array $queries = [], array $new_data, $config_key = 'default') { }
function storage_delete($table, array $queries = [], $config_key = 'default') {}
