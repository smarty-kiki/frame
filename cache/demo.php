<?php

function cache_get($key, $config_key = 'default') { }
function cache_multi_get(array $keys, $config_key = 'default') { }

function cache_set($key, $value, $expires = 0, $config_key = 'default') { }
function cache_add($key, $value, $expires = 0, $config_key = 'default') { }
function cache_replace($key, $value, $expires = 0, $config_key = 'default') { }

function cache_delete($key, $config_key = 'default') { }
function cache_multi_delete(array $keys, $config_key = 'default') { }

function cache_increment($key, $number = 1, $expires = 0, $config_key = 'default') { }
function cache_decrement($key, $number = 1, $expires = 0, $config_key = 'default') { }

function cache_close() {}
