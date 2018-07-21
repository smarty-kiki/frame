<?php

function queue_finish_action() {}

function queue_job_pickup($job_name) {}

function queue_jobs($jobs = null) {}

function queue_job($job_name, closure $closure, $priority = 10, $retry = [], $tube = 'default', $config_key = 'default') {}

function queue_push($job_name, array $data = [], $delay = 0) {}

function queue_watch($tube = 'default', $config_key = 'default') {}

function queue_status($tube = 'default', $config_key = 'default') {}
