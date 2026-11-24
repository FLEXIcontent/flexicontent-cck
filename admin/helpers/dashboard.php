<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @copyright       Copyright © 2019, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;

/**
 * Customizable backend dashboard
 *
 *  - a registry of all built-in buttons (with their permission checks)
 *  - a layout (blocks -> buttons) stored as JSON in the component parameter "dashboard_layout"
 *  - sanitizing of the layout received from the editor, and rendering of the blocks
 *
 * Layout format:
 *   {"v":2,"blocks":[
 *     {"id":"editing","tk":"FLEXI_NAV_SD_CONTENT_EDITING","title":"","w":6,"btns":[
 *        {"k":"items","t":"","na":0},
 *        {"k":"custom","id":"c1","t":"My link","u":"index.php?option=com_x","i":"icon-link","g":"self|blank|modal"}
 *     ]}
 *   ]}
 * "w" is the block width in 1/12 of the row: 3 = 1/4, 4 = 1/3, 6 = 1/2, 8 = 2/3, 12 = full row
 * Blocks with "sys" ("waiting", "sysinfo") are the system blocks (Waiting for action, System info): they have no buttons,
 * their content is provided by the template, but they can be moved, resized and renamed like the other blocks
 * (layouts saved with "v":1 used a grid of 6 columns, their widths are doubled when loaded)
 */
class FlexicontentDashboard
{
	const PARAM = 'dashboard_layout';

	/** Allowed block widths (in 1/12 of the row) */
	const WIDTHS = array(3, 4, 6, 8, 12);

	/** Keys of all built-in buttons */
	const KEYS = array(
		'items', 'cats', 'files', 'comments',
		'types', 'fields', 'tags',
		'templates', 'index', 'searchlogs', 'stats', 'reviews',
		'users', 'groups',
		'import', 'plgfields', 'plgsystem', 'plgflexicontent',
		'support',
	);

	/** System blocks: id => language key of the default title */
	const SYS_BLOCKS = array(
		'waiting' => 'FLEXI_DASHBOARD_WAITING_ACTION',
		'sysinfo' => 'FLEXI_DASHBOARD_SYSTEM_INFO',
	);

	/** Built-in buttons that have an additional "+" (create) link */
	const PLUS_KEYS = array(
		'items' => 'additem', 'cats' => 'addcat', 'types' => 'addtype', 'fields' => 'addfield',
		'tags' => 'addtag', 'users' => 'adduser', 'groups' => 'addgroup',
	);


	/**
	 * Translated text with HTML entities decoded (e.g. "Types &amp; fields"), as the output is escaped later
	 */
	public static function text($key)
	{
		return html_entity_decode(Text::_($key), ENT_QUOTES, 'UTF-8');
	}


	/**
	 * Is the reviews button usable (reviews enabled in the voting field and user can manage them)
	 */
	public static function reviewsEnabled($perms)
	{
		if (!$perms->CanReviews || !file_exists(JPATH_ADMINISTRATOR . '/components/com_flexicontent/views/reviews'))
		{
			return false;
		}

		$db    = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$field = $db->setQuery('SELECT * FROM #__flexicontent_fields WHERE field_type = ' . $db->Quote('voting'))->loadObject();

		if (!$field)
		{
			return false;
		}

		$item = (object) array('type_id' => 0);
		FlexicontentFields::loadFieldConfig($field, $item);

		return (int) $field->parameters->get('allow_reviews', 0) > 0;
	}


	/**
	 * Can the user create an item in at least one category (evaluated lazily, it loads the item model)
	 */
	protected static function canAddItem()
	{
		static $can = null;

		if ($can === null)
		{
			require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/models/item.php';
			$itemmodel = new FlexicontentModelItem();
			$can = (bool) $itemmodel->getItemAccess()->get('access-create');
		}

		return $can;
	}


	/**
	 * Registry of the built-in buttons
	 *
	 * @return  array  key => [title, icon, link, modal, modal_w, avail, add => null | [link, title, modal, modal_w, allowed]]
	 */
	public static function getRegistry($perms, $params, $user, $showReviews)
	{
		$o = 'index.php?option=com_flexicontent';
		$t = function ($key) { return self::text($key); };

		// Comments (JComments / Komento / custom link)
		$mode = (int) $params->get('comments');
		$comments_ok = ($mode == 1 && $perms->CanComments)
			|| ($mode == 3 && $user->authorise('core.manage', 'com_komento'))
			|| (!$mode && $params->get('comments_admin_link'));
		$comments_missing = ($mode == 1 && !$perms->JComments_Installed);

		switch ($mode)
		{
			case 1:  $c_link = 'index.php?option=com_jcomments&task=view&fog=com_flexicontent'; $c_title = 'JComments'; break;
			case 3:  $c_link = 'index.php?option=com_komento'; $c_title = 'Komento'; break;
			default: $c_link = (string) $params->get('comments_admin_link'); $c_title = $t('FLEXI_COMMENTS'); break;
		}

		if (!$comments_ok)
		{
			$c_link  = '';
			$c_title = $comments_missing ? $t('FLEXI_JCOMMENTS_MISSING') : $t('FLEXI_COMMENTS');
		}

		$plugins = $t('FLEXI_PLUGINS');

		$reg = array(
			'items' => array(
				'title' => $t('FLEXI_ITEMS'), 'icon' => 'icon-file-alt', 'link' => $o . '&view=items', 'avail' => true,
				'add' => array('link' => $o . '&view=types&tmpl=component&layout=typeslist&action=new', 'title' => $t('FLEXI_NEW_ITEM'),
					'modal' => true, 'modal_w' => 1200, 'allowed' => function () { return FlexicontentDashboard::canAddItemPublic(); }),
			),
			'cats' => array(
				'title' => $t('FLEXI_CATEGORIES'), 'icon' => 'icon-folder', 'link' => $o . '&view=categories', 'avail' => (bool) $perms->CanCats,
				'add' => array('link' => $o . '&view=category', 'title' => $t('FLEXI_NEW_CATEGORY'),
					'allowed' => function () { return (bool) FlexicontentHelperPerm::getPermAny('core.create'); }),
			),
			'files' => array(
				'title' => $t('FLEXI_FILEMANAGER'), 'icon' => 'icon-images', 'link' => $o . '&view=filemanager', 'avail' => (bool) $perms->CanFiles,
			),
			'comments' => array(
				'title' => $c_title, 'icon' => 'icon-comment', 'link' => $c_link, 'modal' => true, 'avail' => $comments_ok || $comments_missing,
			),

			'types' => array(
				'title' => $t('FLEXI_TYPES'), 'icon' => 'icon-briefcase', 'link' => $o . '&view=types', 'avail' => (bool) $perms->CanTypes,
				'add' => array('link' => $o . '&view=type', 'title' => $t('FLEXI_NEW_TYPE')),
			),
			'fields' => array(
				'title' => $t('FLEXI_FIELDS'), 'icon' => 'icon-signup', 'link' => $o . '&view=fields', 'avail' => (bool) $perms->CanFields,
				'add' => array('link' => $o . '&view=field', 'title' => $t('FLEXI_NEW_FIELD')),
			),
			'tags' => array(
				'title' => $t('FLEXI_TAGS'), 'icon' => 'icon-tags', 'link' => $o . '&view=tags', 'avail' => (bool) $perms->CanTags,
				'add' => array('link' => $o . '&view=tag', 'title' => $t('FLEXI_NEW_TAG')),
			),

			'templates' => array(
				'title' => $t('FLEXI_TEMPLATES'), 'icon' => 'icon-paint-brush', 'link' => $o . '&view=templates', 'avail' => (bool) $perms->CanTemplates,
			),
			'index' => array(
				'title' => $t('FLEXI_SEARCH_INDEXES'), 'icon' => 'icon-search', 'link' => $o . '&view=search', 'avail' => (bool) $perms->CanIndex,
			),
			'searchlogs' => array(
				'title' => $t('FLEXI_NAV_SD_SEARCH_LOGS'), 'icon' => 'icon-book', 'link' => $o . '&view=search&layout=logs&tmpl=component',
				'modal' => true, 'avail' => (bool) $perms->CanIndex,
			),
			'stats' => array(
				'title' => $t('FLEXI_STATISTICS'), 'icon' => 'icon-chart', 'link' => $o . '&view=stats', 'avail' => (bool) $perms->CanStats,
			),
			'reviews' => array(
				'title' => $t('FLEXI_REVIEWS'), 'icon' => 'icon-comments-2', 'link' => $o . '&view=reviews', 'avail' => (bool) $showReviews,
			),

			'users' => array(
				'title' => $t('FLEXI_USERS'), 'icon' => 'icon-users', 'link' => $o . '&view=users', 'avail' => (bool) $perms->CanAuthors,
				'add' => array('link' => $o . '&task=users.add', 'title' => $t('FLEXI_ADD_USER')),
			),
			'groups' => array(
				'title' => $t('FLEXI_GROUPS'), 'icon' => 'icon-users-cog', 'link' => $o . '&view=groups', 'avail' => (bool) $perms->CanGroups,
				'add' => array('link' => $o . '&task=groups.add', 'title' => $t('FLEXI_ADD_GROUP')),
			),

			'import' => array(
				'title' => $t('FLEXI_IMPORT'), 'icon' => 'icon-upload', 'link' => $o . '&view=import', 'avail' => (bool) $perms->CanImport,
			),
			'plgfields' => array(
				'title' => $plugins . ' - Fields', 'icon' => 'icon-power-cord', 'modal' => true,
				'link' => 'index.php?option=com_plugins&filter_folder=flexicontent_fields', 'avail' => (bool) $perms->CanPlugins,
			),
			'plgsystem' => array(
				'title' => $plugins . ' - System', 'icon' => 'icon-cube', 'modal' => true,
				'link' => 'index.php?option=com_plugins&filter_folder=system&filter_search=flexi', 'avail' => (bool) $perms->CanPlugins,
			),
			'plgflexicontent' => array(
				'title' => $plugins . ' - Flexicontent', 'icon' => 'icon-puzzle', 'modal' => true,
				'link' => 'index.php?option=com_plugins&filter_folder=flexicontent', 'avail' => (bool) $perms->CanPlugins,
			),

			'support' => array(
				'title' => $t('FLEXI_SUPPORT'), 'icon' => 'icon-help', 'modal' => true,
				'link' => (string) $params->get('support_url'), 'avail' => (bool) $params->get('support_url'),
			),
		);

		return $reg;
	}


	/** Public wrapper, so that the registry closures can call the protected method */
	public static function canAddItemPublic()
	{
		return self::canAddItem();
	}


	/**
	 * Default layout (the historical dashboard), honoring the old "hide buttons" parameter
	 * until the layout is saved for the first time
	 */
	public static function getDefaultLayout($params)
	{
		$hidden = FLEXIUtilities::paramToArray($params->get('dashboard_buttons_hide', array()));
		$hidden = array_flip((array) $hidden);

		$sets = array(
			array('editing',  'FLEXI_NAV_SD_CONTENT_EDITING', array('items', 'cats', 'files', 'comments'), ''),
			array('types',    'FLEXI_NAV_SD_TYPES_N_FIELDS',  array('types', 'fields', 'tags'), ''),
			array('waiting',  'FLEXI_DASHBOARD_WAITING_ACTION', array(), 'waiting'),
			array('viewing',  'FLEXI_NAV_SD_CONTENT_VIEWING', array('templates', 'index', 'searchlogs', 'stats', 'reviews'), ''),
			array('users',    'FLEXI_NAV_SD_USERS_N_GROUPS',  array('users', 'groups'), ''),
			array('sysinfo',  'FLEXI_DASHBOARD_SYSTEM_INFO',  array(), 'sysinfo'),
			array('expert',   'FLEXI_NAV_SD_EXPERT_USAGE',    array('import', 'plgfields', 'plgsystem', 'plgflexicontent'), ''),
			array('help',     'FLEXI_HELP',                   array('support'), ''),
		);

		$blocks = array();

		foreach ($sets as $set)
		{
			$btns = array();

			foreach ($set[2] as $key)
			{
				if (isset($hidden[$key]))
				{
					continue;
				}

				$btns[] = array('k' => $key, 't' => '', 'na' => (isset(self::PLUS_KEYS[$key]) && isset($hidden[self::PLUS_KEYS[$key]])) ? 1 : 0);
			}

			$blocks[] = array('id' => $set[0], 'tk' => $set[1], 'sys' => $set[3], 'bg' => '', 'title' => '', 'w' => 4, 'btns' => $btns);
		}

		return array('v' => 2, 'blocks' => $blocks);
	}


	/**
	 * Get the layout: the saved one, or the default one
	 */
	public static function getLayout($params)
	{
		$raw = $params->get(self::PARAM);

		if (is_string($raw) && $raw !== '')
		{
			$layout = self::sanitize($raw);

			if ($layout)
			{
				return $layout;
			}
		}

		return self::getDefaultLayout($params);
	}


	/**
	 * Is the URL of a custom button acceptable (no javascript:, data: ... schemes)
	 */
	public static function cleanUrl($url)
	{
		$url = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $url));

		if ($url === '' || strlen($url) > 1000)
		{
			return '';
		}

		if (preg_match('#^(https?://|mailto:)#i', $url))
		{
			return $url;
		}

		// Any other scheme is refused, relative URLs (index.php?..., /path, ?x, #x) are fine
		if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $url) || strpos($url, '//') === 0)
		{
			return '';
		}

		return $url;
	}


	protected static function cleanText($text, $max)
	{
		$text = trim(strip_tags((string) $text));

		return function_exists('mb_substr') ? mb_substr($text, 0, $max) : substr($text, 0, $max);
	}


	/**
	 * Validate and normalize a layout (JSON string or array), returns null if unusable
	 */
	public static function sanitize($data)
	{
		if (is_string($data))
		{
			$data = json_decode($data, true);
		}

		if (!is_array($data) || !isset($data['blocks']) || !is_array($data['blocks']))
		{
			return null;
		}

		$blocks   = array();
		$seen     = array();   // built-in buttons can be placed only once
		$seen_ids = array();
		$seen_sys = array();
		$factor   = (isset($data['v']) && (int) $data['v'] >= 2) ? 1 : 2;   // old layouts used a grid of 6 columns

		foreach (array_slice($data['blocks'], 0, 30) as $b)
		{
			if (!is_array($b))
			{
				continue;
			}

			$id = isset($b['id']) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', (string) $b['id']) ? (string) $b['id'] : 'b' . substr(md5(uniqid('', true)), 0, 8);
			while (isset($seen_ids[$id])) $id .= 'x';
			$seen_ids[$id] = 1;

			$tk  = isset($b['tk']) && preg_match('/^FLEXI_[A-Z0-9_]{1,80}$/', (string) $b['tk']) ? (string) $b['tk'] : '';
			$w   = isset($b['w']) ? (int) $b['w'] * $factor : 6;
			$w   = in_array($w, self::WIDTHS, true) ? $w : 6;
			$sys = isset($b['sys']) && isset(self::SYS_BLOCKS[$b['sys']]) && !isset($seen_sys[$b['sys']]) ? (string) $b['sys'] : '';

			if ($sys !== '')
			{
				$seen_sys[$sys] = 1;
				$tk = self::SYS_BLOCKS[$sys];
			}

			$btns = array();

			foreach (array_slice($sys === '' && isset($b['btns']) && is_array($b['btns']) ? $b['btns'] : array(), 0, 60) as $btn)
			{
				if (!is_array($btn) || empty($btn['k']))
				{
					continue;
				}

				$k = (string) $btn['k'];

				if ($k === 'custom')
				{
					$url = self::cleanUrl(isset($btn['u']) ? $btn['u'] : '');

					if ($url === '')
					{
						continue;
					}

					$cid   = isset($btn['id']) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', (string) $btn['id']) ? (string) $btn['id'] : 'c' . substr(md5(uniqid('', true)), 0, 8);
					$icon  = isset($btn['i']) && preg_match('/^[a-z0-9 _-]{1,60}$/i', (string) $btn['i']) ? trim((string) $btn['i']) : 'icon-link';
					$g     = isset($btn['g']) && in_array($btn['g'], array('self', 'blank', 'modal'), true) ? $btn['g'] : 'self';
					$title = self::cleanText(isset($btn['t']) ? $btn['t'] : '', 100);

					$btns[] = array('k' => 'custom', 'id' => $cid, 't' => $title !== '' ? $title : $url, 'u' => $url, 'i' => $icon, 'g' => $g);
				}
				elseif (in_array($k, self::KEYS, true) && !isset($seen[$k]))
				{
					$seen[$k] = 1;
					$btns[]   = array('k' => $k, 't' => self::cleanText(isset($btn['t']) ? $btn['t'] : '', 100), 'na' => empty($btn['na']) ? 0 : 1);
				}
			}

			$bg = isset($b['bg']) && preg_match('/^#[0-9a-fA-F]{6}$/', (string) $b['bg']) ? strtolower($b['bg']) : '';

			$blocks[] = array('id' => $id, 'tk' => $tk, 'sys' => $sys, 'bg' => $bg, 'title' => self::cleanText(isset($b['title']) ? $b['title'] : '', 100), 'w' => $w, 'btns' => $btns);
		}

		// The system blocks must always exist
		foreach (self::SYS_BLOCKS as $sys => $tk)
		{
			if (!isset($seen_sys[$sys]))
			{
				$blocks[] = array('id' => $sys, 'tk' => $tk, 'sys' => $sys, 'bg' => '', 'title' => '', 'w' => 4, 'btns' => array());
			}
		}

		return array('v' => 2, 'blocks' => $blocks);
	}


	/**
	 * Store the layout in the component parameters (empty $layout = back to the default layout)
	 */
	public static function save($layout)
	{
		$db  = Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
		$q   = $db->getQuery(true)
			->select($db->qn(array('extension_id', 'params')))
			->from($db->qn('#__extensions'))
			->where($db->qn('type') . ' = ' . $db->q('component'))
			->where($db->qn('element') . ' = ' . $db->q('com_flexicontent'));
		$row = $db->setQuery($q)->loadObject();

		if (!$row)
		{
			return false;
		}

		$reg = new Registry($row->params);
		$reg->set(self::PARAM, $layout ? json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '');

		$db->setQuery($db->getQuery(true)
			->update($db->qn('#__extensions'))
			->set($db->qn('params') . ' = ' . $db->q($reg->toString()))
			->where($db->qn('extension_id') . ' = ' . (int) $row->extension_id)
		)->execute();

		// Component parameters are cached
		try
		{
			Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class)
				->createCacheController('callback', array('defaultgroup' => '_system'))->clean();
		}
		catch (\Throwable $e)
		{
		}

		return true;
	}


	/**
	 * Data for the JS editor: the layout (with the default titles of the built-in items) and the catalog of buttons
	 */
	public static function getEditorData($layout, $registry)
	{
		$catalog = array();

		foreach ($registry as $key => $def)
		{
			$catalog[$key] = array(
				'title' => $def['title'],
				'icon'  => $def['icon'],
				'plus'  => !empty($def['add']),
				'avail' => !empty($def['avail']),
			);
		}

		foreach ($layout['blocks'] as &$block)
		{
			$block['tt'] = $block['tk'] !== '' ? self::text($block['tk']) : '';
		}
		unset($block);

		return array('layout' => $layout, 'catalog' => $catalog);
	}


	/**
	 * Inline style and class of a block with a custom background color (text color chosen for contrast)
	 *
	 * @return  array  [class, style]
	 */
	protected static function bgStyle($bg)
	{
		if (!is_string($bg) || !preg_match('/^#[0-9a-f]{6}$/i', $bg))
		{
			return array('', '');
		}

		$r = hexdec(substr($bg, 1, 2));
		$g = hexdec(substr($bg, 3, 2));
		$b = hexdec(substr($bg, 5, 2));
		$fg = (0.299 * $r + 0.587 * $g + 0.114 * $b) < 140 ? '#ffffff' : '#212529';

		return array(' fc-dash-colored', ' style="--fc-dash-bg:' . $bg . ';--fc-dash-fg:' . $fg . '"');
	}


	/**
	 * Titles of the system blocks (custom title, or the default one)
	 */
	public static function getSysTitles($layout)
	{
		$titles = array();

		foreach (self::SYS_BLOCKS as $sys => $tk)
		{
			$titles[$sys] = self::text($tk);
		}

		foreach ($layout['blocks'] as $block)
		{
			if (!empty($block['sys']) && $block['title'] !== '')
			{
				$titles[$block['sys']] = $block['title'];
			}
		}

		return $titles;
	}


	/**
	 * Render the blocks of the dashboard
	 *
	 * @param   array  $layout    The layout
	 * @param   array  $registry  Registry of the built-in buttons (empty if the buttons must not be displayed)
	 * @param   array  $sys       HTML of the system blocks: sys id => html (complete card)
	 */
	public static function render($layout, $registry, $sys = array())
	{
		$html = '';

		foreach ($layout['blocks'] as $block)
		{
			if (!empty($block['sys']))
			{
				if (!empty($sys[$block['sys']]))
				{
					list($bgClass, $bgStyle) = self::bgStyle(isset($block['bg']) ? $block['bg'] : '');
					$html .= '<div class="fc-dashboard-updates fc-dash-sys-wrap fc-dash-span-' . (int) $block['w'] . $bgClass . '" data-fc-block="'
						. htmlspecialchars($block['id'], ENT_QUOTES, 'UTF-8') . '"' . $bgStyle . '>' . $sys[$block['sys']] . '</div>';
				}

				continue;
			}

			// Without registry (dashboard not ready) the buttons are not displayed
			if (empty($registry))
			{
				continue;
			}

			$buttons = '';

			foreach ($block['btns'] as $btn)
			{
				if ($btn['k'] === 'custom')
				{
					$buttons .= self::renderButton(array(
						'title' => $btn['t'], 'icon' => $btn['i'], 'link' => $btn['u'],
						'modal' => $btn['g'] === 'modal', 'blank' => $btn['g'] === 'blank', 'add' => null,
					));
					continue;
				}

				if (!isset($registry[$btn['k']]) || empty($registry[$btn['k']]['avail']))
				{
					continue;
				}

				$def = $registry[$btn['k']];

				if ($btn['t'] !== '')
				{
					$def['title'] = $btn['t'];
				}

				// The "+" link: only if wanted, and allowed (permission checks are evaluated lazily)
				$add = null;

				if (!empty($def['add']) && empty($btn['na']))
				{
					$add = $def['add'];

					if (isset($add['allowed']) && !call_user_func($add['allowed']))
					{
						$add = null;
					}
				}

				$def['add'] = $add;
				$buttons   .= self::renderButton($def);
			}

			if ($buttons === '')
			{
				continue;
			}

			$title = $block['title'] !== '' ? $block['title'] : ($block['tk'] !== '' ? self::text($block['tk']) : '');

			list($bgClass, $bgStyle) = self::bgStyle(isset($block['bg']) ? $block['bg'] : '');

			$html .= '<fieldset class="fc-board-set card fc-dashboard-card fc-dash-span-' . (int) $block['w'] . $bgClass . '" data-fc-block="' . htmlspecialchars($block['id'], ENT_QUOTES, 'UTF-8') . '"' . $bgStyle . '>'
				. '<h3 class="fc-board-header card-header">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h3>'
				. '<div class="fc-board-set-inner card-body">' . $buttons . '</div>'
				. '</fieldset>';
		}

		return $html;
	}


	/**
	 * onclick code opening an URL in the FLEXIcontent modal dialog
	 */
	protected static function modalOnclick($title, $width = 0)
	{
		$js = "var url = jQuery(this).attr('href'); fc_showDialog(url, 'fc_modal_popup_container', 0, " . (int) $width . ", 0, false, {'title': '"
			. flexicontent_html::encodeHTML($title, 2) . "'}); return false;";

		return ' onclick="' . htmlspecialchars($js, ENT_COMPAT, 'UTF-8') . '"';
	}


	protected static function renderButton(array $b)
	{
		$e     = function ($s) { return htmlspecialchars((string) $s, ENT_COMPAT, 'UTF-8'); };
		$title = $b['title'];
		$icon  = $b['icon'];
		$link  = isset($b['link']) ? $b['link'] : '';
		$modal = !empty($b['modal']);
		$attr  = '';

		if ($link !== '' && $modal)
		{
			$attr = self::modalOnclick($title, isset($b['modal_w']) ? $b['modal_w'] : 0);
		}
		elseif ($link !== '' && !empty($b['blank']))
		{
			$attr = ' target="_blank" rel="noopener noreferrer"';
		}

		// Combined quickicon: main link + "+" link
		if (!empty($b['add']) && $link !== '')
		{
			$add      = $b['add'];
			$add_attr = !empty($add['modal']) ? self::modalOnclick($add['title'], isset($add['modal_w']) ? $add['modal_w'] : 0) : '';

			return '<div class="fc-board-button fc-board-button-combo quick-icons">'
				. '<ul class="quickicon-group list-unstyled d-flex w-100 mb-0">'
				. '<li class="quickicon"><a href="' . $e($link) . '"' . $attr . '>'
				. '<div class="quickicon-info"><div class="quickicon-icon"><div class="' . $e($icon) . '" aria-hidden="true"></div></div></div>'
				. '<div class="quickicon-name d-flex align-items-end">' . $e($title) . '</div>'
				. '</a></li>'
				. '<li class="quickicon-linkadd j-links-link d-flex">'
				. '<a class="d-flex" href="' . $e($add['link']) . '" title="' . $e($add['title']) . '"' . $add_attr . '><span class="icon-plus" aria-hidden="true"></span></a>'
				. '</li></ul></div>';
		}

		return '<span class="fc-board-button"><span class="fc-board-button-inner">'
			. ($link !== '' ? '<a href="' . $e($link) . '" class="fc-board-button-link"' . $attr . '>' : '')
			. '<span class="' . $e($icon) . ' fc-dashboard-icon"></span>'
			. '<span class="fc-board-btn-text' . ($link !== '' ? '' : ' fcdisabled') . '">' . $e($title) . '</span>'
			. ($link !== '' ? '</a>' : '')
			. '</span></span>';
	}
}
