<?php  error_reporting(0);
if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$active_group = 'expressionengine';
$active_record = TRUE;
/*
$db['expressionengine']['hostname'] = "localhost";
$db['expressionengine']['username'] = "alshamsi_exp";
$db['expressionengine']['password'] = "welcome*123";
$db['expressionengine']['database'] = "alshamsi_alshamsiexp";
*/

$db['expressionengine']['hostname'] = "localhost";
$db['expressionengine']['username'] = "ashdbuser";
$db['expressionengine']['password'] = "ash@db#";
$db['expressionengine']['database'] = "ashcom_db";
$db['expressionengine']['dbdriver'] = "mysqli";

$db['expressionengine']['dbdriver'] = "mysql";
$db['expressionengine']['pconnect'] = FALSE;
$db['expressionengine']['dbprefix'] = "exp_";
$db['expressionengine']['swap_pre'] = "exp_";
$db['expressionengine']['db_debug'] = TRUE;
$db['expressionengine']['cache_on'] = FALSE;
$db['expressionengine']['autoinit'] = FALSE;
$db['expressionengine']['char_set'] = "utf8";
$db['expressionengine']['dbcollat'] = "utf8_general_ci";
$db['expressionengine']['cachedir'] = "/home/alshamsi/public_html/system/expressionengine/cache/db_cache/";
/* */
/* End of file database.php */
/* Location: ./system/expressionengine/config/database.php */