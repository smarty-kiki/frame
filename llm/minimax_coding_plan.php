<?php

define('MINIMAX_CODING_PLAN_CHAT_URL', 'https://api.minimaxi.com/v1/text/chatcompletion_v2');
define('MINIMAX_CODING_PLAN_REMAINS_URL', 'https://www.minimaxi.com/v1/api/openplatform/coding_plan/remains');
define('MINIMAX_CODING_PLAN_DEFAULT_MAX_COMPLETION_TOKENS', 4096);

/**
 * llm_chat 可接受的 messages 最完整推荐示例:
 *
 * [
 *     [
 *         'role' => 'system',
 *         'name' => 'system',
 *         'content' => '你是一个严谨的 PHP 助手',
 *     ],
 *     [
 *         'role' => 'user',
 *         'name' => 'kiki',
 *         'content' => '帮我查一下上海天气，如果需要就调用工具',
 *     ],
 *     [
 *         'role' => 'assistant',
 *         'content' => '我先查询一下上海天气。',
 *         'tool_calls' => [
 *             [
 *                 'id' => 'call_weather_001',
 *                 'type' => 'function',
 *                 'function' => [
 *                     'name' => 'get_weather',
 *                     'arguments' => '{"city":"上海"}',
 *                 ],
 *             ],
 *         ],
 *     ],
 *     [
 *         'role' => 'tool',
 *         'tool_call_id' => 'call_weather_001',
 *         'content' => '{"city":"上海","weather":"晴","temperature":"24C"}',
 *     ],
 *     [
 *         'role' => 'assistant',
 *         'content' => '上海当前晴，气温 24C。',
 *     ],
 * ]
 *
 * 当前 provider 不校验 messages 子字段，会原样透传给 MiniMax 原生接口。
 * 官方已明确的常用键是 role/content/name/tool_calls；
 * tool_call_id 用于 role=tool 的回传场景，来自官方 Tool Use 文档。
 */
function llm_user_message($content, $user_name = 'frame')
{/* {{{ */
    return [
        'role' => 'user',
        'name' => $user_name,
        'content' => $content,
    ];
}/* }}} */

function llm_pick_response_message($response)
{/*{{{*/
    $message = $response['choices'][0]['message'];

    $res = [
        'role' => $message['role'],
        'content' => $message['content'],
    ];

    if (array_key_exists('tool_calls', $message)) {
        $res['tool_calls'] = $message['tool_calls'];
    }

    return $res;
}/*}}}*/

function llm_system_message($content)
{/*{{{*/
    return [
        'role' => 'system',
        'name' => 'system',
        'content' => $content,
    ];
}/*}}}*/

function llm_tool_message($tool_call_id, $content)
{/*{{{*/
    return [
        'role' => 'tool',
        'tool_call_id' => $tool_call_id,
        'content' => $content,
    ];
}/*}}}*/

function llm_tool_info($name, $description, $parameters = [], $require = [])
{/*{{{*/

    $res = [
        'type' => 'function',
        'function' => [
            'name' => $name,
            'description' => $description,
        ],
    ];

    $properties = [];

    foreach ($parameters as $paramater_name => $paramater_description) {
        $properties[$paramater_name] = [
            'type' => 'string',
            'description' => $paramater_description,
        ];
    }

    if ($properties) {
        $res['function']['parameters'] = [
            'type' => 'object',
            'properties' => $properties,
            'required' => $require,
        ];
    }

    return $res;
}/*}}}*/

function llm_if_need_tool_calls($message)
{/*{{{*/
    return $message['tool_calls'] ?? [];
}/*}}}*/

function llm_chat(array $messages, $tools = [])
{/*{{{*/
    if (empty($messages)) {
        throw new Exception('minimax coding plan messages can not be empty');
    }

    $config = config('minimax_coding_plan');

    $payload = $config['default_option'];
    $payload['messages'] = $messages;
    $payload['tools'] = $tools;

    return _minimax_coding_plan_request(MINIMAX_CODING_PLAN_CHAT_URL, 'POST', $payload);
}/*}}}*/

function llm_remains()
{/*{{{*/
    return _minimax_coding_plan_request(MINIMAX_CODING_PLAN_REMAINS_URL, 'GET');
}/*}}}*/

function _minimax_coding_plan_request($url, $method, array $payload = [])
{/*{{{*/
    $config = config('minimax_coding_plan');

    $api_key = $config['api_key'];

    if (empty($api_key)) {
        throw new Exception('minimax coding plan api_key can not be empty');
    }

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer '.$api_key,
    ];

    $request = [
        'url'     => $url,
        'method'  => $method,
        'timeout' => $config['timeout'],
        'retry'   => $config['retry'],
        'header'  => $headers,
        0         => function ($body, $status) {
            return [
                'status' => $status,
                'body'   => $body,
            ];
        },
    ];

    if ('POST' === $method) {
        $request['data'] = json($payload);
    }

    $raw_response = http($request);

    $response = json_decode($raw_response['body'], true);

    _minimax_coding_plan_assert_response($response, $raw_response['status'], $raw_response['body']);

    return $response;
}/*}}}*/

function _minimax_coding_plan_assert_response($response, $status, $raw_body = '')
{/*{{{*/
    if ($status >= 400) {
        $message = is_array($response) ?
            array_get($response, 'error.message', array_get($response, 'base_resp.status_msg', $raw_body)) :
            $raw_body;

        throw new Exception('minimax coding plan request failed ['.$status.']: '.$message);
    }

    if (! is_array($response)) {
        throw new Exception('minimax coding plan response decode failed: '.$raw_body);
    }

    if (isset($response['error'])) {
        $message = array_get($response, 'error.message', json($response['error']));

        throw new Exception('minimax coding plan request failed: '.$message);
    }

    if (isset($response['base_resp']) && (int) array_get($response, 'base_resp.status_code', 0) !== 0) {
        $message = array_get($response, 'base_resp.status_msg', json($response['base_resp']));

        throw new Exception('minimax coding plan request failed: '.$message);
    }
}/*}}}*/
