<?php
/*
Plugin Name: Hikerquotes
Version: 0.1-alpha
Description: PLUGIN DESCRIPTION HERE
Author: YOUR NAME HERE
Author URI: YOUR SITE HERE
Plugin URI: PLUGIN SITE HERE
Text Domain: hikerquotes
Domain Path: /languages
*/
if (defined('WP_CLI') && WP_CLI) {
	// var_dump(__FILE__);
	include __DIR__ . '/HikerCommand.php';
}
