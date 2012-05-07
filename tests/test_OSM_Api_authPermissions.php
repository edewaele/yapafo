#!/usr/bin/env php
<?php
/**
 * Read with the API
 * Write with the API (on the DEV Server)
 * Select the Auth method (look at comment "Authentification")
 * 
 */
$time_start = microtime(true);

require_once (__DIR__ . '/tests_common.php');
_wl('test "' . basename(__FILE__) . '');

require_once (__DIR__ . '/../lib/OSM/Api.php');

// ===================================================
// Authentification
//
$auth_method = 'Basic';
$auth_basic_user = '';
$auth_basic_password = '';
//
$auth_method = 'OAuth';
$auth_oauth_consumer_key = '';
$auth_oauth_consumer_secret = '';
$auth_oauth_token = '';
$auth_oauth_secret = '';
//
$auth_method = '';
// Override above parameters into you own file:
include (__DIR__ . '/../../secrets.php');
//
// ===================================================

//$auth_method = 'Basic';

$osmApi = new OSM_Api(array(
		'url' => OSM_Api::URL_DEV_UK,
		//'log' => array('level' => OSM_ZLog::LEVEL_DEBUG)
	));

if ($auth_method == 'Basic')
{
	_wl(' using Basic auth with user="'.$auth_basic_user.'"');
	$osmApi->setCredentials(
		new OSM_Auth_Basic($auth_basic_user, $auth_basic_password)
	);
	
}
else if ($auth_method == 'OAuth')
{
	_wl(' using OAuth auth with consumerKey="'.$auth_oauth_consumer_key.'"');
	$oauth = new OSM_Auth_OAuth($auth_oauth_consumer_key, $auth_oauth_consumer_secret	);
	$oauth->setAccessToken($auth_oauth_token, $auth_oauth_secret);
	$osmApi->setCredentials($oauth);
	
}

// http://api06.dev.openstreetmap.org/api/0.6/relation/500
// http://api06.dev.openstreetmap.org/api/0.6/way/8184
// http://api06.dev.openstreetmap.org/api/0.6/node/611571
// get a node

$permissions = $osmApi->getAuthPermissions();
echo print_r($permissions,true)."\n";

if( $auth_method == 'Basic')
{
	_assert( ($osmApi->isAllowedToReadPrefs()=== true) );
	_assert( ($osmApi->isAllowedToWritePrefs() === true) );
	_assert( ($osmApi->isAllowedToWriteDiary() === true) );
	_assert( ($osmApi->isAllowedToWriteApi() === true) );
	_assert( ($osmApi->isAllowedToReadGpx() === true) );
	_assert( ($osmApi->isAllowedToWriteGpx() === true) );
	
}
else
{
	_assert( ($osmApi->isAllowedToReadPrefs()=== true) );
	_assert( ($osmApi->isAllowedToWritePrefs() === true) );
	_assert( ($osmApi->isAllowedToWriteDiary() === false) );
	_assert( ($osmApi->isAllowedToWriteApi() === true) );
	_assert( ($osmApi->isAllowedToReadGpx() === true) );
	_assert( ($osmApi->isAllowedToWriteGpx() === false) );
}

$time_end = microtime(true);
_wl('Test well done in ' . number_format($time_end - $time_start, 3) . ' second(s).');
