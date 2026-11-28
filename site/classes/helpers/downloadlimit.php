<?php
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/**
 * Download quotas per user group (e.g. Public 5/day, Registered 10/day, Subscriber unlimited).
 *
 * Settings live in the component's configuration and can be overridden per File / MediaFile field:
 *   dl_limit_override  field only: 0 = use component settings, 1 = use this field's settings, 2 = no limits for this field
 *   dl_limit_enable    component only: master switch
 *   dl_limit_count_mode 0 = Any file (every download counts), 1 = Unique file (a file counts once per window)
 *   dl_limit_window + dl_limit_window_unit  length of the (sliding) window, unit 1 = minutes, 60 = hours, 1440 = days
 *   dl_limit_groups    subform rows {group_id, limit}, limit -1 = unlimited
 *   dl_limit_guest_ip_factor guests are also limited per IP, at (group limit x factor), so offices behind one IP do not share a single quota
 *   dl_limit_count_special  1 = downloads through coupons / download cart also count
 *   dl_limit_modal_html  HTML shown in the modal when the quota is exhausted
 */
class flexicontent_downloadlimit
{
	const COOKIE = 'fc_dlv';

	/** @var array|null */
	protected static $identity = null;

	/**
	 * Effective settings for a field, or null when the field is not limited.
	 *
	 * @param   \Joomla\Registry\Registry  $fieldParams
	 * @return  array|null
	 */
	public static function getSettings($fieldParams)
	{
		$override = (int) $fieldParams->get('dl_limit_override', 0);

		if ($override === 2)
		{
			return null;
		}

		if ($override === 1)
		{
			$source = $fieldParams;
		}
		else
		{
			$source = ComponentHelper::getParams('com_flexicontent');

			if (!(int) $source->get('dl_limit_enable', 0))
			{
				return null;
			}
		}

		return array(
			'mode'       => (int) $source->get('dl_limit_count_mode', 0) === 1 ? 1 : 0,
			'window'     => max(1, (int) $source->get('dl_limit_window', 24)) * self::windowUnit($source->get('dl_limit_window_unit', 60)) * 60,  // seconds
			'groups'     => self::parseGroups($source->get('dl_limit_groups')),
			'ip_factor'  => max(1, (int) $source->get('dl_limit_guest_ip_factor', 5)),
			'count_special' => (int) $source->get('dl_limit_count_special', 0),
			'modal_html' => (string) $source->get('dl_limit_modal_html', ''),
		);
	}

	/** Settings of a field as the download handler sees them (field + content type configuration) */
	public static function getFieldSettings($fieldId, $itemId)
	{
		$db = Factory::getContainer()->get(DatabaseInterface::class);

		require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/tables/flexicontent_items.php';
		require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/tables/flexicontent_fields.php';
		$item  = new \flexicontent_items($db);
		$field = new \flexicontent_fields($db);

		if (!$item->load((int) $itemId) || !$field->load((int) $fieldId) || !in_array($field->field_type, array('file', 'mediafile'), true))
		{
			return null;
		}

		\FlexicontentFields::loadFieldConfig($field, $item);

		return self::getSettings($field->parameters);
	}

	/**
	 * Attributes for download buttons/links of limited fields; the script disables them when the quota is exhausted.
	 * Returns '' when the field is not limited, so unlimited fields render exactly as before.
	 */
	public static function getButtonAttrs($field, $fileId, $itemId)
	{
		if (Factory::getApplication()->isClient('administrator') || !self::getSettings($field->parameters))
		{
			return '';
		}

		static $loaded = false;

		if (!$loaded)
		{
			$loaded = true;
			$doc = Factory::getApplication()->getDocument();
			$doc->addScriptOptions('fcDownloadLimit', array(
				'url'      => \Joomla\CMS\Router\Route::_('index.php?option=com_flexicontent&task=dlstatus&format=raw', false),
				'fallback' => \Joomla\CMS\Language\Text::_('FLEXI_DL_LIMIT_DEFAULT_MODAL'),
				'close'    => \Joomla\CMS\Language\Text::_('JCLOSE'),
			));
			$doc->getWebAssetManager()->registerAndUseScript(
				'com_flexicontent.downloadlimit',
				'components/com_flexicontent/assets/js/downloadlimit.js',
				array('version' => defined('FLEXI_VHASH') ? FLEXI_VHASH : 'auto'),
				array('defer' => true)
			);
		}

		return ' data-fc-dl="' . (int) $field->id . ':' . (int) $fileId . ':' . (int) $itemId . '"';
	}

	/** Minutes per window unit: 1 = minutes, 60 = hours, 1440 = days */
	protected static function windowUnit($unit)
	{
		$unit = (int) $unit;

		return in_array($unit, array(1, 60, 1440), true) ? $unit : 60;
	}

	/** Length of the period in seconds to text, e.g. "5 min", "24 h", "7 d" */
	public static function formatWindow($seconds)
	{
		$minutes = max(1, (int) round($seconds / 60));

		if ($minutes % 1440 === 0) return \Joomla\CMS\Language\Text::sprintf('FLEXI_DL_LIMIT_WIN_DAYS', $minutes / 1440);
		if ($minutes % 60 === 0)   return \Joomla\CMS\Language\Text::sprintf('FLEXI_DL_LIMIT_WIN_HOURS', $minutes / 60);

		return \Joomla\CMS\Language\Text::sprintf('FLEXI_DL_LIMIT_WIN_MINUTES', $minutes);
	}

	/** Subform rows to a map group id => limit */
	public static function parseGroups($raw)
	{
		$map = array();
		$rows = json_decode(json_encode($raw), true);

		foreach ((array) $rows as $row)
		{
			if (!is_array($row) || !isset($row['group_id']) || !isset($row['limit']) || trim((string) $row['limit']) === '')
			{
				continue;
			}

			$limit = (int) $row['limit'];
			$map[(int) $row['group_id']] = $limit < 0 ? -1 : $limit;
		}

		return $map;
	}

	/**
	 * Limit of a user: -1 = unlimited. Groups inherit upwards (Public applies to Registered, ...),
	 * the most generous matching group wins. No matching group means no limit.
	 */
	public static function getUserLimit(array $settings, $user)
	{
		if ($user->authorise('core.admin'))
		{
			return -1;
		}

		$best = null;

		foreach ($user->getAuthorisedGroups() as $groupId)
		{
			if (!isset($settings['groups'][(int) $groupId]))
			{
				continue;
			}

			$limit = $settings['groups'][(int) $groupId];

			if ($limit === -1)
			{
				return -1;
			}

			$best = $best === null ? $limit : max($best, $limit);
		}

		return $best === null ? -1 : $best;
	}

	/** Who is downloading: user id, or for guests a signed cookie id plus a hashed IP. */
	public static function getIdentity($setCookie = true)
	{
		if (self::$identity !== null)
		{
			return self::$identity;
		}

		$app    = Factory::getApplication();
		$user   = $app->getIdentity();
		$secret = (string) $app->get('secret');
		$ip     = substr(hash_hmac('sha256', 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? ''), $secret), 0, 32);

		$visitor = '';

		if (!$user->id)
		{
			$cookie = (string) $app->getInput()->cookie->getString(self::COOKIE, '');

			if (preg_match('/^([a-f0-9]{32})\.([a-f0-9]{16})$/D', $cookie, $m)
				&& hash_equals(substr(hash_hmac('sha256', 'dlv:' . $m[1], $secret), 0, 16), $m[2]))
			{
				$visitor = $m[1];
			}
			elseif ($setCookie)
			{
				$visitor = bin2hex(random_bytes(16));
				$value   = $visitor . '.' . substr(hash_hmac('sha256', 'dlv:' . $visitor, $secret), 0, 16);

				if (!headers_sent())
				{
					$app->getInput()->cookie->set(self::COOKIE, $value, array(
						'expires'  => time() + 365 * 86400,
						'path'     => $app->get('cookie_path', '/') ?: '/',
						'domain'   => $app->get('cookie_domain', ''),
						'secure'   => \Joomla\CMS\Uri\Uri::getInstance()->isSsl(),
						'httponly' => true,
						'samesite' => 'Lax',
					));
				}
			}
		}

		return self::$identity = array('user_id' => (int) $user->id, 'visitor' => $visitor, 'ip' => $ip);
	}

	/** SQL condition matching the current identity's rows ($limitIp: IP match only, used for the IP ceiling) */
	protected static function identityWhere($db, array $id, $part = 'main')
	{
		if ($id['user_id'])
		{
			return $part === 'ip' ? '1=0' : 'user_id = ' . (int) $id['user_id'];
		}

		if ($part === 'ip')
		{
			return 'user_id = 0 AND ip_hash = ' . $db->quote($id['ip']);
		}

		return $id['visitor'] !== '' ? 'user_id = 0 AND visitor_id = ' . $db->quote($id['visitor']) : '1=0';
	}

	protected static function countRows($db, array $settings, $where)
	{
		$since = gmdate('Y-m-d H:i:s', time() - $settings['window']);
		$what  = $settings['mode'] === 1 ? 'COUNT(DISTINCT file_id)' : 'COUNT(*)';

		return (int) $db->setQuery(
			'SELECT ' . $what . ' FROM ' . $db->quoteName('#__flexicontent_download_log')
			. ' WHERE downloaded_on > ' . $db->quote($since) . ' AND (' . $where . ')'
		)->loadResult();
	}

	protected static function fileSeen($db, array $settings, array $id, $fileId)
	{
		$since = gmdate('Y-m-d H:i:s', time() - $settings['window']);
		$where = array_filter(array(self::identityWhere($db, $id), self::identityWhere($db, $id, 'ip')), function ($w) { return $w !== '1=0'; });

		if (!$where)
		{
			return false;
		}

		return (bool) $db->setQuery(
			'SELECT 1 FROM ' . $db->quoteName('#__flexicontent_download_log')
			. ' WHERE file_id = ' . (int) $fileId . ' AND downloaded_on > ' . $db->quote($since)
			. ' AND ((' . implode(') OR (', $where) . ')) LIMIT 1'
		)->loadResult();
	}

	/**
	 * Current quota state.
	 *
	 * @return array  limit (-1 unlimited), used, exhausted (bool: a download of an unseen file is refused)
	 */
	public static function getState(array $settings, $user = null, $setCookie = true)
	{
		$user  = $user ?: Factory::getApplication()->getIdentity();
		$limit = self::getUserLimit($settings, $user);

		if ($limit === -1)
		{
			return array('limit' => -1, 'used' => 0, 'exhausted' => false);
		}

		$db   = Factory::getContainer()->get(DatabaseInterface::class);
		$id   = self::getIdentity($setCookie);
		$used = self::countRows($db, $settings, self::identityWhere($db, $id));
		$exhausted = $used >= $limit;
		$by = 'main'; $dimUsed = $used; $cap = $limit;

		// Guests: IP ceiling, a multiple of the limit, so clearing cookies does not reset the quota
		if (!$exhausted && !$id['user_id'])
		{
			$ipUsed = self::countRows($db, $settings, self::identityWhere($db, $id, 'ip'));
			$used = max($used, $ipUsed);
			$exhausted = $ipUsed >= $limit * $settings['ip_factor'];

			if ($exhausted)
			{
				$by = 'ip'; $dimUsed = $ipUsed; $cap = $limit * $settings['ip_factor'];
			}
		}

		return array('limit' => $limit, 'used' => $used, 'exhausted' => $exhausted, 'by' => $by, 'dim_used' => $dimUsed, 'cap' => $cap);
	}

	/**
	 * Values for the "limit reached" texts (see placeholders): seconds until the next download becomes possible
	 * (the window is sliding, so the oldest counted download expires first) and until the whole counter is back to zero.
	 */
	public static function getResetInfo(array $settings, $user = null)
	{
		$state = self::getState($settings, $user, false);
		$info  = array('limit' => $state['limit'], 'used' => $state['used'], 'window' => $settings['window'], 'reset_in' => 0, 'reset_full_in' => 0);

		if (empty($state['exhausted']))
		{
			return $info;
		}

		$db    = Factory::getContainer()->get(DatabaseInterface::class);
		$where = self::identityWhere($db, self::getIdentity(false), $state['by']);
		$since = $db->quote(gmdate('Y-m-d H:i:s', time() - $settings['window']));
		$table = $db->quoteName('#__flexicontent_download_log');
		$base  = ' FROM ' . $table . ' WHERE downloaded_on > ' . $since . ' AND (' . $where . ')';
		$skip  = max(0, $state['dim_used'] - $state['cap']);

		$first = $settings['mode'] === 1
			? 'SELECT MIN(downloaded_on) AS m' . $base . ' GROUP BY file_id ORDER BY m ASC LIMIT 1 OFFSET ' . (int) $skip
			: 'SELECT downloaded_on' . $base . ' ORDER BY downloaded_on ASC LIMIT 1 OFFSET ' . (int) $skip;
		$last  = 'SELECT MAX(downloaded_on)' . $base;

		$ends = function ($stamp) use ($settings) {
			return $stamp ? max(0, strtotime($stamp . ' UTC') + $settings['window'] - time()) : 0;
		};

		$info['reset_in']      = $ends($db->setQuery($first)->loadResult());
		$info['reset_full_in'] = $ends($db->setQuery($last)->loadResult());

		return $info;
	}

	/** Seconds to text, e.g. "2 d 3 h", "5 h 12 min", "7 min" */
	public static function formatDuration($seconds)
	{
		$minutes = (int) ceil(max(0, (int) $seconds) / 60);
		$days    = intdiv($minutes, 1440);
		$hours   = intdiv($minutes % 1440, 60);

		if ($days > 0)  return \Joomla\CMS\Language\Text::sprintf('FLEXI_DL_LIMIT_FMT_DAYS', $days, $hours);
		if ($hours > 0) return \Joomla\CMS\Language\Text::sprintf('FLEXI_DL_LIMIT_FMT_HOURS', $hours, $minutes % 60);

		return \Joomla\CMS\Language\Text::sprintf('FLEXI_DL_LIMIT_FMT_MINUTES', max(1, $minutes));
	}

	/** Replace the placeholders {reset_in} {reset_full_in} {reset_seconds} {limit} {used} {window} in a text ({window} = length of the period, e.g. "5 min", "24 h") */
	public static function replacePlaceholders($text, array $info)
	{
		return strtr($text, array(
			'{reset_in}'      => self::formatDuration($info['reset_in']),
			'{reset_full_in}' => self::formatDuration($info['reset_full_in']),
			'{reset_seconds}' => (string) (int) $info['reset_in'],
			'{limit}'         => (string) (int) $info['limit'],
			'{used}'          => (string) (int) $info['used'],
			'{window}'        => self::formatWindow($info['window']),
		));
	}

	/** Is this particular file still downloadable (in Unique mode, files already taken in the window are free) */
	public static function canDownloadFile(array $settings, array $state, $fileId)
	{
		if (!$state['exhausted'])
		{
			return true;
		}

		if ($settings['mode'] !== 1)
		{
			return false;
		}

		$db = Factory::getContainer()->get(DatabaseInterface::class);

		return self::fileSeen($db, $settings, self::getIdentity(false), $fileId);
	}

	/**
	 * Check + record a download. Returns true when allowed (and recorded), false when the quota is exhausted.
	 */
	public static function consume(array $settings, $fileId, $fieldId, $user = null)
	{
		$user  = $user ?: Factory::getApplication()->getIdentity();
		$state = self::getState($settings, $user);

		if ($state['limit'] === -1)
		{
			return true;
		}

		if (!self::canDownloadFile($settings, $state, $fileId))
		{
			return false;
		}

		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$id = self::getIdentity();

		// Unique mode: a file already counted in this window is not recorded again
		if ($settings['mode'] === 1 && self::fileSeen($db, $settings, $id, $fileId))
		{
			return true;
		}

		$now = time();
		$db->setQuery(
			'INSERT INTO ' . $db->quoteName('#__flexicontent_download_log')
			. ' (user_id, visitor_id, ip_hash, file_id, field_id, downloaded_on, expire_on) VALUES ('
			. (int) $id['user_id'] . ', ' . $db->quote($id['visitor']) . ', ' . $db->quote($id['ip']) . ', '
			. (int) $fileId . ', ' . (int) $fieldId . ', '
			. $db->quote(gmdate('Y-m-d H:i:s', $now)) . ', ' . $db->quote(gmdate('Y-m-d H:i:s', $now + $settings['window'])) . ')'
		)->execute();

		/**
		 * Count again, now that this download is recorded. Requests that arrive together all pass the check above,
		 * but here each one sees the rows of the others, so together they can not take more than the limit:
		 * a request that finds the limit exceeded takes its row back and is refused.
		 * Requests that come one after the other never exceed the limit here, the check above already refused them
		 */
		$row_id = (int) $db->insertid();

		if ($row_id && self::isOverLimit($db, $settings, $id, $state['limit']))
		{
			$db->setQuery('DELETE FROM ' . $db->quoteName('#__flexicontent_download_log') . ' WHERE id = ' . $row_id)->execute();

			return false;
		}

		// Rows are useless once their window has passed: purge now and then, a few at a time
		if (mt_rand(1, 20) === 1)
		{
			self::purge($db);
		}

		return true;
	}

	/** Do the recorded downloads of the identity exceed the limit (guests: also the ceiling of their IP) */
	protected static function isOverLimit($db, array $settings, array $id, $limit)
	{
		if (self::countRows($db, $settings, self::identityWhere($db, $id)) > $limit)
		{
			return true;
		}

		return !$id['user_id'] && self::countRows($db, $settings, self::identityWhere($db, $id, 'ip')) > $limit * $settings['ip_factor'];
	}

	public static function purge($db = null)
	{
		$db = $db ?: Factory::getContainer()->get(DatabaseInterface::class);
		$db->setQuery(
			'DELETE FROM ' . $db->quoteName('#__flexicontent_download_log')
			. ' WHERE expire_on < ' . $db->quote(gmdate('Y-m-d H:i:s')) . ' LIMIT 1000'
		)->execute();
	}
}
