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

    foreach ($matches[0] as $match) {
        $codeBlockStart = $match[1];
        $codeBlockEnd = strpos($content, $options['close_tag'], $codeBlockStart) + strlen($options['close_tag']);
        $codeBlock = convertCode(substr($content, $codeBlockStart, $codeBlockEnd - $codeBlockStart), $context, $options);
    }

    return $content;
}

/**
 * @param string $code
 * @param Context $context
 * @param array $options
 */
function convertCode ($code, $context, $options) {

    $tokens = token_get_all($code);

    $converted = "";

    fillContext($context, $code, $options);
}

/**
 * @param Context $context
 * @param string $code
 * @param array $options
 */
function fillContext ($context, $code, $options) {

	$tokens = token_get_all($code);

	for ($index = 0, $size = count($tokens) ; $index < $size ; $index++) {
		$token = $tokens[$index];
		if (is_array($token)) {
			switch ($token[0]) {
                case T_ECHO:
                    fillContextForEcho($context, $code);
                    break;
				case T_FOREACH:
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
}

function fillContextForEcho($context, $code) {

    $operation = 'echo';
    $context->operation = $operation;
    $operandStartIndex = strpos($code, $operation) + strlen($operation) + 1;
    $operandEndIndex = strpos($code, ';', $operandStartIndex);
    $context->expression = str_replace('$', '', trim(substr($code, $operandStartIndex, $operandEndIndex - $operandStartIndex)));
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