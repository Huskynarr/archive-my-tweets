<?php

// timezone, see: http://php.net/manual/en/timezones.php
date_default_timezone_set(''); // e.g. America/New_York

// X (formerly Twitter) settings
define('TWITTER_USERNAME', ''); // e.g. awhalen (your X username)
define('TWITTER_NAME',     ''); // e.g. Andrew M. Whalen (your display name)

// The URL for your installation of archive-my-tweets
define('BASE_URL', ''); // e.g. http://amwhalen.com/twitter/ (start with http:// and have a slash at the end)

// X API v2 credentials (free tier)
// Register at https://developer.x.com/en/apps
// The free tier uses OAuth 2.0 Bearer Token authentication
define('TWITTER_CONSUMER_KEY',    ''); // API Key
define('TWITTER_CONSUMER_SECRET', ''); // API Secret

// Bearer Token for API v2 (recommended)
// You can generate this in the X Developer Portal or leave empty to auto-generate
define('TWITTER_BEARER_TOKEN', '');

// Legacy OAuth 1.0a Tokens (optional, not required for API v2 free tier)
define('TWITTER_OAUTH_TOKEN',  '');
define('TWITTER_OAUTH_SECRET', '');

// mysql database credentials
define('DB_USERNAME', '');
define('DB_PASSWORD', '');
define('DB_NAME',     '');

// to run a cron job a secret key is required so no one can destroy your API limit by visiting cron.php
// this can be anything you want
define('TWITTER_CRON_SECRET', '');

// extra database stuff. the defaults are probably fine.
define('DB_TABLE_PREFIX', 	'amt2_');
define('DB_HOST', 			'localhost'); // you can add a port number like this: example.com:3306

// end PHP