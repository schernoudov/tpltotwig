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

    $result = "";

    $currentPosition = 0;

    foreach ($matches[0] as $match) {

		$codeBlockStart = $match[1];

		$codeBlockEnd = strpos($content, $options['close_tag'], $codeBlockStart) + strlen($options['close_tag']);

		if ($match[1] - $currentPosition !== 0) {
			$result .= substr($content, $currentPosition, $match[1] - $currentPosition);
		}

		$codeBlock = convertCode(trim(substr($content, $codeBlockStart + strlen($options['open_tag']), $codeBlockEnd - $codeBlockStart - strlen($options['close_tag']) - strlen($options['open_tag']))), $context, $options);

        $result = substr_replace($result, $codeBlock, $codeBlockStart, $codeBlockEnd);

		$currentPosition = $codeBlockEnd;
    }

    if ($currentPosition !== strlen($content)) {
    	$result .= substr($content, $currentPosition, strlen($content) - $currentPosition);
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

    $converted = $metaInformation == NULL ? $code : buildCode($metaInformation, $context);

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
            break;
		case 'foreach':
			$code = buildForeach($meta);
			break;
		case 'endforeach':
			$code = '{% endfor %}';
			break;
		case 'endif':
			$code = '{% endif %}';
			break;
    }
    return $code;
}

function buildEcho($meta) {
    return '{{ '.processExpression($meta->argument). ' }}';
}

function buildForeach($meta) {
	if (isset($meta->key)) {
		return '{% for '.processExpression($meta->key).', '.processExpression($meta->value).' in '.processExpression($meta->array_expression).' %}';
	} else {
		return '{% for '.processExpression($meta->value).' in '.processExpression($meta->array_expression).' %}';
	}
}

function processExpression($argument) {
	return str_replace('$', '', $argument);
}

/**
 * @param Context $context
 * @param string $code
 * @param array $options
 * @return null|object
 */
function parseCode ($context, $code, $options) {

    $meta = NULL;
    $token = "";
	for ($index = 0, $size = strlen($code) ; $index < $size ; $index++) {
		if (empty($code[$index])) {
			$token = "";
			continue;
		}
		$token .= $code[$index];
		switch ($token) {
			case 'echo':
				$meta = parseEcho($code, $index + 1, $context);
				$token = "";
				break;
			case 'foreach':
				$meta = parseForeach($code, $index + 1, $context);
				$context->pushBlock('foreach');
				$token = "";
				break;
			case 'if':
				$meta = parseIf($code, $index + 1, $context);
				$context->pushBlock('if');
				$token = "";
				break;
			case '}':
				$meta = (object) [
					'operation' => 	$context->getCurrentBlock() == 'foreach' ? 'endforeach' :
						($context->getCurrentBlock() == 'if' ? 'endif' : '')
				];
				$context->popBlock();
				$token = "";
				break;
		}
	}

	return $meta;
}

function parseEcho ($code, $index, $context) {

    $operation = 'echo';

    $meta = (object) [
        'operation' => $operation
    ];

    $operandStartIndex = strpos($code, $operation, $index) + strlen($operation) + 1;
    $operandEndIndex = strpos($code, ';', $operandStartIndex);

    $meta->argument = parseArgument(trim(substr($code, $operandStartIndex, $operandEndIndex - $operandStartIndex)));

    return $meta;
}

function parseArgument ($argument) {
	return $argument;
}

function parseForeach ($code, $index, $context) {

    $operation = 'foreach';

    $meta = (object) [
        'operation' => $operation
    ];

    $expressionStartIndex = strpos ($code, $operation) + strlen($operation);
    $expressionStartIndex = strpos ($code, '(', $expressionStartIndex) + 1;
    $expressionEndIndex = strrpos ($code, ')', $expressionStartIndex);

    $condition = explode(' as ', trim(substr($code, $expressionStartIndex, $expressionEndIndex - $expressionStartIndex)));
    $meta->array_expression = $condition[0];
	if (strpos($condition[1], '=>')) {
		$keyValue = explode('=>', $condition[1]);
		$meta->key = trim($keyValue[0]);
		$meta->value = trim($keyValue[1]);
	} else {
		$meta->value = trim($condition[1]);
	}

    return $meta;
}

function parseIf ($code) {
	$operation = 'if';

	$meta = (object) [
		'operation' => $operation
	];
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