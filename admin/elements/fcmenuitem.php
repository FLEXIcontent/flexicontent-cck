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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

defined('JPATH_PLATFORM') or die;

jimport('cms.html.html');      // JHtml
jimport('cms.html.select');    // JHtmlSelect
jimport('joomla.form.field');  // JFormField

jimport('joomla.form.helper'); // JFormHelper
JFormHelper::loadFieldClass('groupedlist');   // JFormFieldGroupedList

// Import the com_menus helper.
require_once realpath(JPATH_ADMINISTRATOR . '/components/com_menus/helpers/menus.php');

/**
 * Supports an HTML grouped select list of menu item grouped by menu
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.5
 */
class JFormFieldFcMenuitem extends JFormFieldGroupedList
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
	 * @var    string
	 * @since  3.2
	 */
	protected $clientId;

	/**
	 * The language.
	 *
	 * @var    array
	 * @since  3.2
	 */
	protected $language;

	/**
	 * The published status.
	 *
	 * @var    array
	 * @since  3.2
	 */
	protected $published;

	/**
	 * The disabled status.
	 *
	 * @var    array
	 * @since  3.2
	 */
	protected $disable;

	/**
	 * Method to get certain otherwise inaccessible properties from the form field object.
	 *
	 * @param   string  $name  The property name for which to the the value.
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
	 * @param   string  $name   The property name for which to the the value.
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
				$value = (string) $value;
				$this->$name = $value ? explode(',', $value) : array();
				break;

			default:
				parent::__set($name, $value);
		}
	}

	/**
	 * Method to attach a JForm object to the field.
	 *
	 * @param   SimpleXMLElement  $element  The SimpleXMLElement object representing the `<field>` tag for the form field object.
	 * @param   mixed             $value    The form field value to validate.
	 * @param   string            $group    The field name group control value. This acts as an array container for the field.
	 *                                      For example if the field has name="foo" and the group value is set to "bar" then the
	 *                                      full field name would end up being "bar[foo]".
	 *
	 * @return  boolean  True on success.
	 *
	 * @see     JFormField::setup()
	 * @since   3.2
	 */
	public function setup(SimpleXMLElement $element, $value, $group = null)
	{
		$result = parent::setup($element, $value, $group);

		if ($result === true)
		{
			$this->menuType  = (string) $this->element['menu_type'];
			$this->clientId  = (int) $this->element['client_id'];
			$this->published = $this->element['published'] ? explode(',', (string) $this->element['published']) : array();
			$this->disable   = $this->element['disable'] ? explode(',', (string) $this->element['disable']) : array();
			$this->language  = $this->element['language'] ? explode(',', (string) $this->element['language']) : array();
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
		static $comp_items = array();
		
		$menuType = $this->menuType;
		$component    = empty($this->element['component']) ? false : (string)$this->element['component'];
		$link_filters = empty($this->element['link_filters']) ? false : explode('%%', (string)$this->element['link_filters']);
		
		// Get the menu items.
		if ( !$menuType && $component && isset($comp_items[$component]) )
		{
			$items = & $comp_items[$component];
		}
		else
		{
			//$items = MenusHelper::getMenuLinks($menuType, 0, 0, $this->published, $this->language);
			$items = $this->getMenuLinks($menuType, 0, 0, $this->published, $this->language);
			
			// Cache component menu items
			if (!$menuType && $component)
			{
				$filter_text = 'option='.$component;
				$comp_items[$component] = array();
				foreach ($items as $menu)
				{
					$_menu = new stdClass();
					foreach ($menu as $prop_name => $prop_val) {
						if (!is_object($prop_val) && !is_array($prop_val)) $_menu->$prop_name = $prop_val;
					}
					$_menu->links = array();
					foreach ($menu->links as $link)
					{
						if (strstr($link->url, $filter_text) === false) continue;
						$_menu->links[] = clone $link;
					}
					$comp_items[$component][] = $_menu;
				}
				$items = & $comp_items[$component];
			}
		}
		
		// Build group for a specific menu type.
		$groups = array();
		if ($menuType)
		{
			// Initialize the group.
			$groups[$menuType] = array();

			// Build the options array.
			foreach ($items as $link)
			{
				$skip = 0;
				if ($link_filters && $this->value != $link->value) foreach($link_filters as $filter_text)
				{
					if ($filter_text[0]=='!') {
						if (strstr($link->url, substr($filter_text, 1)) !== false) { $skip=1; break; }
					} else {
						if (strstr($link->url, $filter_text) === false) { $skip=1; break; }
					}
				}
				if ($skip) continue;
				
				$levelPrefix = str_repeat('- ', max(0, $link->level - 1));
				$groups[$menuType][] = JHtml::_('select.option',
								$link->value, $levelPrefix . $link->text,
								'value',
								'text',
								in_array($link->type, $this->disable)
							);
			}
		}
		// Build groups for all menu types.
		else
		{
			// Build the groups arrays.
			foreach ($items as $menu)
			{
				// Initialize the group.
				$groups[$menu->menutype] = array();

				// Build the options array.
				foreach ($menu->links as $link)
				{
					$skip = 0;
					if ($link_filters && $this->value != $link->value) foreach($link_filters as $filter_text)  {
						if ($filter_text[0]=='!') {
							if (strstr($link->url, substr($filter_text, 1)) !== false) { $skip=1; break; }
						} else {
							if (strstr($link->url, $filter_text) === false) { $skip=1; break; }
						}
					}
					if ($skip) continue;
					
					$levelPrefix = str_repeat('- ', $link->level - 1);
					$groups[$menu->menutype][] = JHtml::_(
						'select.option', $link->value, $levelPrefix . $link->text, 'value', 'text',
						in_array($link->type, $this->disable)
					);
				}
				if ( !count($groups[$menu->menutype]) )  unset($groups[$menu->menutype]);  // Skip menu with no items
			}
		}

		// Merge any additional groups in the XML definition.
		$groups = array_merge(parent::getGroups(), $groups);

		return $groups;
	}

	public static function getMenuLinks($menuType = null, $parentId = 0, $mode = 0, $published = array(), $languages = array())
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select('DISTINCT a.id AS value, 
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
				  a.lft')
			->from('#__menu AS a');

		if (JLanguageMultilang::isEnabled())
		{
			$query->select('l.title AS language_title, l.image as language_image')
				->join('LEFT', $db->quoteName('#__languages') . ' AS l ON l.lang_code = a.language');
		}

		// Filter by the type
		if ($menuType)
		{
			$query->where('(a.menutype = ' . $db->quote($menuType) . ' OR a.parent_id = 0)');
		}

		if ($parentId)
		{
			if ($mode == 2)
			{
				// Prevent the parent and children from showing.
				$query->join('LEFT', '#__menu AS p ON p.id = ' . (int) $parentId)
					->where('(a.lft <= p.lft OR a.rgt >= p.rgt)');
			}
		}

		if (!empty($languages))
		{
			if (is_array($languages))
			{
				$languages = '(' . implode(',', array_map(array($db, 'quote'), $languages)) . ')';
			}

			$query->where('a.language IN ' . $languages);
		}

		if (!empty($published))
		{
			if (is_array($published))
			{
				$published = '(' . implode(',', $published) . ')';
			}

			$query->where('a.published IN ' . $published);
		}

		$query->where('a.published != -2');

		$query->order('a.lft ASC');

		// Get the options.
		$db->setQuery($query);

		try
		{
			$links = $db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			JError::raiseWarning(500, $e->getMessage());

			return false;
		}

		if (empty($menuType))
		{
			// If the menutype is empty, group the items by menutype.
			$query->clear()
				->select('*')
				->from('#__menu_types')
				->where('menutype <> ' . $db->quote(''))
				->order('title, menutype');
			$db->setQuery($query);

			try
			{
				$menuTypes = $db->loadObjectList();
			}
			catch (RuntimeException $e)
			{
				JError::raiseWarning(500, $e->getMessage());

				return false;
			}

			// Create a reverse lookup and aggregate the links.
			$rlu = array();

			foreach ($menuTypes as &$type)
			{
				$rlu[$type->menutype] = & $type;
				$type->links = array();
			}

			// Loop through the list of menu links.
			foreach ($links as &$link)
			{
				if (isset($rlu[$link->menutype]))
				{
					$rlu[$link->menutype]->links[] = & $link;

					// Cleanup garbage.
					unset($link->menutype);
				}
			}

			return $menuTypes;
		}
		else
		{
			return $links;
		}
	}
}
