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

run($options);

/**
 * Opens files and runs conversion.
 * @param array $options
 */
function run ($options) {
	if (($sfh = fopen($options['src'], "r")) && ($dfh = fopen($options['dest'], "w"))) {
		try {
			convert($sfh, $dfh, $options);
		} catch (Exception $e) {
			echo $e->getMessage();
		}
		fclose($sfh);
		fclose($dfh);
	}
}

function convert ($sfh, $dfh, $options) {

}