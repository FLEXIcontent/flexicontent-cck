<?php
/**
 * FLEXIcontent: request guard of the bundled phpThumb endpoint.
 *
 * phpThumb.php answers anyone who can reach it. This file refuses the request shapes that FLEXIcontent never issues
 * and that make the endpoint read files outside the site, contact other hosts, or do unbounded work, and it serves
 * the images of protected folders to signed requests only.
 * It is included by phpThumb.php before the request is processed and has no dependency on Joomla.
 */

if (!function_exists('flexicontent_phpthumb_refusal'))
{
	/**
	 * Check a file argument (the source, or a file named inside a filter)
	 *
	 * @return  string|null  The reason for refusing it, null if it is acceptable
	 */
	function flexicontent_phpthumb_file_refusal($value)
	{
		if (!is_string($value))
		{
			return 'file argument is not a string';
		}

		if (strpos($value, "\0") !== false)
		{
			return 'file argument contains a NUL byte';
		}

		$path = str_replace('\\', '/', trim($value));

		// Stream wrappers and remote schemes: http and https are checked against the host names of the site, see
		// flexicontent_phpthumb_remote_refusal(), anything else (file, phar, ftp, data, php, ...) is refused.
		// A Windows drive letter is a single letter and is not a scheme
		if (preg_match('#^([a-z][a-z0-9+.\-]+):#i', $path, $m) && !in_array(strtolower($m[1]), array('http', 'https'), true))
		{
			return 'scheme "' . strtolower($m[1]) . '" is not allowed';
		}

		// Network paths
		if (strncmp($path, '//', 2) === 0)
		{
			return 'network paths are not allowed';
		}

		// Parent folder segments, also in their encoded form
		if (preg_match('#(^|/)\.\.(/|$)#', $path) || preg_match('#(^|/)\.\.(/|$)#', str_replace('\\', '/', rawurldecode($path))))
		{
			return 'parent folder segments are not allowed';
		}

		return null;
	}


	/**
	 * Check the parameters of a phpThumb request
	 *
	 * @param   array  $get  The request parameters
	 *
	 * @return  string|null  The reason for refusing the request, null if it is acceptable
	 */
	function flexicontent_phpthumb_refusal(array $get)
	{
		// The signature is a string
		if (isset($get['hash']) && !is_string($get['hash']))
		{
			return 'invalid hash';
		}

		// The "md5s" lookup fetches the source and reports its checksum before any other check runs
		if (array_key_exists('md5s', $get))
		{
			return 'parameter "md5s" is not supported';
		}

		// A canvas without a source image, of any size
		if (array_key_exists('new', $get))
		{
			return 'parameter "new" is not supported';
		}

		if (isset($get['src']) && ($reason = flexicontent_phpthumb_file_refusal($get['src'])) !== null)
		{
			return 'src: ' . $reason;
		}

		// Filters take file names too (mask, overlay, watermark image, font), as arguments separated by "|"
		if (isset($get['fltr']))
		{
			foreach ((array) $get['fltr'] as $filter)
			{
				if (!is_string($filter))
				{
					return 'filter is not a string';
				}

				foreach (explode('|', $filter) as $i => $argument)
				{
					if ($i > 0 && ($reason = flexicontent_phpthumb_file_refusal($argument)) !== null)
					{
						return 'filter: ' . $reason;
					}
				}
			}

			if (count((array) $get['fltr']) > 20)
			{
				return 'too many filters';
			}
		}

		// Sizes: larger output than any layout asks for only costs memory, CPU time and cache space
		foreach (array('w', 'h', 'wp', 'hp', 'wl', 'hl', 'ws', 'hs', 'sw', 'sh') as $name)
		{
			if (!isset($get[$name]))
			{
				continue;
			}

			if (!is_scalar($get[$name]) || (is_numeric($get[$name]) && (float) $get[$name] > 6000))
			{
				return 'parameter "' . $name . '" is out of range';
			}
		}

		return null;
	}


	/**
	 * Check a URL that phpThumb would fetch (a source, a file named inside a filter, or the target of a redirect)
	 * against the host names the endpoint may fetch from. The list is written by FLEXIcontent to
	 * phpThumb.config_FLEXI.php ('nohotlink_valid_domains'); it never comes from the request, whose Host header is
	 * chosen by the sender.
	 *
	 * @param   string  $url    An http or https URL
	 * @param   array   $hosts  Host names, without port
	 *
	 * @return  string|null  The reason for refusing it, null if it is acceptable
	 */
	function flexicontent_phpthumb_url_refusal($url, array $hosts)
	{
		// A backslash, a space or a control character is read differently by different URL parsers
		if (!is_string($url) || preg_match('/[\\\\\x00-\x20\x7f]/', trim($url)) || !preg_match('#^(https?)://([^/?\#]*)#i', trim($url), $m))
		{
			return 'not a plain http(s) URL';
		}

		$scheme    = strtolower($m[1]);
		$authority = $m[2];

		// "user@host" hides the real host from a reader, and no URL built by FLEXIcontent has it
		// A host name or a bracketed IPv6 literal, and an optional port
		if (strpos($authority, '@') !== false || !preg_match('/^(\[[0-9a-f:.]+\]|[a-z0-9._\-]+)(?::(\d*))?$/i', $authority, $p))
		{
			return 'the host of the URL is not a plain host name';
		}

		$authority = $p[1];
		$port      = isset($p[2]) ? $p[2] : '';

		// Only the port of the scheme: another port of the same host is another service
		if ($port !== '' && (int) $port !== ($scheme === 'https' ? 443 : 80))
		{
			return 'the port of the URL is not the port of its scheme';
		}

		$host  = strtolower(trim($authority, '.[]'));
		$valid = array();

		foreach ($hosts as $valid_host)
		{
			if (is_string($valid_host) && trim($valid_host, " .[]") !== '')
			{
				$valid[] = strtolower(trim($valid_host, " .[]"));
			}
		}

		if (!$valid)
		{
			return 'no host name of this site is configured for remote sources';
		}

		return $host !== '' && in_array($host, $valid, true) ? null : 'the host of the URL is not a host of this site';
	}


	/**
	 * Refuse a request whose source, or a file named inside one of its filters, is a URL of a host that the endpoint
	 * may not fetch from
	 *
	 * @param   array  $get    The request parameters
	 * @param   array  $hosts  Host names, without port
	 *
	 * @return  string|null  The reason for refusing the request, null if it is acceptable
	 */
	function flexicontent_phpthumb_remote_refusal(array $get, array $hosts)
	{
		$urls = array();

		if (isset($get['src']) && is_string($get['src']) && preg_match('#^\s*https?:#i', $get['src']))
		{
			$urls[] = $get['src'];
		}

		foreach (isset($get['fltr']) ? (array) $get['fltr'] : array() as $filter)
		{
			foreach (is_string($filter) ? explode('|', $filter) : array() as $i => $argument)
			{
				if ($i > 0 && preg_match('#^\s*https?:#i', $argument))
				{
					$urls[] = $argument;
				}
			}
		}

		foreach ($urls as $url)
		{
			if (($reason = flexicontent_phpthumb_url_refusal($url, $hosts)) !== null)
			{
				return 'remote source: ' . $reason;
			}
		}

		return null;
	}


	/**
	 * Whether phpThumb may follow a redirect to the given URL: only to a host it may fetch from. Without this a
	 * redirect on the site (an open redirect, a moved file) would lead the fetch to any host.
	 * The host list is the one phpThumb.php checked the request against.
	 *
	 * @param   string  $url
	 *
	 * @return  bool
	 */
	function flexicontent_phpthumb_may_follow($url)
	{
		$hosts = isset($GLOBALS['PHPTHUMB_FLEXI_REMOTE_HOSTS']) && is_array($GLOBALS['PHPTHUMB_FLEXI_REMOTE_HOSTS'])
			? $GLOBALS['PHPTHUMB_FLEXI_REMOTE_HOSTS']
			: array();

		return flexicontent_phpthumb_url_refusal($url, $hosts) === null;
	}


	/**
	 * Canonical form of a path for comparisons: the real location of the deepest part that exists, so that links,
	 * short names, letter case and repeated separators cannot hide the folder a file is in
	 *
	 * @return  string  Forward slashes, no trailing slash
	 */
	function flexicontent_phpthumb_path($path)
	{
		$path = preg_replace('#/+#', '/', str_replace('\\', '/', (string) $path));
		$tail = '';

		while ($path !== '' && strpos($path, "\0") === false)
		{
			if (($real = @realpath($path)) !== false)
			{
				$path = str_replace('\\', '/', $real);
				break;
			}

			$parent = dirname($path);

			if ($parent === $path || $parent === '.' || $parent === '')
			{
				break;
			}

			$tail = '/' . basename($path) . $tail;
			$path = $parent;
		}

		return rtrim(rtrim($path, '/') . $tail, '/');
	}


	/**
	 * Whether a file lies in a folder that FLEXIcontent keeps away from the public: one of the given folders (the
	 * secure file folder), or the "original" folder of an image field (<field folder>/item_<id>_field_<id>/original)
	 * unless the field was configured to leave its originals public
	 *
	 * @param   string  $file            Path in the file system
	 * @param   array   $protected_dirs  Paths in the file system
	 *
	 * @return  bool
	 */
	function flexicontent_phpthumb_is_protected($file, array $protected_dirs)
	{
		$file = flexicontent_phpthumb_path($file);

		foreach ($protected_dirs as $dir)
		{
			$dir = flexicontent_phpthumb_path($dir);

			// Letter case is ignored: a file system that ignores it opens the folder under any spelling
			if ($dir !== '' && strncasecmp($file . '/', $dir . '/', strlen($dir) + 1) === 0)
			{
				return true;
			}
		}

		if (preg_match('#^(.*/item_[^/]+_field_\d+/original)/#i', $file, $m) && @is_dir($m[1]))
		{
			// The image field writes this file: "Deny from all" by default, "Allow from all" when originals are public
			$rules = @file_get_contents($m[1] . '/.htaccess');

			return !is_string($rules) || stripos($rules, 'Allow from all') === false || stripos($rules, 'Deny from all') !== false;
		}

		return false;
	}


	/**
	 * Paths in the file system that a file argument may be resolved to by phpThumb
	 *
	 * @param   string  $value          The source, or a file named inside a filter
	 * @param   string  $document_root  Document root that phpThumb uses
	 * @param   string  $script_dir     Folder of phpThumb.php
	 *
	 * @return  array
	 */
	function flexicontent_phpthumb_candidates($value, $document_root, $script_dir)
	{
		$value         = str_replace('\\', '/', trim((string) $value));
		$document_root = rtrim(str_replace('\\', '/', (string) $document_root), '/');

		if ($value === '')
		{
			return array();
		}

		// A URL is fetched from the web server, which applies its own rules. Its path is checked as if it were local
		if (preg_match('#^https?://[^/?\#]*(/[^?\#]*)?#i', $value, $m))
		{
			return isset($m[1]) ? array($document_root . rawurldecode($m[1])) : array();
		}

		// Absolute path with a drive letter
		if (preg_match('#^[a-z]:#i', $value))
		{
			return array($value);
		}

		// Relative to the document root, or an absolute path
		if ($value[0] === '/')
		{
			return array_unique(array($document_root . $value, $value));
		}

		// Relative to the folder of phpThumb.php
		return array(rtrim(str_replace('\\', '/', (string) $script_dir), '/') . '/' . $value);
	}


	/**
	 * Folders whose images are served to signed requests only: the uploads folder of the component and the secure
	 * file folder of the component configuration, which FLEXIcontent writes to phpThumb.config_FLEXI.php
	 *
	 * @return  array  Paths in the file system
	 */
	function flexicontent_phpthumb_protected_dirs()
	{
		// This file is in <site>/components/com_flexicontent/librairies/phpthumb
		$dirs = array(dirname(__DIR__, 2) . '/uploads');
		$site = dirname(__DIR__, 4);

		$configured = isset($GLOBALS['PHPTHUMB_FLEXI_PROTECTED_DIRS']) ? $GLOBALS['PHPTHUMB_FLEXI_PROTECTED_DIRS'] : array();

		foreach ((array) $configured as $dir)
		{
			if (is_string($dir) && trim($dir) !== '')
			{
				$dirs[] = preg_match('#^(?:[a-z]:)?[\\\\/]#i', $dir) ? $dir : $site . '/' . $dir;
			}
		}

		return $dirs;
	}


	/**
	 * Key strength as computed by phpthumb_functions::PasswordStrength()
	 *
	 * @return  float
	 */
	function flexicontent_phpthumb_key_strength($key)
	{
		$key = (string) $key;

		return
			strlen(preg_replace('#[^a-z]#', '', $key)) * 0.5 +
			strlen(preg_replace('#[^A-Z]#', '', $key)) * 0.8 +
			strlen(preg_replace('#[^0-9]#', '', $key)) * 1.0 +
			strlen(preg_replace('#[a-zA-Z0-9]#', '', $key)) * 2.0;
	}


	/**
	 * Whether a request carries a signature made with the key of the site, checked exactly as phpThumb.php checks it
	 *
	 * @param   array   $get           The request parameters
	 * @param   string  $query_string  The query string as received, the signature covers all of it
	 * @param   array   $config        high_security_password, high_security_url_separator
	 *
	 * @return  bool
	 */
	function flexicontent_phpthumb_signed(array $get, $query_string, array $config)
	{
		$key       = isset($config['high_security_password']) ? (string) $config['high_security_password'] : '';
		$separator = !empty($config['high_security_url_separator']) ? (string) $config['high_security_url_separator'] : '&';

		if (empty($get['hash']) || !is_string($get['hash']) || flexicontent_phpthumb_key_strength($key) < 20)
		{
			return false;
		}

		$signed = str_replace($separator . 'hash=' . $get['hash'], '', (string) $query_string);

		return hash_equals(hash_hmac('sha256', $signed, $key), $get['hash']);
	}


	/**
	 * Refuse a request that reads an image of a protected folder, as source or inside a filter, unless it is signed.
	 * This does not depend on the high security setting: the web server already refuses to serve these folders, and
	 * phpThumb reads from the disk. The URLs that FLEXIcontent builds for such images are always signed.
	 *
	 * @param   array       $get             The request parameters
	 * @param   string      $query_string    The query string as received
	 * @param   array       $config          document_root, high_security_password, high_security_url_separator
	 * @param   array|null  $protected_dirs  Paths in the file system (default: flexicontent_phpthumb_protected_dirs())
	 * @param   string      $script_dir      Folder of phpThumb.php
	 *
	 * @return  string|null  The reason for refusing the request, null if it is acceptable
	 */
	function flexicontent_phpthumb_protected_refusal(array $get, $query_string, array $config, $protected_dirs = null, $script_dir = __DIR__)
	{
		$files = array();

		if (isset($get['src']) && is_string($get['src']))
		{
			$files[] = $get['src'];
		}

		// File names inside filters (mask, overlay, watermark image) always contain a folder
		foreach (isset($get['fltr']) ? (array) $get['fltr'] : array() as $filter)
		{
			foreach (is_string($filter) ? explode('|', $filter) : array() as $i => $argument)
			{
				if ($i > 0 && strpbrk($argument, '/\\') !== false)
				{
					$files[] = $argument;
				}
			}
		}

		$protected_dirs = is_array($protected_dirs) ? $protected_dirs : flexicontent_phpthumb_protected_dirs();
		$document_root  = isset($config['document_root']) ? (string) $config['document_root'] : '';

		foreach ($files as $file)
		{
			foreach (flexicontent_phpthumb_candidates($file, $document_root, $script_dir) as $candidate)
			{
				if (flexicontent_phpthumb_is_protected($candidate, $protected_dirs))
				{
					return flexicontent_phpthumb_signed($get, $query_string, $config)
						? null
						: 'the image is in a protected folder and the request is not signed';
				}
			}
		}

		return null;
	}


	/**
	 * phpThumb applies the limits of its cache at most once per hour: the time of the last run is the modification
	 * time of phpThumbCacheStats.txt. Make that file look old once a shorter interval has passed, so that the next
	 * call of CleanUpCacheDirectory() does its work.
	 *
	 * @param   string  $cache_directory
	 * @param   int     $interval         Seconds between two clean-ups, 0 keeps the interval of phpThumb
	 *
	 * @return  bool  True if the next clean-up was brought forward
	 */
	function flexicontent_phpthumb_cache_cleanup_due($cache_directory, $interval)
	{
		$interval = (int) $interval;
		$file     = rtrim((string) $cache_directory, '/\\') . DIRECTORY_SEPARATOR . 'phpThumbCacheStats.txt';

		if ($interval <= 0 || $interval >= 3600)
		{
			return false;
		}

		// PHP remembers the times of the last file it looked at, also after touch()
		clearstatcache(true, $file);

		$modified = is_file($file) ? @filemtime($file) : false;

		if ($modified === false || $modified > time() - $interval)
		{
			return false;
		}

		$changed = @touch($file, time() - 3601);

		clearstatcache(true, $file);

		return $changed;
	}
}
