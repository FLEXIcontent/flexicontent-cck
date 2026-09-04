<?php
/**
 * @package         FLEXIcontent
 * @version         5.0
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2026, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

/**
 * Safe client for server-side access to remote (HTTP / HTTPS) files
 *
 * Every server-side request to a user supplied URL must go through this class:
 *  - only http / https, no credentials inside the URL, only default ports (unless the host is trusted)
 *  - the host must resolve (IPv4 and IPv6) to public addresses only, unless it is the site's own host or a trusted host
 *  - the connection is pinned to the validated address (CURLOPT_RESOLVE), redirects are never followed automatically,
 *    at most MAX_REDIRECTS redirects are followed manually and every redirect target is validated and pinned again
 *  - connect and total timeouts are applied, TLS verification is kept, and without cURL no request is made at all
 *
 * Trusted hosts are configured in the component configuration ('remote_trusted_hosts', one 'host' or 'host:port' per line).
 * When the list is set, downloads of URL files are only proxied for these hosts.
 */
class flexicontent_remote
{
	const MAX_REDIRECTS   = 5;
	const CONNECT_TIMEOUT = 5;
	const TOTAL_TIMEOUT   = 15;
	const STREAM_TIMEOUT  = 600;
	const USER_AGENT      = 'FLEXIcontent remote file client';

	/**
	 * Cached configuration (null = not loaded yet)
	 */
	protected static $trusted_hosts = null;
	protected static $site_host = null;


	/**
	 * Override the trusted hosts list (e.g. for tests), array or a string with one host per line
	 */
	public static function setTrustedHosts($hosts)
	{
		self::$trusted_hosts = self::normalizeHostList($hosts);
	}


	/**
	 * Override the site's own hostname (e.g. for tests)
	 */
	public static function setSiteHost($host)
	{
		self::$site_host = strtolower(trim((string) $host, '.'));
	}


	/**
	 * Get the trusted hosts list from the component configuration
	 *
	 * @return  array
	 */
	public static function getTrustedHosts()
	{
		if (self::$trusted_hosts === null)
		{
			$list = '';

			if (class_exists('\Joomla\CMS\Component\ComponentHelper'))
			{
				try
				{
					$list = (string) \Joomla\CMS\Component\ComponentHelper::getParams('com_flexicontent')->get('remote_trusted_hosts', '');
				}
				catch (\Throwable $e)
				{
					$list = '';
				}
			}

			self::$trusted_hosts = self::normalizeHostList($list);
		}

		return self::$trusted_hosts;
	}


	/**
	 * Get the site's own hostname (exempted from the public address requirement, like in addurl() before)
	 *
	 * @return  string
	 */
	public static function getSiteHost()
	{
		if (self::$site_host === null)
		{
			$host = '';

			if (class_exists('\Joomla\CMS\Uri\Uri'))
			{
				try
				{
					$host = (string) parse_url(\Joomla\CMS\Uri\Uri::root(), PHP_URL_HOST);
				}
				catch (\Throwable $e)
				{
					$host = '';
				}
			}

			self::$site_host = strtolower(trim($host, '.'));
		}

		return self::$site_host;
	}


	/**
	 * Whether a trusted hosts list is configured
	 *
	 * @return  boolean
	 */
	public static function hasTrustedHosts()
	{
		return count(self::getTrustedHosts()) > 0;
	}


	/**
	 * Check if a host is trusted. A 'host' entry matches any port, a 'host:port' entry matches that port only
	 *
	 * @param   string   $host  The hostname (or IP address)
	 * @param   integer  $port  The port
	 *
	 * @return  boolean
	 */
	public static function isTrustedHost($host, $port = null)
	{
		$host = strtolower(trim((string) $host, '.[]'));

		foreach (self::getTrustedHosts() as $entry)
		{
			if ($entry === $host)
			{
				return true;
			}

			if ($port !== null && $entry === $host . ':' . (int) $port)
			{
				return true;
			}
		}

		return false;
	}


	/**
	 * Normalize a hosts list: lowercase, no scheme / path, unique
	 *
	 * @param   array|string  $hosts
	 *
	 * @return  array
	 */
	protected static function normalizeHostList($hosts)
	{
		if (!is_array($hosts))
		{
			$hosts = preg_split('/[\s,;]+/', (string) $hosts);
		}

		$out = array();

		foreach ($hosts as $h)
		{
			$h = strtolower(trim((string) $h));
			$h = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $h);
			$h = preg_replace('#/.*$#', '', $h);
			$h = trim($h, '.');

			if ($h !== '')
			{
				$out[$h] = $h;
			}
		}

		return array_values($out);
	}


	/**
	 * Check that an IP address (v4 or v6) is a public, globally routable unicast address
	 *
	 * @param   string  $ip
	 *
	 * @return  boolean
	 */
	public static function isPublicIp($ip)
	{
		$ip = trim((string) $ip, " \t\n\r\0\x0B[]");

		if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false)
		{
			return false;
		}

		// IPv6
		if (strpos($ip, ':') !== false)
		{
			$packed = @inet_pton($ip);

			if ($packed === false || strlen($packed) !== 16)
			{
				return false;
			}

			$hex = bin2hex($packed);

			// IPv4-mapped (::ffff:0:0/96) and NAT64 (64:ff9b::/96) addresses embed an IPv4 address: validate that one
			if (substr($hex, 0, 24) === str_repeat('0', 20) . 'ffff' || substr($hex, 0, 24) === '0064ff9b' . str_repeat('0', 16))
			{
				return self::isPublicIp(long2ip((int) hexdec(substr($hex, 24, 8))));
			}

			if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)
			{
				return false;
			}

			$first = hexdec(substr($hex, 0, 4));

			if (($first & 0xfe00) === 0xfc00)   // fc00::/7 unique local
			{
				return false;
			}

			if (($first & 0xffc0) === 0xfe80)   // fe80::/10 link local
			{
				return false;
			}

			if (($first & 0xff00) === 0xff00)   // ff00::/8 multicast
			{
				return false;
			}

			if ($hex === str_repeat('0', 32) || $hex === str_repeat('0', 31) . '1')   // :: and ::1
			{
				return false;
			}

			if (substr($hex, 0, 8) === '20010db8')   // 2001:db8::/32 documentation
			{
				return false;
			}

			return true;
		}

		// IPv4
		if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)
		{
			return false;
		}

		$long = ip2long($ip);

		$blocks = array(
			array('0.0.0.0', 8), array('10.0.0.0', 8), array('100.64.0.0', 10), array('127.0.0.0', 8), array('169.254.0.0', 16),
			array('172.16.0.0', 12), array('192.0.0.0', 24), array('192.0.2.0', 24), array('192.168.0.0', 16), array('198.18.0.0', 15),
			array('198.51.100.0', 24), array('203.0.113.0', 24), array('224.0.0.0', 4), array('240.0.0.0', 4),
		);

		foreach ($blocks as $block)
		{
			$mask = $block[1] === 0 ? 0 : ((~0 << (32 - $block[1])) & 0xffffffff);

			if (($long & $mask) === (ip2long($block[0]) & $mask))
			{
				return false;
			}
		}

		return true;
	}


	/**
	 * Resolve a hostname to all its IPv4 and IPv6 addresses
	 *
	 * @param   string  $host
	 *
	 * @return  array
	 */
	public static function resolveHost($host)
	{
		$host = trim((string) $host, '.[]');

		if (filter_var($host, FILTER_VALIDATE_IP))
		{
			return array($host);
		}

		$ips = array();

		if (function_exists('dns_get_record'))
		{
			$records = @dns_get_record($host, DNS_A | DNS_AAAA);

			foreach ((array) $records as $record)
			{
				if (!empty($record['ip']))
				{
					$ips[] = $record['ip'];
				}
				elseif (!empty($record['ipv6']))
				{
					$ips[] = $record['ipv6'];
				}
			}
		}

		if (!$ips && function_exists('gethostbynamel'))
		{
			$ips = array_filter((array) @gethostbynamel($host));
		}

		return array_values(array_unique($ips));
	}


	/**
	 * Validate a remote file URL
	 *
	 * @param   string  $url    The URL (a missing scheme is normalized to http://)
	 * @param   string  $error  Set to a message when validation fails
	 *
	 * @return  array|boolean  false when invalid, otherwise an array with keys:
	 *                         url (normalized), scheme, host, port, ips, pin (CURLOPT_RESOLVE entry), trusted, is_site
	 */
	public static function validateUrl($url, & $error = null)
	{
		$error = '';
		$url   = trim((string) $url);

		if ($url === '')
		{
			$error = 'URL is empty';

			return false;
		}

		if (preg_match('/[\x00-\x1f\x7f]/', $url))
		{
			$error = 'URL contains control characters';

			return false;
		}

		$url = str_replace(' ', '%20', $url);

		if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $url))
		{
			$url = 'http://' . ltrim($url, '/');
		}

		$parts = parse_url($url);

		if ($parts === false || empty($parts['host']))
		{
			$error = 'URL is not valid';

			return false;
		}

		$scheme = strtolower(isset($parts['scheme']) ? $parts['scheme'] : '');

		if ($scheme !== 'http' && $scheme !== 'https')
		{
			$error = 'Only HTTP and HTTPS URLs are supported';

			return false;
		}

		if (isset($parts['user']) || isset($parts['pass']))
		{
			$error = 'Credentials inside the URL are not allowed';

			return false;
		}

		$host_literal = strtolower(trim($parts['host'], '.'));
		$host         = trim($host_literal, '[]');
		$default_port = $scheme === 'https' ? 443 : 80;
		$port         = isset($parts['port']) ? (int) $parts['port'] : $default_port;

		if ($host === '' || $port < 1 || $port > 65535)
		{
			$error = 'URL is not valid';

			return false;
		}

		$is_site = $host === self::getSiteHost() && $host !== '';
		$trusted = self::isTrustedHost($host, $port);

		if ($port !== $default_port && !$trusted && !$is_site)
		{
			$error = 'Only the default HTTP / HTTPS ports are allowed for hosts that are not trusted';

			return false;
		}

		$ips = self::resolveHost($host);

		if (!$ips)
		{
			$error = 'Host could not be resolved';

			return false;
		}

		if (!$trusted && !$is_site)
		{
			foreach ($ips as $ip)
			{
				if (!self::isPublicIp($ip))
				{
					$error = 'Host resolves to a non-public address';

					return false;
				}
			}
		}

		$pin_ip = $ips[0];

		if (strpos($pin_ip, ':') !== false)
		{
			$pin_ip = '[' . trim($pin_ip, '[]') . ']';
		}

		$normalized = $scheme . '://' . $host_literal
			. ($port !== $default_port ? ':' . $port : '')
			. (isset($parts['path']) && $parts['path'] !== '' ? $parts['path'] : '/')
			. (isset($parts['query']) ? '?' . $parts['query'] : '');

		return array(
			'url'     => $normalized,
			'scheme'  => $scheme,
			'host'    => $host,
			'port'    => $port,
			'ips'     => $ips,
			'pin'     => $host . ':' . $port . ':' . $pin_ip,
			'trusted' => $trusted,
			'is_site' => $is_site,
		);
	}


	/**
	 * Resolve a (possibly relative) redirect Location against the current target
	 *
	 * @param   string  $location
	 * @param   array   $target    Validated target array (see validateUrl())
	 *
	 * @return  string
	 */
	public static function resolveRedirect($location, $target)
	{
		$location = trim((string) $location);

		if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $location))
		{
			return $location;
		}

		if (substr($location, 0, 2) === '//')
		{
			return $target['scheme'] . ':' . $location;
		}

		$base   = parse_url($target['url']);
		$origin = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');

		if (substr($location, 0, 1) === '/')
		{
			return $origin . self::normalizePath($location);
		}

		$path = isset($base['path']) ? $base['path'] : '/';
		$path = substr($path, 0, strrpos($path, '/') + 1);

		return $origin . self::normalizePath($path . $location);
	}


	/**
	 * Remove dot segments from a URL path
	 *
	 * @param   string  $path
	 *
	 * @return  string
	 */
	protected static function normalizePath($path)
	{
		$query = '';

		if (($pos = strpos($path, '?')) !== false)
		{
			$query = substr($path, $pos);
			$path  = substr($path, 0, $pos);
		}

		$out = array();

		foreach (explode('/', $path) as $segment)
		{
			if ($segment === '..')
			{
				array_pop($out);
			}
			elseif ($segment !== '.')
			{
				$out[] = $segment;
			}
		}

		$result = implode('/', $out);

		return '/' . ltrim($result, '/') . $query;
	}


	/**
	 * Perform one pinned request that never follows redirects
	 *
	 * @param   string    $method  'HEAD', 'RANGE' (GET of the first byte) or 'GET'
	 * @param   array     $target  Validated target array (see validateUrl())
	 * @param   callable  $sink    For 'GET': callable receiving body chunks
	 * @param   integer   $max     For 'GET': maximum number of bytes to pass to the sink (0 = unlimited)
	 *
	 * @return  array  status, headers (lowercase names), errno, error, sent
	 */
	protected static function request($method, $target, $sink = null, $max = 0)
	{
		$status  = 0;
		$headers = array();
		$sent    = 0;

		if (!function_exists('curl_init'))
		{
			return array('status' => 0, 'headers' => array(), 'errno' => -1, 'error' => 'cURL is not available', 'sent' => 0);
		}

		$ch = curl_init();

		$options = array(
			CURLOPT_URL            => $target['url'],
			CURLOPT_RESOLVE        => array($target['pin']),
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_MAXREDIRS      => 0,
			CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
			CURLOPT_TIMEOUT        => $method === 'GET' ? self::STREAM_TIMEOUT : self::TOTAL_TIMEOUT,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_USERAGENT      => self::USER_AGENT,
			CURLOPT_HEADERFUNCTION => function ($ch, $line) use (& $status, & $headers)
			{
				if (preg_match('#^HTTP/[\d.]+\s+(\d{3})#i', $line, $m))
				{
					$status  = (int) $m[1];
					$headers = array();
				}
				elseif (($pos = strpos($line, ':')) !== false)
				{
					$headers[strtolower(trim(substr($line, 0, $pos)))] = trim(substr($line, $pos + 1));
				}

				return strlen($line);
			},
		);

		if (defined('CURLOPT_PROTOCOLS_STR'))
		{
			$options[CURLOPT_PROTOCOLS_STR] = 'http,https';
		}
		elseif (defined('CURLOPT_PROTOCOLS'))
		{
			$options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
		}

		if ($method === 'HEAD')
		{
			$options[CURLOPT_NOBODY] = true;
		}
		elseif ($method === 'RANGE')
		{
			$options[CURLOPT_HTTPGET]     = true;
			$options[CURLOPT_RANGE]       = '0-0';
			$options[CURLOPT_MAXFILESIZE] = 1024 * 1024;
		}
		else
		{
			$options[CURLOPT_HTTPGET]       = true;
			$options[CURLOPT_WRITEFUNCTION] = function ($ch, $data) use (& $status, & $sent, $sink, $max)
			{
				// Discard the body of non-successful responses
				if ($status < 200 || $status >= 300)
				{
					return strlen($data);
				}

				$sent += strlen($data);

				if ($max > 0 && $sent > $max)
				{
					return 0;   // abort the transfer
				}

				if ($sink)
				{
					call_user_func($sink, $data);
				}

				return strlen($data);
			};
		}

		curl_setopt_array($ch, $options);
		curl_exec($ch);

		$errno = curl_errno($ch);
		$error = curl_error($ch);
		curl_close($ch);

		return array('status' => $status, 'headers' => $headers, 'errno' => $errno, 'error' => $error, 'sent' => $sent);
	}


	/**
	 * Follow redirects manually (validating and pinning every hop) until a final response is received
	 *
	 * @param   string  $url
	 * @param   string  $error
	 *
	 * @return  array|boolean  false on failure, otherwise: target, status, headers, size (-1 if unknown)
	 */
	public static function resolveFinal($url, & $error = null)
	{
		$error = '';

		if (!function_exists('curl_init'))
		{
			$error = 'cURL is not available, the remote file cannot be accessed';

			return false;
		}

		$target = self::validateUrl($url, $error);

		if (!$target)
		{
			return false;
		}

		for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++)
		{
			$res = self::request('HEAD', $target);

			// Servers not supporting HEAD: retry with a GET of the first byte
			if (!$res['errno'] && in_array($res['status'], array(405, 501), true))
			{
				$res = self::request('RANGE', $target);
			}

			// The filesize limit of the ranged GET being exceeded still gives us the headers
			if ($res['errno'] && $res['errno'] !== CURLE_FILESIZE_EXCEEDED)
			{
				$error = 'Connection failed: ' . $res['error'];

				return false;
			}

			$status = $res['status'];

			if ($status >= 300 && $status < 400 && !empty($res['headers']['location']))
			{
				if ($hop === self::MAX_REDIRECTS)
				{
					$error = 'Too many redirects';

					return false;
				}

				$next   = self::resolveRedirect($res['headers']['location'], $target);
				$target = self::validateUrl($next, $error);

				if (!$target)
				{
					$error = 'Redirect target was rejected: ' . $error;

					return false;
				}

				continue;
			}

			if ($status < 200 || $status >= 300)
			{
				$error = 'HTTP ' . $status;

				return false;
			}

			$size = -1;

			if ($status === 206 && !empty($res['headers']['content-range']) && preg_match('#/\s*(\d+)\s*$#', $res['headers']['content-range'], $m))
			{
				$size = (int) $m[1];
			}
			elseif ($status !== 206 && isset($res['headers']['content-length']) && is_numeric($res['headers']['content-length']))
			{
				$size = (int) $res['headers']['content-length'];
			}

			return array('target' => $target, 'status' => $status, 'headers' => $res['headers'], 'size' => $size);
		}

		$error = 'Too many redirects';

		return false;
	}


	/**
	 * Get the size of a remote file without downloading it
	 *
	 * @param   string  $url
	 * @param   string  $error  Set to a message when -999 is returned
	 *
	 * @return  integer  The size, -1 if it could not be determined, -999 on error (URL rejected, connection failed, HTTP error)
	 */
	public static function headSize($url, & $error = null)
	{
		$error = '';

		if (!function_exists('curl_init'))
		{
			return -1;
		}

		$final = self::resolveFinal($url, $error);

		if ($final === false)
		{
			return -999;
		}

		if ($final['size'] >= 0)
		{
			return $final['size'];
		}

		// No length in the response headers: try a GET of the first byte (no redirects are followed)
		$res = self::request('RANGE', $final['target']);

		if ((!$res['errno'] || $res['errno'] === CURLE_FILESIZE_EXCEEDED) && $res['status'] >= 200 && $res['status'] < 300)
		{
			if ($res['status'] === 206 && !empty($res['headers']['content-range']) && preg_match('#/\s*(\d+)\s*$#', $res['headers']['content-range'], $m))
			{
				return (int) $m[1];
			}

			if ($res['status'] !== 206 && isset($res['headers']['content-length']) && is_numeric($res['headers']['content-length']))
			{
				return (int) $res['headers']['content-length'];
			}
		}

		return -1;
	}


	/**
	 * Prepare proxying a remote file to the browser: validate the URL and all redirects, apply the trusted hosts
	 * policy (when a list is configured only trusted hosts, or the site itself, are proxied) and the size limit
	 *
	 * @param   string   $url
	 * @param   integer  $max_bytes  Maximum allowed size (0 = unlimited)
	 * @param   string   $error
	 *
	 * @return  array|boolean  false when the file must not be proxied, otherwise the data for streamDownload()
	 */
	public static function prepareDownload($url, $max_bytes = 0, & $error = null)
	{
		$error = '';

		if (!function_exists('curl_init'))
		{
			$error = 'cURL is not available';

			return false;
		}

		$first = self::validateUrl($url, $error);

		if (!$first)
		{
			return false;
		}

		if (self::hasTrustedHosts() && !$first['trusted'] && !$first['is_site'])
		{
			$error = 'Host is not in the trusted remote hosts list';

			return false;
		}

		$final = self::resolveFinal($url, $error);

		if (!$final)
		{
			return false;
		}

		if (self::hasTrustedHosts() && !$final['target']['trusted'] && !$final['target']['is_site'])
		{
			$error = 'Redirect target host is not in the trusted remote hosts list';

			return false;
		}

		if ($max_bytes > 0 && $final['size'] > $max_bytes)
		{
			$error = 'File is too large to be proxied';

			return false;
		}

		$final['max_bytes'] = (int) $max_bytes;

		return $final;
	}


	/**
	 * Stream a prepared remote file to the output (the caller sends the HTTP headers before calling this)
	 *
	 * @param   array  $prepared  The result of prepareDownload()
	 *
	 * @return  boolean  true if the remote server answered with a success status
	 */
	public static function streamDownload($prepared)
	{
		if (empty($prepared['target']))
		{
			return false;
		}

		$sink = function ($data)
		{
			echo $data;
			flush();
		};

		$res = self::request('GET', $prepared['target'], $sink, isset($prepared['max_bytes']) ? (int) $prepared['max_bytes'] : 0);

		// An aborted transfer (e.g. the size limit was exceeded while streaming) is reported as a failure
		return $res['status'] >= 200 && $res['status'] < 300 && !$res['errno'];
	}
}
