<?php

define('IDGENTER_CACHE_MIDWARE_KEY', 'idgenter');

function unit_of_work_db_config_key($config_key = null)
{
    static $container = 'default';

    if (!is_null($config_key)) {
        $container = $config_key;
    }

    return $container;
}

function _unit_of_work_write($sql_template, array $binds = [], $config_key = 'default')
{
    $row_count = db_write($sql_template, $binds, $config_key);

    otherwise($row_count === 1, 'data in unit of work is expired');
}

function if_unit_of_work_executed($action = null)
{
    static $container = null;

    if (is_array($action)) {
        return $container = null;
    } else if (! empty($action)) {
        return $container = $action;
    } 

    return $container;
}

function if_unit_of_work_disturbed($action = null, $ex = null)
{
    static $container = null;

    if (is_array($action)) {
        return $container = null;
    } else if (! empty($action)) {
        return $container = $action;
    } 

    return $container;
}

function clean_unit_of_work_delegate()
{
    if_unit_of_work_executed([]);
    if_unit_of_work_disturbed([]);
}

function unit_of_work(Closure $action)
{
    local_cache_delete_all();
    clean_unit_of_work_delegate();

    try {
        try {
            $res = $action();

            $entities = local_cache_get_all();
        } finally {
            local_cache_delete_all();
        }

        $sqls = [];

        $db_config_key = unit_of_work_db_config_key();

        foreach ($entities as $entity) {

            $dao = $entity->get_dao();

            if ($dao->get_db_config_key() !== $db_config_key) {
                continue;
            }

            if ($entity->just_force_deleted()) {
                if (!$entity->just_new()) {
                    $sqls[] = $dao->dump_delete_sql($entity);
                }
            } elseif ($entity->just_new()) {
                $sqls[] = $dao->dump_insert_sql($entity);
            } elseif ($entity->just_updated() || $entity->just_deleted()) {
                $sqls[] = $dao->dump_update_sql($entity);
            }
        }

        if ($sqls) {
            if (count($sqls) > 1) {
                db_transaction(function () use ($sqls, $db_config_key) {
                    foreach ($sqls as $sql) {
                        _unit_of_work_write($sql['sql_template'], $sql['binds'], $db_config_key);
                    }
                }, $db_config_key);
            } else {
                $sql = reset($sqls);
                _unit_of_work_write($sql['sql_template'], $sql['binds'], $db_config_key);
            }
        }

        $action = if_unit_of_work_executed();
        if ($action instanceof closure) {
            call_user_func($action);
            clean_unit_of_work_delegate();
        }

    } catch (exception $ex) {

        $action = if_unit_of_work_disturbed();
        if ($action instanceof closure) {
            call_user_func($action, $ex);
            clean_unit_of_work_delegate();
        }

        throw $ex;
    }

    return $res;
}

function generate_id($mark = 'idgenter')
{
    static $step = 1;

    static $generators = [];

    if (! array_key_exists($mark, $generators)) {

        $generator = $generators[$mark] = function () use ($step, $mark) {

            static $now_id;
            static $step_last_id;

            if ($now_id === $step_last_id) {
                $step_last_id = cache_increment($mark.'_last_id', $step, 0, IDGENTER_CACHE_MIDWARE_KEY);
                $now_id = $step_last_id - $step;
            }

            return $now_id += 1;
        };
    } else {

        $generator = $generators[$mark];
    }

    return call_user_func($generator);
}
