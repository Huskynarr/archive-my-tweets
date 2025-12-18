<?php

// Require PHP 8.1+ for modern compatibility
if (version_compare(phpversion(), '8.1.0') < 0) {
	exit('Archive My Tweets requires PHP 8.1.0 or higher. Your server is running PHP '.phpversion().'.');
}

// run
define('ARCHIVE_MY_TWEETS', 1);
require_once dirname(__FILE__) . '/run.php';

