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
    switch ($meta->key) {
        case 'echo':
            $code = buildEcho($meta);
            break;
		case 'foreach':
			$code = buildForeach($meta);
			break;
		case 'endforeach':
			$code = '{% endfor %}';
			break;
        case 'if':
            $code = buildIf($meta);
            break;
		case 'endif':
			$code = '{% endif %}';
			break;
    }
    return $code;
}

function buildEcho($meta) {
    return '{{ '.processExpression($meta->expression->args[0]). ' }}';
}

function buildForeach($meta) {
	if (isset($meta->condition->key)) {
		return '{% for '.processExpression($meta->condition->key).', '.processExpression($meta->condition->value).' in '.processExpression($meta->condition->array_expression).' %}';
	} else {
		return '{% for '.processExpression($meta->condition->value).' in '.processExpression($meta->condition->array_expression).' %}';
	}
}


function buildIf($meta) {
    return '{% if '.$meta->condition->expression->args[0].' %}';
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
				$meta = parseEcho($code, $context);
				$token = "";
				break;
			case 'foreach':
				$meta = parseForeach($code, $context);
				$token = "";
				break;
			case 'if':
				$meta = parseIf($code, $context);
				$token = "";
				break;
            case '{':
                $context->getCurrentBlock()->multiline = TRUE;
                break;
			case '}':
                if ($context->getCurrentBlock() != NULL &&  $context->getCurrentBlock()->multiline) {
                    $meta = (object) [
                        'key' =>    ($context->getCurrentBlock()->key == 'foreach') ? 'endforeach' :
                                    ($context->getCurrentBlock()->key == 'if' ? 'endif' : '')
                    ];
                }
                $context->popBlock();
                $token = "";
				break;
		}
	}

	return $meta;
}

function parseEcho ($code, $context) {

    $meta = (object) [
        'key' => 'echo'
    ];

    $matches = [];

    preg_match('/\s*?\(?.*?\)??\s??(?=;)/', $code, $matches, 0, strpos($code, $meta->key) + strlen($meta->key));

    $meta->expression = parseExpression(trim($matches[0], '() '));

    return $meta;
}

function parseArgument ($argument) {
	return $argument;
}

/**
 * @param string $code
 * @param Context $context
 * @return object
 */
function parseForeach ($code, $context) {

    $operation = 'foreach';

    $meta = (object) [
        'key' => $operation
    ];

    $context->pushBlock((object) ['key' => 'foreach']);

    if (preg_match('/foreach\s*\(.*\)\s*\{.*/', $code) === 1) {
        $context->getCurrentBlock()->multiline = TRUE;
    } else {
        $context->getCurrentBlock()->multiline = FALSE;
    }

    $expressionStartIndex = strpos ($code, $operation) + strlen($operation);
    $expressionStartIndex = strpos ($code, '(', $expressionStartIndex) + 1;
    $expressionEndIndex = strrpos ($code, ')', $expressionStartIndex);

    $condition = explode(' as ', trim(substr($code, $expressionStartIndex, $expressionEndIndex - $expressionStartIndex)));
    $meta->condition = (object) [
        'array_expression' => $condition[0]
    ];
	if (strpos($condition[1], '=>')) {
		$keyValue = explode('=>', $condition[1]);
        $meta->condition->key = trim($keyValue[0]);
        $meta->condition->value = trim($keyValue[1]);
	} else {
        $meta->condition->value = trim($condition[1]);
	}

    return $meta;
}

/**
 * @param string $code
 * @param Context $context
 * @return object
 */
function parseIf ($code, $context) {

	$operation = 'if';

    $context->pushBlock((object) ['key' => 'if', 'multiline' => FALSE]);

	$meta = (object) [
		'key' => $operation
	];

	if (preg_match('/if\s*\(.*\)\s*\{.*/', $code) === 1) {
        $context->getCurrentBlock()->multiline = TRUE;
    } else {
        $context->getCurrentBlock()->multiline = FALSE;
    }

    $matches = [];

    preg_match('/\(.*\)/', $code, $matches);

    $meta->condition = parseCondition($matches[0]);

    return $meta;
}
/**
 * @param string $condition
 * @return object
 */
function parseCondition($condition) {
    return (object) [
        'expression' => parseExpression(trim($condition, '()\t\n\r\0\x0B'))
    ];
}

/**
 * @param string $expression
 * @return object
 */
function parseExpression ($expression) {
    return (object) [
        'args' => [$expression]
    ];
}


class Context {

	private $_blocks = [];

	function _construct () {}

	function getCurrentBlock() {
		return count($this->_blocks) !== 0 ? $this->_blocks[count($this->_blocks) - 1] : NULL;
	}

	function pushBlock($block) {
		return array_push($this->_blocks, $block);
	}

	function popBlock() {
		return array_pop($this->_blocks);
	}
}