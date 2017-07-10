<?php

function unit_of_work_system_code($system_code = null)
{
    static $container = null;

    if (!is_null($system_code)) {
        $container = $system_code;
    }

    return $container;
}

function _unit_of_work_write($sql_template, array $binds = [])
{
    $row_count = db_write($sql_template, $binds);

    otherwise($row_count === 1, 'data in unit of work is expired');
}

function unit_of_work(Closure $action)
{
    local_cache_delete_all();

    try {
        $res = $action();

        $entities = local_cache_get_all();
    } finally {
        local_cache_delete_all();
    }

    $sqls = [];

    foreach ($entities as $entity) {
        if ($entity->get_system_code() !== unit_of_work_system_code()) {
            continue;
        }

        $dao = $entity->get_dao();

        if ($entity->is_force_deleted()) {
            if (!$entity->is_new()) {
                $sqls[] = $dao->dump_delete_sql($entity);
            }
        } elseif ($entity->is_new()) {
            $sqls[] = $dao->dump_insert_sql($entity);
        } elseif ($entity->is_updated()) {
            $sqls[] = $dao->dump_update_sql($entity);
        }
    }

    if ($sqls) {
        if (count($sqls) > 1) {
            db_transaction(function () use ($sqls) {
                foreach ($sqls as $sql) {
                    _unit_of_work_write($sql['sql_template'], $sql['binds']);
                }
            });
        } else {
            $sql = reset($sqls);
            _unit_of_work_write($sql['sql_template'], $sql['binds']);
        }
    }

    return $res;
}

function generate_id($mark = 'idgenter')
{
    static $step = 1;

    static $now_id;
    static $step_last_id;

    if ($now_id == $step_last_id) {
        $step_last_id = cache_increment($mark.'_last_id', $step, 0, 'idgenter');
        $now_id = $step_last_id - $step;
    }

    return $now_id += 1;
}
