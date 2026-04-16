<?php
/**
 * @version 1.5 stable $Id: types.php 1260 2012-04-25 17:43:21Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * Compatibility: Joomla 4.x, 5.x, 6.x
 */

// J4+ : _JEXEC remplace JPATH_PLATFORM (supprimé en J6)
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\GroupedlistField;  // Remplace JFormFieldGroupedList (alias J3 supprimé en J6)
use Joomla\CMS\HTML\HTMLHelper;              // Remplace JHtml (alias J3 supprimé en J6)
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Log\Log;                      // Remplace JError::raiseWarning() (supprimé en J4)

// jimport() supprimé en J6 → les use statements ci-dessus suffisent
// require_once com_menus helper supprimé en J4 → getMenuLinks() est réécrit localement

/**
 * Supports an HTML grouped select list of menu items grouped by menu.
 *
 * @package     Joomla
 * @subpackage  FLEXIcontent
 * @since       1.5
 */
class JFormFieldFcMenuitem extends GroupedlistField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.6
	 */
	public $type = 'FcMenuitem';

	/**
	 * The menu type.
	 *
	 * @var    string
	 * @since  3.2
	 */
	protected $menuType;

	/**
	 * The client id.
	 *
	 * @var    int
	 * @since  3.2
	 */
	protected $clientId;

	/**
	 * The language.
	 *
	 * @var    array
	 * @since  3.2
	 */
	protected $language = [];

	/**
	 * The published status.
	 *
	 * @var    array
	 * @since  3.2
	 */
	protected $published = [];

	/**
	 * The disabled status.
	 *
	 * @var    array
	 * @since  3.2
	 */
	protected $disable = [];

	/**
	 * Method to get certain otherwise inaccessible properties from the form field object.
	 *
	 * @param   string  $name  The property name for which to get the value.
	 *
	 * @return  mixed  The property value or null.
	 *
	 * @since   3.2
	 */
	public function __get($name)
	{
		switch ($name)
		{
			case 'menuType':
			case 'clientId':
			case 'language':
			case 'published':
			case 'disable':
				return $this->$name;
		}

		return parent::__get($name);
	}

	/**
	 * Method to set certain otherwise inaccessible properties of the form field object.
	 *
	 * @param   string  $name   The property name for which to set the value.
	 * @param   mixed   $value  The value of the property.
	 *
	 * @return  void
	 *
	 * @since   3.2
	 */
	public function __set($name, $value)
	{
		switch ($name)
		{
			case 'menuType':
				$this->menuType = (string) $value;
				break;

			case 'clientId':
				$this->clientId = (int) $value;
				break;

			case 'language':
			case 'published':
			case 'disable':
				$value       = (string) $value;
				$this->$name = $value ? explode(',', $value) : [];
				break;

			default:
				parent::__set($name, $value);
		}
	}

	/**
	 * Method to attach a \Joomla\CMS\Form\Form object to the field.
	 *
	 * @param   SimpleXMLElement  $element  The SimpleXMLElement object representing the `<field>` tag.
	 * @param   mixed             $value    The form field value to validate.
	 * @param   string|null       $group    The field name group control value.
	 *
	 * @return  boolean  True on success.
	 *
	 * @see     \Joomla\CMS\Form\FormField::setup()
	 * @since   3.2
	 */
	public function setup(SimpleXMLElement $element, $value, $group = null)
	{
		$result = parent::setup($element, $value, $group);

		if ($result === true)
		{
			$this->menuType  = (string) $this->element['menu_type'];
			$this->clientId  = (int) $this->element['client_id'];
			$this->published = $this->element['published'] ? explode(',', (string) $this->element['published']) : [];
			$this->disable   = $this->element['disable']   ? explode(',', (string) $this->element['disable'])   : [];
			$this->language  = $this->element['language']  ? explode(',', (string) $this->element['language'])  : [];
		}

		return $result;
	}

	/**
	 * Method to get the field option groups.
	 *
	 * @return  array  The field option objects as a nested array in groups.
	 *
	 * @since   1.6
	 */
	protected function getGroups()
	{
		static $comp_items = [];

		$menuType     = $this->menuType;
		$component    = empty($this->element['component'])    ? false : (string) $this->element['component'];
		$link_filters = empty($this->element['link_filters']) ? false : explode('%%', (string) $this->element['link_filters']);

		// Use cache for component menu items when no specific menuType is set
		if (!$menuType && $component && isset($comp_items[$component]))
		{
			$items = &$comp_items[$component];
		}
		else
		{
			$items = static::getMenuLinks($menuType, 0, 0, $this->published, $this->language);

			// Build and cache component-filtered menu items
			if (!$menuType && $component)
			{
				$filter_text           = 'option=' . $component;
				$comp_items[$component] = [];

				foreach ($items as $menu)
				{
					$_menu = new stdClass();

					foreach ($menu as $prop_name => $prop_val)
					{
						if (!is_object($prop_val) && !is_array($prop_val))
						{
							$_menu->$prop_name = $prop_val;
						}
					}

					$_menu->links = [];

					foreach ($menu->links as $link)
					{
						if (strpos($link->url, $filter_text) === false)
						{
							continue;
						}

						$_menu->links[] = clone $link;
					}

					$comp_items[$component][] = $_menu;
				}

				$items = &$comp_items[$component];
			}
		}

		$groups = [];

		if ($menuType)
		{
			// Build group for a specific menu type
			$groups[$menuType] = [];

			foreach ($items as $link)
			{
				if ($this->shouldSkipLink($link, $link_filters))
				{
					continue;
				}

				$levelPrefix          = str_repeat('- ', max(0, $link->level - 1));
				$groups[$menuType][] = HTMLHelper::_(
					'select.option',
					$link->value,
					$levelPrefix . $link->text,
					'value',
					'text',
					in_array($link->type, $this->disable)
				);
			}
		}
		else
		{
			// Build groups for all menu types
			foreach ($items as $menu)
			{
				$groups[$menu->menutype] = [];

				foreach ($menu->links as $link)
				{
					if ($this->shouldSkipLink($link, $link_filters))
					{
						continue;
					}

					$levelPrefix               = str_repeat('- ', max(0, $link->level - 1));
					$groups[$menu->menutype][] = HTMLHelper::_(
						'select.option',
						$link->value,
						$levelPrefix . $link->text,
						'value',
						'text',
						in_array($link->type, $this->disable)
					);
				}

				// Remove menus with no matching items
				if (empty($groups[$menu->menutype]))
				{
					unset($groups[$menu->menutype]);
				}
			}
		}

		// Merge any additional groups defined in the XML
		return array_merge(parent::getGroups(), $groups);
	}

	/**
	 * Helper method to determine whether a link should be skipped based on filters.
	 *
	 * @param   object        $link          The menu link object.
	 * @param   array|false   $link_filters  Array of filter strings, or false if no filters.
	 *
	 * @return  bool  True if the link should be skipped.
	 */
	protected function shouldSkipLink(object $link, $link_filters): bool
	{
		if (!$link_filters || $this->value == $link->value)
		{
			return false;
		}

		foreach ($link_filters as $filter_text)
		{
			if ($filter_text[0] === '!')
			{
				// Exclude links matching this pattern
				if (strpos($link->url, substr($filter_text, 1)) !== false)
				{
					return true;
				}
			}
			else
			{
				// Exclude links NOT matching this pattern
				if (strpos($link->url, $filter_text) === false)
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get menu links from the database.
	 *
	 * Replaces the removed MenusHelper::getMenuLinks() from com_menus (removed in J4).
	 *
	 * @param   string|null  $menuType   The menu type to filter on, or null for all menus.
	 * @param   int          $parentId   Parent item ID (used with $mode = 2).
	 * @param   int          $mode       Mode flag (2 = exclude parent and its children).
	 * @param   array        $published  Array of published states to include.
	 * @param   array        $languages  Array of language codes to filter on.
	 *
	 * @return  array|false  Array of menu items/types, or false on error.
	 */
	public static function getMenuLinks(
		?string $menuType = null,
		int $parentId = 0,
		int $mode = 0,
		array $published = [],
		array $languages = []
	)
	{
		$db    = Factory::getDbo();
		$query = $db->getQuery(true)
			->select(
				'DISTINCT a.id AS value,
				a.title AS text,
				a.link AS url,
				a.alias,
				a.level,
				a.menutype,
				a.type,
				a.published,
				a.template_style_id,
				a.checked_out,
				a.language,
				a.lft'
			)
			->from($db->quoteName('#__menu', 'a'));

		if (Multilanguage::isEnabled())
		{
			$query->select('l.title AS language_title, l.image AS language_image')
				->join(
					'LEFT',
					$db->quoteName('#__languages', 'l') . ' ON l.lang_code = a.language'
				);
		}

		if ($menuType)
		{
			$query->where(
				'(a.menutype = ' . $db->quote($menuType) . ' OR a.parent_id = 0)'
			);
		}

		if ($parentId && $mode === 2)
		{
			$query->join('LEFT', $db->quoteName('#__menu', 'p') . ' ON p.id = ' . (int) $parentId)
				->where('(a.lft <= p.lft OR a.rgt >= p.rgt)');
		}

		if (!empty($languages))
		{
			$quotedLanguages = '(' . implode(',', array_map([$db, 'quote'], $languages)) . ')';
			$query->where('a.language IN ' . $quotedLanguages);
		}

		if (!empty($published))
		{
			$publishedList = '(' . implode(',', array_map('intval', $published)) . ')';
			$query->where('a.published IN ' . $publishedList);
		}

		$query->where('a.published != -2')
			->order('a.lft ASC');

		$db->setQuery($query);

		try
		{
			$links = $db->loadObjectList();
		}
		catch (\RuntimeException $e)
		{
			// J4+ : Log::add() remplace JError::raiseWarning() (supprimé en J4)
			Log::add($e->getMessage(), Log::WARNING, 'flexicontent');

			return false;
		}

		// If no specific menuType requested, group links by menu type
		if (empty($menuType))
		{
			$query->clear()
				->select('*')
				->from($db->quoteName('#__menu_types'))
				->where('menutype <> ' . $db->quote(''))
				->order('title, menutype');

			$db->setQuery($query);

			try
			{
				$menuTypes = $db->loadObjectList();
			}
			catch (\RuntimeException $e)
			{
				Log::add($e->getMessage(), Log::WARNING, 'flexicontent');

				return false;
			}

			// Build a reverse lookup by menutype and initialise links array
			$rlu = [];

			foreach ($menuTypes as &$type)
			{
				$rlu[$type->menutype] = &$type;
				$type->links          = [];
			}

			unset($type);

			// Assign each link to its menu type group
			foreach ($links as &$link)
			{
				if (isset($rlu[$link->menutype]))
				{
					$rlu[$link->menutype]->links[] = &$link;
					unset($link->menutype); // Cleanup: menutype now lives on the parent object
				}
			}

			unset($link);

			return $menuTypes;
		}

		return $links;
	}
}