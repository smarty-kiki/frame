<?php

/**
 * response
 */

function _agent_staged_response_messages($messages = null)
{/*{{{*/
    static $container = [];

    if (is_null($messages)) {
        return $container;
    }

    return $container = $messages;
}/*}}}*/

function _agent_stage_response_message($message)
{/*{{{*/
    $messages = _agent_staged_response_messages();
    $message = _agent_message_user_visible_message($message);

    if (empty($message['content'])) {
        return $messages;
    }

    $messages[] = $message;

    return _agent_staged_response_messages($messages);
}/*}}}*/

function _agent_pick_staged_response_messages()
{/*{{{*/
    $messages = _agent_staged_response_messages();

    _agent_staged_response_messages([]);

    return $messages;
}/*}}}*/

function _agent_message_payload(array $message): array
{/*{{{*/
    $content = $message['content'] ?? null;

    otherwise(
        is_string($content) && $content !== '',
        'assistant message content 必须是非空 JSON 字符串'
    );

    $payload = json_decode($content, true);

    otherwise(
        is_array($payload),
        'assistant message content 不是合法 JSON'
    );

    otherwise(
        array_key_exists('content', $payload),
        'assistant message content JSON 缺少 content 字段'
    );

    otherwise(
        array_key_exists('task_state', $payload),
        'assistant message content JSON 缺少 task_state 字段'
    );

    return $payload;
}/*}}}*/

function _agent_message_user_visible_message(array $message): array
{/*{{{*/
    $payload = _agent_message_payload($message);

    $message['content'] = $payload['content'];

    return $message;
}/*}}}*/

function _agent_message_task_state(array $message)
{/*{{{*/
    $payload = _agent_message_payload($message);

    $task_state = $payload['task_state'];

    otherwise(
        in_array($task_state, ['continue', 'finished', 'need_user_input'], true),
        'assistant message content JSON 的 task_state 不合法'
    );

    return $task_state;
}/*}}}*/

/**
 * session
 */

/**
 * 注册闭包调用参数: ($user_name)
 */
function if_get_session_key(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($session_key)
 */
function if_read_user_session_messages(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($session_key, $message)
 */
function if_append_user_session_message(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($session_key, $messages)
 */
function if_overwrite_user_session_messages(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($session_key)
 */
function if_reset_user_session_messages(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

function _user_session_key(string $user_name)
{/*{{{*/
    $get_session_key_closure = if_get_session_key();

    otherwise(
        $get_session_key_closure instanceof closure,
        '必须先实现 [if_get_session_key] 闭包'
    );

    return $get_session_key_closure($user_name);
}/*}}}*/

function _user_session_messages(string $user_name): array
{/*{{{*/
    $session_key = _user_session_key($user_name);

    if (empty($session_key)) {
        return [];
    }

    $read_user_session_messages_closure = if_read_user_session_messages();

    otherwise(
        $read_user_session_messages_closure instanceof closure,
        '必须先实现 [if_read_user_session_messages] 闭包'
    );

    return (array) $read_user_session_messages_closure($session_key);
}/*}}}*/

function _user_session_append_message(string $user_name, array $message): array
{/*{{{*/
    $session_key = _user_session_key($user_name);

    if (empty($session_key)) {
        return [];
    }

    $append_user_session_message_closure = if_append_user_session_message();

    otherwise(
        $append_user_session_message_closure instanceof closure,
        '必须先实现 [if_append_user_session_message] 闭包'
    );

    return (array) $append_user_session_message_closure($session_key, $message);
}/*}}}*/

function _user_session_overwrite_messages(string $user_name, array $messages): array
{/*{{{*/
    $session_key = _user_session_key($user_name);

    if (empty($session_key)) {
        return [];
    }

    $overwrite_user_session_messages_closure = if_overwrite_user_session_messages();

    otherwise(
        $overwrite_user_session_messages_closure instanceof closure,
        '必须先实现 [if_overwrite_user_session_messages] 闭包'
    );

    return (array) $overwrite_user_session_messages_closure($session_key, $messages);
}/*}}}*/

function _user_session_reset_messages(string $user_name): array
{/*{{{*/
    $session_key = _user_session_key($user_name);

    if (empty($session_key)) {
        return [];
    }

    $reset_user_session_messages_closure = if_reset_user_session_messages();

    otherwise(
        $reset_user_session_messages_closure instanceof closure,
        '必须先实现 [if_reset_user_session_messages] 闭包'
    );

    return (array) $reset_user_session_messages_closure($session_key);
}/*}}}*/

function user_session(string $user_name): array
{/*{{{*/
    return _user_session_messages($user_name);
}/*}}}*/

function user_session_compress(string $user_name): array
{/*{{{*/
    $messages = _user_session_messages($user_name);

    // todo::kiki 在这里把长会话压缩成更短的 message 数组，再覆盖写回 session
    $compressed_messages = $messages;

    return _user_session_overwrite_messages($user_name, $compressed_messages);
}/*}}}*/

function user_session_reset(string $user_name): array
{/*{{{*/
    return _user_session_reset_messages($user_name);
}/*}}}*/

/**
 * tool
 */

/**
 * 注册闭包调用参数: ($path, $user_name)
 */
function if_agent_tool_read_permission(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($path, $user_name)
 */
function if_agent_tool_read_directory_permission(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($path, $user_name)
 */
function if_agent_tool_write_permission(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($path, $user_name)
 */
function if_agent_tool_delete_directory_permission(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($path, $user_name)
 */
function if_agent_tool_delete_permission(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($cwd, $user_name, $command)
 */
function if_agent_tool_run_command_permission(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

function _agent_tool_simple_infos(array $tool_names = [])
{/*{{{*/
    $infos = [];

    $tools = _agent_tools();

    if ($tool_names) {
        $picked_tools = [];

        foreach ($tool_names as $tool_name) {
            otherwise(
                array_key_exists($tool_name, $tools),
                "agent 工具 [$tool_name] 不存在"
            );

            $picked_tools[$tool_name] = $tools[$tool_name];
        }

        $tools = $picked_tools;
    }

    foreach ($tools as $tool) {
        $infos[] = [
            'name' => $tool['name'],
            'description' => $tool['description'],
        ];
    }

    return $infos;
}/*}}}*/

function _agent_tools()
{/*{{{*/
    static $container = null;

    if (is_null($container)) {
        $container = [
            'read_file' => [
                'name' => 'read_file',
                'description' => '读取指定文件内容，可选按行范围读取',
                'parameters' => [
                    'path' => '需要读取的文件绝对路径或相对路径',
                    'start_line' => '可选，开始行号，从 1 开始',
                    'end_line' => '可选，结束行号',
                ],
                'require' => ['path'],
                'closure' => function ($arguments, $tool_call, $user_name) {
                    $path = $arguments['path'];
                    $start_line = $arguments['start_line'] ?? null;
                    $end_line = $arguments['end_line'] ?? null;

                    return [
                        'path' => $path,
                        'content' => _agent_tool_file_read($path, $user_name, $start_line, $end_line),
                    ];
                },
            ],
            'write_file' => [
                'name' => 'write_file',
                'description' => '写入指定文件内容，可选择覆盖或追加',
                'parameters' => [
                    'path' => '需要写入的文件绝对路径或相对路径',
                    'content' => '要写入文件的完整内容',
                    'append' => '可选，true 表示追加写入，默认覆盖',
                ],
                'require' => ['path', 'content'],
                'closure' => function ($arguments, $tool_call, $user_name) {
                    $path = $arguments['path'];
                    $content = $arguments['content'];
                    $append = _agent_tool_bool($arguments['append'] ?? false);

                    return [
                        'path' => $path,
                        'append' => $append,
                        'bytes' => _agent_tool_file_write($path, $content, $user_name, $append),
                    ];
                },
            ],
            'read_directory' => [
                'name' => 'read_directory',
                'description' => '读取指定目录内容，可选指定递归显示深度，默认显示当前目录一层',
                'parameters' => [
                    'path' => '需要读取的目录绝对路径或相对路径',
                    'depth' => '可选，递归显示深度，默认 1',
                ],
                'require' => ['path'],
                'closure' => function ($arguments, $tool_call, $user_name) {
                    $path = $arguments['path'];
                    $depth = $arguments['depth'] ?? 1;

                    return _agent_tool_directory_read($path, $user_name, $depth);
                },
            ],
            'delete_file' => [
                'name' => 'delete_file',
                'description' => '删除指定文件',
                'parameters' => [
                    'path' => '需要删除的文件绝对路径或相对路径',
                ],
                'require' => ['path'],
                'closure' => function ($arguments, $tool_call, $user_name) {
                    $path = $arguments['path'];

                    return [
                        'path' => $path,
                        'deleted' => _agent_tool_file_delete($path, $user_name),
                    ];
                },
            ],
            'delete_directory' => [
                'name' => 'delete_directory',
                'description' => '递归删除指定目录',
                'parameters' => [
                    'path' => '需要删除的目录绝对路径或相对路径',
                ],
                'require' => ['path'],
                'closure' => function ($arguments, $tool_call, $user_name) {
                    $path = $arguments['path'];

                    return [
                        'path' => $path,
                        'deleted' => _agent_tool_directory_delete($path, $user_name),
                    ];
                },
            ],
            'run_command' => [
                'name' => 'run_command',
                'description' => '执行 linux 终端命令，并返回退出码和输出',
                'parameters' => [
                    'command' => '需要执行的终端命令',
                    'cwd' => '可选，命令执行目录',
                ],
                'require' => ['command'],
                'closure' => function ($arguments, $tool_call, $user_name) {
                    $command = $arguments['command'];
                    $cwd = $arguments['cwd'] ?? null;

                    return _agent_tool_run_command($command, $user_name, $cwd);
                },
            ],
        ];
    }

    return $container;
}/*}}}*/

function _agent_tool_pickup($name)
{/*{{{*/
    $tools = _agent_tools();

    otherwise(
        array_key_exists($name, $tools),
        "agent 工具 [$name] 不存在"
    );

    return $tools[$name];
}/*}}}*/

function _agent_llm_tool_infos(array $tool_names = [])
{/*{{{*/
    $infos = [];

    $tools = _agent_tools();

    if ($tool_names) {
        $picked_tools = [];

        foreach ($tool_names as $tool_name) {
            otherwise(
                array_key_exists($tool_name, $tools),
                "agent 工具 [$tool_name] 不存在"
            );

            $picked_tools[$tool_name] = $tools[$tool_name];
        }

        $tools = $picked_tools;
    }

    foreach ($tools as $tool) {
        $infos[] = llm_tool_info(
            $tool['name'],
            $tool['description'],
            $tool['parameters'],
            $tool['require']
        );
    }

    return $infos;
}/*}}}*/

function _agent_tool_call_arguments(array $tool_call): array
{/*{{{*/
    $arguments = array_get($tool_call, 'function.arguments', '');

    if ($arguments === '' || is_null($arguments)) {
        return [];
    }

    if (is_array($arguments)) {
        return $arguments;
    }

    $decoded_arguments = json_decode($arguments, true);

    otherwise(
        is_array($decoded_arguments),
        '工具调用参数不是合法的 JSON'
    );

    return $decoded_arguments;
}/*}}}*/

function _agent_tool_result_content($result)
{/*{{{*/
    if (is_string($result)) {
        return $result;
    }

    if (is_null($result)) {
        return '';
    }

    return json($result);
}/*}}}*/

function _agent_tool_user_name(array $tool_call, array $arguments = [])
{/*{{{*/
    if (array_key_exists('user_name', $arguments)) {
        return $arguments['user_name'];
    }

    if (array_key_exists('user_name', $tool_call)) {
        return $tool_call['user_name'];
    }

    throw new Exception('工具调用缺少 user_name');
}/*}}}*/

function _agent_tool_assert_read_permission($path, string $user_name)
{/*{{{*/
    $permission_closure = if_agent_tool_read_permission();

    otherwise(
        $permission_closure instanceof closure,
        '必须先实现 [if_agent_tool_read_permission] 闭包'
    );

    otherwise(
        call_user_func($permission_closure, $path, $user_name),
        "当前无权限读取文件 [$path]"
    );
}/*}}}*/

function _agent_tool_assert_read_directory_permission($path, string $user_name)
{/*{{{*/
    $permission_closure = if_agent_tool_read_directory_permission();

    otherwise(
        $permission_closure instanceof closure,
        '必须先实现 [if_agent_tool_read_directory_permission] 闭包'
    );

    otherwise(
        call_user_func($permission_closure, $path, $user_name),
        "当前无权限读取目录 [$path]"
    );
}/*}}}*/

function _agent_tool_assert_write_permission($path, string $user_name)
{/*{{{*/
    $permission_closure = if_agent_tool_write_permission();

    otherwise(
        $permission_closure instanceof closure,
        '必须先实现 [if_agent_tool_write_permission] 闭包'
    );

    otherwise(
        call_user_func($permission_closure, $path, $user_name),
        "当前无权限写入文件 [$path]"
    );
}/*}}}*/

function _agent_tool_assert_delete_directory_permission($path, string $user_name)
{/*{{{*/
    $permission_closure = if_agent_tool_delete_directory_permission();

    otherwise(
        $permission_closure instanceof closure,
        '必须先实现 [if_agent_tool_delete_directory_permission] 闭包'
    );

    otherwise(
        call_user_func($permission_closure, $path, $user_name),
        "当前无权限删除目录 [$path]"
    );
}/*}}}*/

function _agent_tool_assert_delete_permission($path, string $user_name)
{/*{{{*/
    $permission_closure = if_agent_tool_delete_permission();

    otherwise(
        $permission_closure instanceof closure,
        '必须先实现 [if_agent_tool_delete_permission] 闭包'
    );

    otherwise(
        call_user_func($permission_closure, $path, $user_name),
        "当前无权限删除文件 [$path]"
    );
}/*}}}*/

function _agent_tool_assert_run_command_permission($cwd, string $user_name, $command)
{/*{{{*/
    $permission_closure = if_agent_tool_run_command_permission();

    otherwise(
        $permission_closure instanceof closure,
        '必须先实现 [if_agent_tool_run_command_permission] 闭包'
    );

    otherwise(
        call_user_func($permission_closure, $cwd, $user_name, $command),
        '当前无权限执行命令'
    );
}/*}}}*/

function _agent_tool_directory_entry($path, $depth, $now_depth = 1)
{/*{{{*/
    $entry = [
        'name' => basename($path),
        'path' => $path,
        'type' => is_dir($path) ? 'directory' : 'file',
    ];

    if (is_dir($path) && $now_depth < $depth) {
        $entry['children'] = _agent_tool_directory_entries($path, $depth, $now_depth + 1);
    }

    return $entry;
}/*}}}*/

function _agent_tool_directory_entries($path, $depth, $now_depth = 1)
{/*{{{*/
    $entries = [];

    foreach (scandir($path) as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }

        $entries[] = _agent_tool_directory_entry($path.'/'.$name, $depth, $now_depth);
    }

    return $entries;
}/*}}}*/

function _agent_tool_directory_read($path, string $user_name, $depth = 1)
{/*{{{*/
    _agent_tool_assert_read_directory_permission($path, $user_name);

    otherwise(
        is_dir($path),
        "目录 [$path] 不存在"
    );

    otherwise(
        is_readable($path),
        "目录 [$path] 不可读"
    );

    $depth = max(1, (int) $depth);

    return [
        'path' => $path,
        'depth' => $depth,
        'entries' => _agent_tool_directory_entries($path, $depth),
    ];
}/*}}}*/

function _agent_tool_file_read($path, string $user_name, $start_line = null, $end_line = null)
{/*{{{*/
    _agent_tool_assert_read_permission($path, $user_name);

    otherwise(
        is_file($path),
        "文件 [$path] 不存在"
    );

    otherwise(
        is_readable($path),
        "文件 [$path] 不可读"
    );

    if (all_empty($start_line, $end_line)) {
        return file_get_contents($path);
    }

    $lines = file($path);

    $line_count = count($lines);

    $start_line = empty($start_line) ? 1 : max(1, (int) $start_line);
    $end_line = empty($end_line) ? $line_count : min($line_count, (int) $end_line);

    otherwise(
        $start_line <= $end_line,
        '开始行号不能大于结束行号'
    );

    return implode('', array_slice($lines, $start_line - 1, $end_line - $start_line + 1));
}/*}}}*/

function _agent_tool_file_write($path, $content, string $user_name, $append = false)
{/*{{{*/
    _agent_tool_assert_write_permission($path, $user_name);

    $dir = dirname($path);

    if (! is_dir($dir)) {
        otherwise(
            mkdir($dir, 0777, true),
            "目录 [$dir] 创建失败"
        );
    }

    $flags = $append ? FILE_APPEND : 0;

    $bytes = file_put_contents($path, $content, $flags);

    otherwise(
        $bytes !== false,
        "文件 [$path] 写入失败"
    );

    return $bytes;
}/*}}}*/

function _agent_tool_directory_delete_entries($path)
{/*{{{*/
    foreach (scandir($path) as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }

        $sub_path = $path.'/'.$name;

        if (is_dir($sub_path)) {
            _agent_tool_directory_delete_entries($sub_path);

            otherwise(
                rmdir($sub_path),
                "目录 [$sub_path] 删除失败"
            );
        } else {
            otherwise(
                unlink($sub_path),
                "文件 [$sub_path] 删除失败"
            );
        }
    }
}/*}}}*/

function _agent_tool_file_delete($path, string $user_name)
{/*{{{*/
    _agent_tool_assert_delete_permission($path, $user_name);

    otherwise(
        is_file($path),
        "文件 [$path] 不存在"
    );

    otherwise(
        unlink($path),
        "文件 [$path] 删除失败"
    );

    return true;
}/*}}}*/

function _agent_tool_directory_delete($path, string $user_name)
{/*{{{*/
    _agent_tool_assert_delete_directory_permission($path, $user_name);

    otherwise(
        is_dir($path),
        "目录 [$path] 不存在"
    );

    _agent_tool_directory_delete_entries($path);

    otherwise(
        rmdir($path),
        "目录 [$path] 删除失败"
    );

    return true;
}/*}}}*/

function _agent_tool_run_command($command, string $user_name, $cwd = null)
{/*{{{*/
    _agent_tool_assert_run_command_permission($cwd, $user_name, $command);

    otherwise(
        not_empty($command),
        '命令不能为空'
    );

    $old_cwd = getcwd();

    if (not_empty($cwd)) {
        otherwise(
            is_dir($cwd),
            "命令执行目录 [$cwd] 不存在"
        );

        chdir($cwd);
    }

    try {
        $output = [];
        $exit_code = 0;

        exec($command.' 2>&1', $output, $exit_code);

        return [
            'command' => $command,
            'cwd' => not_empty($cwd) ? $cwd : $old_cwd,
            'exit_code' => $exit_code,
            'output' => implode("\n", $output),
        ];
    } finally {
        if (not_empty($cwd) && $old_cwd) {
            chdir($old_cwd);
        }
    }
}/*}}}*/

function _agent_tool_call_result($tool_call)
{/*{{{*/
    $tool_name = array_get($tool_call, 'function.name');

    otherwise(
        not_empty($tool_name),
        '工具调用缺少工具名称'
    );

    $tool = _agent_tool_pickup($tool_name);

    $arguments = _agent_tool_call_arguments($tool_call);
    $user_name = _agent_tool_user_name($tool_call, $arguments);

    try {
        $result = call_user_func($tool['closure'], $arguments, $tool_call, $user_name);

        return [
            'ok' => true,
            'tool_name' => $tool_name,
            'result' => $result,
        ];
    } catch (throwable $exception) {
        log_exception($exception);

        return [
            'ok' => false,
            'tool_name' => $tool_name,
            'error' => otherwise_get_error_message($exception),
        ];
    }
}/*}}}*/

function _agent_tool_call($tool_call)
{/*{{{*/
    $tool_call_id = array_get($tool_call, 'id');

    otherwise(
        not_empty($tool_call_id),
        '工具调用缺少调用标识'
    );

    return llm_tool_message(
        $tool_call_id,
        _agent_tool_result_content(_agent_tool_call_result($tool_call))
    );
}/*}}}*/

function _agent_tool_calls(array $tool_calls)
{/*{{{*/
    $messages = [];

    foreach ($tool_calls as $tool_call) {
        $messages[] = _agent_tool_call($tool_call);
    }

    return $messages;
}/*}}}*/

function _agent_message_tool_calls(array $message)
{/*{{{*/
    return _agent_tool_calls(llm_if_need_tool_calls($message));
}/*}}}*/

function _agent_tool_bool($value)
{/*{{{*/
    return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'y', 'on', 'append']);
}/*}}}*/

/**
 * memory
 */

/**
 * 注册闭包调用参数: ($user_name)
 */
function if_get_user_memory(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($messages, $user_name)
 */
function if_extract_user_memory_from_messages(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($memory, $user_name)
 */
function if_organize_user_memory(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($memory, $user_name)
 */
function if_store_user_memory(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

function _user_memory(string $user_name)
{/*{{{*/
    $get_user_memory_closure = if_get_user_memory();

    otherwise(
        $get_user_memory_closure instanceof closure,
        '必须先实现 [if_get_user_memory] 闭包'
    );

    return $get_user_memory_closure($user_name);
}/*}}}*/

function _user_memory_extract(array $messages, string $user_name)
{/*{{{*/
    $extract_user_memory_from_messages_closure = if_extract_user_memory_from_messages();

    otherwise(
        $extract_user_memory_from_messages_closure instanceof closure,
        '必须先实现 [if_extract_user_memory_from_messages] 闭包'
    );

    return $extract_user_memory_from_messages_closure($messages, $user_name);
}/*}}}*/

function _user_memory_organize($memory, string $user_name)
{/*{{{*/
    $organize_user_memory_closure = if_organize_user_memory();

    otherwise(
        $organize_user_memory_closure instanceof closure,
        '必须先实现 [if_organize_user_memory] 闭包'
    );

    return $organize_user_memory_closure($memory, $user_name);
}/*}}}*/

function _user_memory_store($memory, string $user_name)
{/*{{{*/
    $store_user_memory_closure = if_store_user_memory();

    otherwise(
        $store_user_memory_closure instanceof closure,
        '必须先实现 [if_store_user_memory] 闭包'
    );

    return $store_user_memory_closure($memory, $user_name);
}/*}}}*/

function user_memory(string $user_name)
{/*{{{*/
    return _user_memory($user_name);
}/*}}}*/

function user_memory_extract(array $messages, string $user_name)
{/*{{{*/
    return _user_memory_extract($messages, $user_name);
}/*}}}*/

function user_memory_organize($memory, string $user_name)
{/*{{{*/
    return _user_memory_organize($memory, $user_name);
}/*}}}*/

function user_memory_store($memory, string $user_name)
{/*{{{*/
    return _user_memory_store($memory, $user_name);
}/*}}}*/

/**
 * skill
 */

/**
 * installed skill
 */

/**
 * 注册闭包调用参数: ($user_name)
 */
function if_get_installed_user_skills(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($user_name)
 */
function if_get_installed_user_skill_simple_infos(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($skill, $user_name)
 */
function if_register_installed_user_skill(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

function _installed_user_skills(string $user_name)
{/*{{{*/
    $get_installed_user_skills_closure = if_get_installed_user_skills();

    otherwise(
        $get_installed_user_skills_closure instanceof closure,
        '必须先实现 [if_get_installed_user_skills] 闭包'
    );

    return $get_installed_user_skills_closure($user_name);
}/*}}}*/

function _installed_user_skill_simple_infos(string $user_name)
{/*{{{*/
    $get_installed_user_skill_simple_infos_closure = if_get_installed_user_skill_simple_infos();

    otherwise(
        $get_installed_user_skill_simple_infos_closure instanceof closure,
        '必须先实现 [if_get_installed_user_skill_simple_infos] 闭包'
    );

    return $get_installed_user_skill_simple_infos_closure($user_name);
}/*}}}*/

function _installed_user_skill_register($skill, string $user_name)
{/*{{{*/
    $register_installed_user_skill_closure = if_register_installed_user_skill();

    otherwise(
        $register_installed_user_skill_closure instanceof closure,
        '必须先实现 [if_register_installed_user_skill] 闭包'
    );

    return $register_installed_user_skill_closure($skill, $user_name);
}/*}}}*/

function installed_user_skills(string $user_name)
{/*{{{*/
    return _installed_user_skills($user_name);
}/*}}}*/

function installed_user_skill_simple_infos(string $user_name)
{/*{{{*/
    return _installed_user_skill_simple_infos($user_name);
}/*}}}*/

function installed_user_skill_register($skill, string $user_name)
{/*{{{*/
    return _installed_user_skill_register($skill, $user_name);
}/*}}}*/

/**
 * refined skill
 */

/**
 * 注册闭包调用参数: ($user_name)
 */
function if_get_refined_user_skills(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($user_name)
 */
function if_get_refined_user_skill_simple_infos(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($skill, $user_name)
 */
function if_register_refined_user_skill(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($messages, $user_name)
 */
function if_extract_refined_user_skill(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($skill, $user_name)
 */
function if_store_refined_user_skill(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

function _refined_user_skills(string $user_name)
{/*{{{*/
    $get_refined_user_skills_closure = if_get_refined_user_skills();

    otherwise(
        $get_refined_user_skills_closure instanceof closure,
        '必须先实现 [if_get_refined_user_skills] 闭包'
    );

    return $get_refined_user_skills_closure($user_name);
}/*}}}*/

function _refined_user_skill_simple_infos(string $user_name)
{/*{{{*/
    $get_refined_user_skill_simple_infos_closure = if_get_refined_user_skill_simple_infos();

    otherwise(
        $get_refined_user_skill_simple_infos_closure instanceof closure,
        '必须先实现 [if_get_refined_user_skill_simple_infos] 闭包'
    );

    return $get_refined_user_skill_simple_infos_closure($user_name);
}/*}}}*/

function _refined_user_skill_register($skill, string $user_name)
{/*{{{*/
    $register_refined_user_skill_closure = if_register_refined_user_skill();

    otherwise(
        $register_refined_user_skill_closure instanceof closure,
        '必须先实现 [if_register_refined_user_skill] 闭包'
    );

    return $register_refined_user_skill_closure($skill, $user_name);
}/*}}}*/

function _refined_user_skill_extract(array $messages, string $user_name)
{/*{{{*/
    $extract_refined_user_skill_closure = if_extract_refined_user_skill();

    otherwise(
        $extract_refined_user_skill_closure instanceof closure,
        '必须先实现 [if_extract_refined_user_skill] 闭包'
    );

    return $extract_refined_user_skill_closure($messages, $user_name);
}/*}}}*/

function _refined_user_skill_store($skill, string $user_name)
{/*{{{*/
    $store_refined_user_skill_closure = if_store_refined_user_skill();

    otherwise(
        $store_refined_user_skill_closure instanceof closure,
        '必须先实现 [if_store_refined_user_skill] 闭包'
    );

    return $store_refined_user_skill_closure($skill, $user_name);
}/*}}}*/

function refined_user_skills(string $user_name)
{/*{{{*/
    return _refined_user_skills($user_name);
}/*}}}*/

function refined_user_skill_simple_infos(string $user_name)
{/*{{{*/
    return _refined_user_skill_simple_infos($user_name);
}/*}}}*/

function refined_user_skill_register($skill, string $user_name)
{/*{{{*/
    return _refined_user_skill_register($skill, $user_name);
}/*}}}*/

function refined_user_skill_extract(array $messages, string $user_name)
{/*{{{*/
    return _refined_user_skill_extract($messages, $user_name);
}/*}}}*/

function refined_user_skill_store($skill, string $user_name)
{/*{{{*/
    return _refined_user_skill_store($skill, $user_name);
}/*}}}*/

/**
 * agent
 */

/**
 * 注册闭包调用参数: ($user_name)
 */
function if_get_agent_definition(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数:
 * ([
 *     'user_name' => ...,
 *     'agent_definition' => ...,
 *     'installed_skill_definitions' => ...,
 *     'refined_skill_definitions' => ...,
 *     'history_session_messages' => ...,
 * ])
 */
function if_pick_agent_skills(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数:
 * ($agent_definition, $agent_frame_definition, $user_name, $memory, $registered_tool_simple_infos, $installed_skill_simple_infos, $refined_skill_simple_infos, $active_skill_infos)
 */
function if_build_agent_system_message(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数:
 * ([
 *     'llm_messages' => ...,
 *     'registered_tool_infos' => ...,
 * ])
 */
function if_agent_context_near_limit(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

/**
 * 注册闭包调用参数: ($llm_user_message)
 */
function if_pick_agent_tools(closure $closure = null): ?closure
{/*{{{*/
    static $container = null;

    if (not_null($closure)) {
        $container = $closure;
    }

    return $container;
}/*}}}*/

function _agent_definition(string $user_name)
{/*{{{*/
    $get_agent_definition_closure = if_get_agent_definition();

    otherwise(
        $get_agent_definition_closure instanceof closure,
        '必须先实现 [if_get_agent_definition] 闭包'
    );

    return $get_agent_definition_closure($user_name);
}/*}}}*/

function _agent_user_name($llm_user_message)
{/*{{{*/
    if (is_array($llm_user_message)) {
        return $llm_user_message['name'] ?? null;
    }

    return null;
}/*}}}*/

function agent_push($llm_user_message)
{/*{{{*/
    $pick_agent_skills_closure = if_pick_agent_skills();
    otherwise(
        $pick_agent_skills_closure instanceof closure,
        '必须先实现 [if_pick_agent_skills] 闭包'
    );

    $build_agent_system_message_closure = if_build_agent_system_message();
    otherwise(
        $build_agent_system_message_closure instanceof closure,
        '必须先实现 [if_build_agent_system_message] 闭包'
    );

    $agent_context_near_limit_closure = if_agent_context_near_limit();
    otherwise(
        $agent_context_near_limit_closure instanceof closure,
        '必须先实现 [if_agent_context_near_limit] 闭包'
    );

    $pick_agent_tools_closure = if_pick_agent_tools();
    otherwise(
        $pick_agent_tools_closure instanceof closure,
        '必须先实现 [if_pick_agent_tools] 闭包'
    );

    $user_name = _agent_user_name($llm_user_message);

    otherwise(
        not_empty($user_name),
        'agent_push 缺少 user_name'
    );

    _agent_staged_response_messages([]);

    $agent_definition = _agent_definition($user_name);

    $agent_frame_definition = "
        你是运行在 agent 框架中的 assistant。
        当你返回给用户可见的回复时，assistant message 的 content 必须是一个合法 JSON 字符串。
        这个 JSON 至少必须包含两个字段: content, task_state。
        content 字段用于放给用户看的文本内容。
        task_state 字段只能是 continue、finished、need_user_input 三个值之一。
        如果当前轮需要调用 tool，请优先按原生 tool_calls 协议返回，不要把 tool 调用信息写入 content JSON。
        如果当前轮没有 tool_calls，则 content 必须严格满足上述 JSON 结构要求。";

    $memory = _user_memory($user_name);
    $installed_skill_definitions = _installed_user_skills($user_name);
    $installed_skill_simple_infos = _installed_user_skill_simple_infos($user_name);
    $refined_skill_definitions = _refined_user_skills($user_name);
    $refined_skill_simple_infos = _refined_user_skill_simple_infos($user_name);
    $history_session_messages = _user_session_append_message($user_name, $llm_user_message);

    for (;;) {
        try {
            $active_skill_infos = $pick_agent_skills_closure([
                'user_name' => $user_name,
                'agent_definition' => $agent_definition,
                'installed_skill_definitions' => $installed_skill_definitions,
                'refined_skill_definitions' => $refined_skill_definitions,
                'history_session_messages' => $history_session_messages,
            ]);

            otherwise(
                is_array($active_skill_infos),
                '选择使用 skill 失败'
            );

            $registered_tool_names = $pick_agent_tools_closure($llm_user_message);

            otherwise(
                is_array($registered_tool_names),
                '选择注册 tool 失败'
            );

            $registered_tool_infos = _agent_llm_tool_infos($registered_tool_names);
            $registered_tool_simple_infos = _agent_tool_simple_infos($registered_tool_names);

            $system_message = $build_agent_system_message_closure(
                $agent_definition,
                $agent_frame_definition,
                $user_name,
                $memory,
                $registered_tool_simple_infos,
                $installed_skill_simple_infos,
                $refined_skill_simple_infos,
                $active_skill_infos
            );

            otherwise(
                is_array($system_message),
                '构建 system message 失败'
            );

            $llm_messages = array_merge([$system_message], $history_session_messages);

            $context_near_limit = $agent_context_near_limit_closure([
                'llm_messages' => $llm_messages,
                'registered_tool_infos' => $registered_tool_infos,
            ]);

            if ($context_near_limit) {
                $history_session_messages = user_session_compress($user_name);
                continue;
            }

            $response = llm_chat($llm_messages, $registered_tool_infos);
            $assistant_message = llm_pick_response_message($response);

            $history_session_messages = _user_session_append_message($user_name, $assistant_message);

            $tool_calls = llm_if_need_tool_calls($assistant_message);
            if (not_empty($tool_calls)) {
                $tool_result_messages = _agent_message_tool_calls($assistant_message);

                foreach ($tool_result_messages as $tool_result_message) {
                    $history_session_messages = _user_session_append_message($user_name, $tool_result_message);
                }

                continue;
            }

            if (not_empty($assistant_message['content'] ?? null)) {
                _agent_stage_response_message($assistant_message);
            }

            $task_state = _agent_message_task_state($assistant_message);

            if (in_array($task_state, ['finished', 'need_user_input'], true)) {
                $memory = _user_memory_extract($history_session_messages, $user_name);
                $memory = _user_memory_organize($memory, $user_name);
                _user_memory_store($memory, $user_name);

                $refined_skill_definition = _refined_user_skill_extract($history_session_messages, $user_name);
                if (not_empty($refined_skill_definition)) {
                    _refined_user_skill_store($refined_skill_definition, $user_name);
                }

                return _agent_pick_staged_response_messages();
            }
        } catch (exception $exception) {
            log_exception($exception);

            throw $exception;
        }
    }
}/*}}}*/
