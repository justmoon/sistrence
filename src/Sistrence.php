<?php
namespace Sistrence;

use Sistrence\Connector\Mysqli\MysqliConnection;
use Sistrence\Connector\Mongo\MongoConnection;
use Sistrence\Sharding\SingleSharder;

/**
 * Sistrence PHP5 database abstraction layer
 * Copyright (C) 2009 Stefan Thomas aka justmoon
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 */

Sistrence::setSharderSingle();

class Sistrence
{
	// Whether to display debug information (lots of it)
	const DEBUG = 0;

	// Version
	const VERSION = '4.0';

	// Field types
	const FT_BOOL    = 10;
	const FT_INT     = 11;
	const FT_FLOAT   = 12;
	const FT_BLOB    = 20;
	const FT_CHAR    = 30;
	const FT_VARCHAR = 31;
	const FT_TEXT    = 32;
	const FT_AUTOKEY = 50;

	// List of open database links
	static private $links = array();

	// Selects a database link based on the requested table
	static private $sharder;

	/**
	 * Connects to a MySQL-compatible database.
	 */
	static public function connectMysqli($user = 'root', $password = '', $host = 'localhost', $database = 'mysql')
	{
		$linkid = count(self::$links);
		self::$links[$linkid] = new MysqliConnection($user, $password, $host, $database);
		return $linkid;
	}

	/**
	 * Connects to a MongoDB-compatible database.
	 */
	static public function connectMongo($database = 'default', $server = 'mongodb://localhost:27017', $options = array())
	{
		$linkid = count(self::$links);
		self::$links[$linkid] = new MongoConnection($database, $server, $options);
		return $linkid;
	}

	static public function setSharderSingle()
	{
		self::$sharder = new SingleSharder();
	}

	static public function op($table, $linkid = null)
	{
		if ($linkid === null) $linkid = self::$sharder->getLink($table);

		if (!isset(self::$links[$linkid])) {
			self::error('Can\'t prepare database op - invalid link id "'.$linkid.'"', array('linklist' => self::$links));
			return false;
		}

		return self::$links[$linkid]->op($table);
	}

	static public function getLink($linkid)
	{
		if (!isset(self::$links[$linkid])) {
			self::error('Can\'t find database link - invalid link id "'.$linkid.'"', array('linklist' => self::$links));
			return false;
		}

		return self::$links[$linkid];
	}

	static public function error($error, $debug)
	{
		$de = ini_get('display_errors');

		if ($de === 'On' || $de == 1) {
			echo '<br />'."\r\n";
			echo '<b>Database Error</b>:  '.$error."<br/>\r\n";
			if (isset($debug['db_error'])) echo '<small><i><b>'.$debug['db_error']."</b></i></small><br/>\r\n";
			if (isset($debug['query'])) echo '<pre>'.wordwrap($debug['query']).'</pre>';
			if (isset($debug['value'])){echo '<pre>';var_dump($debug['value']);echo '</pre>';}
			echo '<pre>';debug_print_backtrace();echo '</pre>';
			echo '<br />';
		}
	}

	static public function dumpLinklist()
	{
		print("<pre>\n");
		print_r(self::$links);
		print("</pre>\n");
	}

	static public function debugEcho($text)
	{
		if (self::DEBUG) {
			// non-framework version
			echo "<br />\n".$text."<br />\n";
		}
	}
}
