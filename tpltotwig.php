<?php
/**
 * Created by PhpStorm.
 * User: scher
 * Date: 05.12.2017
 * Time: 16:03
 */

$options = getopt('', ['dest:']);

if (count($options) == 0 && count($argv) == 0) {
	printf('Usage:');
	return;
}

$options['src'] = $argv[count($argv) - 1];