<?php
$dir = dirname(__FILE__);
require_once($dir.'/sis.class.php');
require_once($dir.'/obj.class.php');
require_once($dir.'/set.class.php');

DB::connectMysqli($qconfig['db_user'], $qconfig['db_password'], $qconfig['db_host'], $qconfig['db_name']);
?>
