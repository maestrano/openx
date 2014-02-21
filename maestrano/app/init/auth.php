<?php
//-----------------------------------------------
// Define root folder and load base
//-----------------------------------------------
if (!defined('MAESTRANO_ROOT')) {
  define("MAESTRANO_ROOT", realpath(dirname(__FILE__) . '/../../'));
}
require MAESTRANO_ROOT . '/app/init/base.php';

//-----------------------------------------------
// Require your app specific files here
//-----------------------------------------------
define('APP_DIR', realpath(MAESTRANO_ROOT . '/../'));
chdir(APP_DIR);

error_log("======== Just before loading init");
//require_once APP_DIR . '/init.php';
require_once APP_DIR . '/lib/pear/PEAR.php';
define('MAX_PATH', APP_DIR);
define('OX_PATH', APP_DIR);
//require_once APP_DIR . '/pre-check.php';
require_once APP_DIR . '/init-parse.php';
// require_once APP_DIR . '/variables.php';
// require_once APP_DIR . '/constants.php';
function OX_getHostName() { return 'localhost'; }


$conf = parseIniFile(APP_DIR . '/var');

//require_once APP_DIR . '/www/admin/lib-sessions.inc.php';
//require_once APP_DIR . '/lib/OA/Auth.php';
//var_dump($conf['database']);

//-----------------------------------------------
// Perform your custom preparation code
//-----------------------------------------------
// If you define the $opts variable then it will
// automatically be passed to the MnoSsoUser object
// for construction
// e.g:
$opts = array();
$opts['db_connection'] = new mysqli($conf['database']['host'], $conf['database']['username'], $conf['database']['password'], $conf['database']['name']);

