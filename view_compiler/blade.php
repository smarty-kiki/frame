<?php 

define('BLADE_STREAM_SCHEMA', 'blade');

class blade_stream
{/*{{{*/
    private $string;
    private $position;

    private static $streams = [];

    public static function stream_write($path, $template)
    {/*{{{*/
        return self::$streams[$path] = $template;
    }/*}}}*/

    public static function has_stream($path)
    {/*{{{*/
        return isset(self::$streams[$path]);
    }/*}}}*/

    public static function generate_stream_path($view)
    {/*{{{*/
        return BLADE_STREAM_SCHEMA.'://'.$view;
    }/*}}}*/

    public function stream_open($path, $mode, $options, &$opened_path)
    {/*{{{*/
        $url_info = parse_url($path);
        $path = $url_info["host"].($url_info['path'] ?? '');

        $this->string = self::$streams[$path];
        $this->position = 0;
        return true;
    }/*}}}*/

    public function stream_read($count)
    {/*{{{*/
        $ret = substr($this->string, $this->position, $count);

        $this->position += strlen($ret);

        return $ret;
    }/*}}}*/

    public function stream_eof()
    {/*{{{*/
    }/*}}}*/

    public function stream_stat()
    {/*{{{*/
    }/*}}}*/
}/*}}}*/

stream_wrapper_register(BLADE_STREAM_SCHEMA, "blade_stream");

function _blade_regular($compiler)
{/*{{{*/
    return '/(?<!\w)(\s*)@'.$compiler.'(\s*\(.*\))/';
}/*}}}*/

function _blade_brace_regular($compiler)
{/*{{{*/
    return '/(?<!\w)(\s*)@'.$compiler.'\((\s*.*)\)/';
}/*}}}*/

function _blade_plain_regular($compiler)
{/*{{{*/
    return '/(?<!\w)(\s*)@'.$compiler.'(\s*)/';
}/*}}}*/

function _blade_compile_includes($value)
{/*{{{*/
    $pattern = _blade_brace_regular('include');

    $res = preg_match_all($pattern, $value, $matches);

    if ($res > 0) {

        foreach ($matches[2] as $i => $template_path) {

            $view_compiled_path = blade_view_compiler($template_path);

            $value = str_replace($matches[0][$i], "<?php include '$view_compiled_path'; ?>", $value);
        }
    }

    return $value;
}/*}}}*/

function _blade_compile_comments($value)
{/*{{{*/
    return preg_replace('/{{--((.|\s)*?)--}}/', '<?php /*$1*/ ?>', $value);
}/*}}}*/

function _blade_compile_php_code($value)
{/*{{{*/
    return preg_replace('/@php((.|\s)*?)@endphp/', '<?php $1 ?>', $value);
}/*}}}*/

function _blade_compile_escaped_echos($value)
{/*{{{*/
    $pattern = '/{{{\s*(.+?)\s*}}}/s';

    $callback = function($matches)
    {
        return '<?php echo htmlentities('.preg_replace('/^(?=\$)(.+?)(?:\s+or\s+)(.+?)$/s', 'isset($1) ? $1 : $2', $matches[1]).', ENT_QUOTES, "UTF-8", false); ?>';
    };

    return preg_replace_callback($pattern, $callback, $value);
}/*}}}*/

function _blade_compile_echos($value)
{/*{{{*/
    $pattern = '/(@)?{{\s*(.+?)\s*}}/s';

    $callback = function($matches)
    {
        return $matches[1] ? substr($matches[0], 1) : '<?php echo '.preg_replace('/^(?=\$)(.+?)(?:\s+or\s+)(.+?)$/s', 'isset($1) ? $1 : $2', $matches[2]).'; ?>';
    };

    return preg_replace_callback($pattern, $callback, $value);
}/*}}}*/

function _blade_compile_openings($value)
{/*{{{*/
    $pattern = '/(?(R)\((?:[^\(\)]|(?R))*\)|(?<!\w)(\s*)@(if|elseif|foreach|for|while)(\s*(?R)+))/';

    return preg_replace($pattern, '$1<?php $2$3: ?>', $value);
}/*}}}*/

function _blade_compile_closings($value)
{/*{{{*/
    $pattern = '/(\s*)@(endif|endforeach|endfor|endwhile)(\s*)/';

    return preg_replace($pattern, '$1<?php $2; ?>$3', $value);
}/*}}}*/

function _blade_compile_else($value)
{/*{{{*/
    $pattern = _blade_plain_regular('else');

    return preg_replace($pattern, '$1<?php else: ?>$2', $value);
}/*}}}*/

function _blade_compile_unless($value)
{/*{{{*/
    $pattern = _blade_regular('unless');

    return preg_replace($pattern, '$1<?php if ( !$2): ?>', $value);
}/*}}}*/

function _blade_compile_endunless($value)
{/*{{{*/
    $pattern = _blade_plain_regular('endunless');

    return preg_replace($pattern, '$1<?php endif; ?>$2', $value);
}/*}}}*/

function blade($template)
{/*{{{*/
    static $compilers = array(
        'includes',
        'comments',
        'escaped_echos',
        'echos',
        'openings',
        'closings',
        'else',
        'unless',
        'endunless',
        'php_code',
    );

    foreach ($compilers as $compiler)
    {
        $template = call_user_func("_blade_compile_".$compiler, $template);
    }

    return $template;
}/*}}}*/

function blade_eval($template, $args = [])
{/*{{{*/
    $path = uniqid('template_', true);

    blade_stream::stream_write($path, blade($template));

    extract($args);

    ob_start();

    include(blade_stream::generate_stream_path($path));

    $echo = ob_get_contents();

    ob_end_clean();

    return $echo;
}/*}}}*/

function blade_view_compiler($view)
{/*{{{*/
    $config = config('blade');

    $view_path = view_path();

    $cache_opened = array_get($config, 'compiled_cache', true);
    $view_compiled_path = array_get($config, 'compiled_path', $view_path);

    $view_file = $view_path.$view.'.php';

    if ($cache_opened) {

        $compiled_file = $view_compiled_path.str_replace('/', '-', $view).'.blade.php';

        if (! is_file($compiled_file)) {

            $template = blade(file_get_contents($view_file));

            file_put_contents($compiled_file, $template);
        }

        return $compiled_file;
    } else {

        if (! blade_stream::has_stream($view)) {

            blade_stream::stream_write($view, blade(file_get_contents($view_file)));
        }

        return blade_stream::generate_stream_path($view);
    }
}/*}}}*/

function blade_view_compiler_generate()
{/*{{{*/
    return function ($view) {

        return blade_view_compiler($view);
    };
}/*}}}*/
