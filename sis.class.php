<?php

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

Sis::setSharderSingle();

class Sis
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
		require_once dirname(__FILE__).'/connector/mysqli.php';
		self::$links[$linkid] = new SisConnectionMysqli($user, $password, $host, $database);
		return $linkid;
	}

	/**
	 * Connects to a MongoDB-compatible database.
	 */
	static public function connectMongo($database = 'default', $server = 'mongodb://localhost:27017', $options = array())
	{
		$linkid = count(self::$links);
		require_once dirname(__FILE__).'/connector/mongodb.php';
		self::$links[$linkid] = new SisConnectionMongo($database, $server, $options);
		return $linkid;
	}

	static public function setSharderSingle()
	{
		self::$sharder = new SisSharderSingle();
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

class SisUpdateRuleset
{

}

abstract class SisField
{
	// Associated operation
	protected $op;

	// Name of the field
	protected $name = null;

	public function __construct(SisOperation $op, $fieldName)
	{
		$this->op = $op;
		$this->name = $fieldName;
	}

	public function getName()
	{
		return $this->name;
	}

	public function getFullName()
	{
		return '`'.$this->op->getTable() . '`.`' . $this->name . '`';
	}

	/*
	// data type
	public $type = null;

	// the (maximum) size
	public $size = 8;

	// default value for the column
	public $default = '';
	*/
}

/**
 * This class implements certain features every SisOperation object needs
 */
abstract class SisOperation
{
	// The SisConnection we'll use
	protected $c;

	// Table to operate on
	protected $table;

	// Options for operation
	protected $options = array();

	public function __construct($table, $c)
	{
		$this->c = $c;
		$this->table = $table;
	}

	public function __destruct()
	{
		unset($this->c);
		unset($this->options);
	}

	public function getTable()
	{
		return $this->table;
	}

	public function prepareValue($value)
	{
		if (is_bool($value)) {
			return $value ? 1 : 0;
		} elseif (is_int($value)) {
			return $value;
		} elseif (is_float($value)) {
			// convert the decimal separator back to english notation if changed by locale
			$localeconv = localeconv();
			return str_replace($localeconv['decimal_point'], '.', $value);
		} elseif (is_string($value)) {
			return "'".$this->escapeString($value)."'";
		} elseif ($value === null) {
			return 'NULL';
		} elseif ($value instanceof SisField) {
			return $value->getFullName();
		} else {
			Sis::error('Invalid data type!', array('value' => $value));
			return false;
		}
	}

	public function escapeString($string)
	{
		return $this->c->escapeString($string);
	}
}

abstract class SisJoin
{
	protected $op;
	protected $table;

	protected $on = array();

	public function __construct(SisOperation $op, $table)
	{
		$this->op = $op;
		$this->table = $table;
	}
}

abstract class SisSharder
{
	abstract public function getLink($table);
}

class SisSharderSingle extends SisSharder
{
	public function getLink($table)
	{
		return 0;
	}
}

class SisUtil
{
	/**
	 * Generates a pseudo-random UUID compliant with RFC 4122.
	 *
	 * @author mimec
	 */
	static public function uuid()
	{
		return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
	}

	/**
	 * Generates a variable length random case-independent identifier.
	 */
	static public function getUnique($length = 12)
	{
		$result = ''; $len = $length;
		do {
			$rs = base_convert(mt_rand(0, 0x39aa3ff), 10, 36);
			$result .= $rs;
		} while (($len -= 5) > 0);

		return strtoupper(substr($result, 0, $length));
	}

	/**
	 * Generates a variable length random "base90" identifier.
	 */
	static public function getUnique90($length = 12)
	{
		$result = ''; $length = (int)$length;
		while ($length-- > 0) {
			$chr = 0x24 + mt_rand(0, 0x5a);
			if ($chr == 0x27) $chr = 0x21; // avoid single quote
			if ($chr == 0x5c) $chr = 0x23; // avoid backslash
			$result .= chr($chr);
		}

		return $result;
	}

	/**
	 * Returns a 16-byte packed IPv6-ready representation of the IP.
	 *
	 * By default this will return the user's ip (REMOTE_ADDR).
	 *
	 * Tested with lighttpd and Apache2.
	 *
	 * You can use a 128-bit binary datatype to store this in your database. For
	 * MySQL for example you can use BINARY(16).
	 *
	 * To turn this into a readable string, use inet_ntop().
	 */
	static public function getPackedIp($ip = null)
	{
		// Create packed IPv6 IP
		$packedIp = inet_pton($_SERVER['REMOTE_ADDR']);
		$isIpv4 = strlen($packedIp) == 4;
		if ($isIpv4) $packedIp = str_repeat(chr(0), 10).str_repeat(chr(255), 2).$packedIp;

		return $packedIp;
	}
}
?>
