<?php
/**
 * Created by PhpStorm.
 * User: scher
 * Date: 05.12.2017
 * Time: 16:03
 */

$options = getopt('', ['dest:']);

if (count($argv) - count($options) < 2) {
	printf('Usage:');
	return;
}

$options['src'] = $argv[count($argv) - 1];

$options['open_tag'] = '<?php';
$options['close_tag'] = '?>';

run($options);

/**
 * Opens files and runs conversion.
 * @param array $options
 */
function run ($options) {
    if (file_exists($options['src'])) {
        if (($sfh = fopen($options['src'], "r")) && ($dfh = fopen($options['dest'], "w"))) {
            try {
                $content = fread($sfh, filesize($options['src']));
                $converted = convert($content, $options);
                fwrite($dfh, $converted);
            } catch (Exception $e) {
                echo $e->getMessage();
            }
            fclose($sfh);
            fclose($dfh);
        }
    }
}

/**
 * @param string $content
 * @param array $options
 * @return string
 */
function convert ($content, $options) {

    preg_match_all('/'.preg_quote($options['open_tag']).'/', $content, $matches, PREG_OFFSET_CAPTURE);

    $context = new Context();

    $result = substr($content, 0, strlen($content));

    foreach ($matches[0] as $match) {
        $codeBlockStart = $match[1];
        $codeBlockEnd = strpos($content, $options['close_tag'], $codeBlockStart) + strlen($options['close_tag']);
        $codeBlock = convertCode(substr($content, $codeBlockStart, $codeBlockEnd - $codeBlockStart), $context, $options);
        $result = substr_replace($result, $codeBlock, $codeBlockStart, $codeBlockEnd);
    }

    return $result;
}

/**
 * @param string $code
 * @param Context $context
 * @param array $options
 * @return string
 */
function convertCode ($code, $context, $options) {

    $metaInformation = parseCode($context, $code, $options);

    $converted = $metaInformation == NULL ? $code : buildCode($metaInformation);

    return $converted;
}

/**
 * @param $meta
 * @return string
 */
function buildCode ($meta) {
    $code = NULL;
    switch ($meta->operation) {
        case 'echo':
            $code = buildEcho($meta);
    }
    return $code;
}

function buildEcho($meta) {
    return '{{ '.$meta->argument. ' }}';
}

/**
 * @param Context $context
 * @param string $code
 * @param array $options
 * @return null|object
 */
function parseCode ($context, $code, $options) {

	$tokens = token_get_all($code);
    $meta = NULL;
	for ($index = 0, $size = count($tokens) ; $index < $size ; $index++) {
		$token = $tokens[$index];
		if (is_array($token)) {
			switch ($token[0]) {
                case T_ECHO:
                    $meta = parseEcho($code);
                    break;
				case T_FOREACH:
                    $meta = parseForeach($code);
				default:
					echo token_name($token[0]), PHP_EOL;
			}
		} else {
			switch ($token) {
				case '{':
					echo $token, PHP_EOL;
					break;
				case '}':
					echo $token, PHP_EOL;
					break;
				case '(':
					echo $token, PHP_EOL;
					break;
				case ')':
					echo $token, PHP_EOL;
					break;
				case ';':
					echo $token, PHP_EOL;
					break;
			}
		}
	}

	return $meta;
}

function parseEcho ($code) {

    $operation = 'echo';

    $echo = (object) [
        'operation' => $operation
    ];

    $operandStartIndex = strpos($code, $operation) + strlen($operation) + 1;
    $operandEndIndex = strpos($code, ';', $operandStartIndex);

    $echo->argument = trim(substr($code, $operandStartIndex, $operandEndIndex - $operandStartIndex));

    return $echo;
}

function parseForeach ($code) {

    $operation = 'foreach';

    $foreach = (object) [
        'operation' => $operation
    ];

    $expressionStartIndex = strpos($code, $operation) + strlen($operation) + 1;
    $expressionStartIndex = strpos($code, '(', $expressionStartIndex);
    $expressionEndIndex = strpos($code, ')', $expressionStartIndex);

    $condition = explode('as', trim(substr($code, $expressionStartIndex, $expressionEndIndex - $expressionStartIndex)));
    $foreach->array_expression = $condition[0];
    if (strpos($condition, '=>')) {
        $keyValue = explode('=>', $condition[1]);
        $foreach->key = trim($keyValue[0]);
        $foreach->value = trim($keyValue[1]);
    } else {
        $foreach->value = trim($condition[1]);
    }

    return $foreach;
}

function parseExpression ($code) {

}


class Context {

	private $_blocks = [];

	function _construct () {}

	function getCurrentBlock() {
		return current($this->_blocks);
	}

	function pushBlock($block) {
		return array_push($this->_blocks, $block);
	}

	function popBlock() {
		return array_pop($this->_blocks);
	}
}