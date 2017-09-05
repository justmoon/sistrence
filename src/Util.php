<?php
namespace Sistrence;

class Util
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
