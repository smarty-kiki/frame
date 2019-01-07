<?php

function _command_prepare_arguments()
{/*{{{*/
    static $file_name = '';
    static $command = '';
    static $arguments = [];

    if (! $file_name) {
        global $argv;
        $file_name = array_shift($argv);
        $command = array_shift($argv);

        foreach ($argv as $num => $argument) {

            switch (true) {

            case preg_match('/^-([a-zA-Z]+)$/', $argument, $res):
                $arguments[$res[1]] = true;
                break;

            case preg_match('/^--([a-zA-Z_]+)=(.*)$/', $argument, $res):
                $arguments[$res[1]] = $res[2];
                break;
            }
        }
    }

    return [$file_name, $command, $arguments];
}/*}}}*/

function command_paramater($key, $default = null)
{/*{{{*/
    list($file_name, $command, $arguments) = _command_prepare_arguments();

    if (! isset($arguments[$key])) {
        if (is_null($default)) {
            echo "需要加 --$key=xxx 或者 -$key\n";
            exit(1);
        } else {
            return $default;
        }
    }

    return $arguments[$key];
}/*}}}*/

function command($rule, $description, closure $action)
{/*{{{*/
    list($file_name, $command, $arguments) = _command_prepare_arguments();

    if ($command === $rule) {
        exit($action());
    } else {
        command_not_found($rule, $description);
    }
}/*}}}*/

function if_command_not_found(closure $action = null)
{/*{{{*/
    static $container = null;

    if (!empty($action)) {
        return $container = $action;
    }

    return $container;
}/*}}}*/

function command_not_found($rule = null, $description = null)
{/*{{{*/
    static $rules = [];
    static $descriptions = [];

    if (is_null($rule) && is_null($description)) {
        call_user_func(if_command_not_found(), $rules, $descriptions);
        exit;
    } else {
        $rules[] = $rule;
        $descriptions[] = $description;
    }
}/*}}}*/

function command_read($prompt, $default = true, array $options = [])
{/*{{{*/
    if ($options) {
        $prompt = "$prompt (Default: $default)\n\n";
        foreach ($options as $key => $option) {
            $prompt .= "  $key) $option\n";
        }

        $prompt .= "\n> ";

        do {
            fwrite(STDOUT, $prompt);  
            $result = trim(fgets(STDIN));  
            $result = ($result === '')? $default: $result;
        } while (! array_key_exists($result, $options));

        return $options[$result];
    } else {
        $prompt = "$prompt (Default: $default)\n> ";
        fwrite(STDOUT, $prompt);  
        $result = trim(fgets(STDIN));  
        return ($result === '')? $default: $result;
    }
}/*}}}*/

function command_read_bool($prompt, $default = 'n')
{/*{{{*/
    $map = [
        'y' => true,
        'n' => false,
    ];

    do {

        $res = command_read("$prompt [y/n]?", $default);

    } while (! array_key_exists($res, $map));

    return $map[$res];
}/*}}}*/
