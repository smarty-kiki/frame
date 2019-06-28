<?php

function client_call($service_name, $method, $args = [])
{
    $configs = config('client');
    $config = $configs[$service_name];

    $host = $config['host'];
    $ips = $config['ips'];
    $timeout = $config['timeout'];
    $retry = $config['retry'];

    $ip = $ips[array_rand($ips)];

    $raw_data = remote_post('http://'.$ip.'/'.$method, serialize($args), $timeout, $retry, ["host: $host"]);

    if (false === $raw_data) {
        return false;
    }

    $data = unserialize($raw_data);

    if ($data['res']) {
        return $data['data'];
    } else {
        throw new $data['exception']['class']($data['exception']['message']);
    }
}
