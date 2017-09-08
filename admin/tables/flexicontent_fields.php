<?php
/**
 * @version 1.5 stable $Id: flexicontent_fields.php 1138 2012-02-07 03:01:38Z ggppdk $
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

defined('_JEXEC') or die('Restricted access');

jimport('joomla.access.rules');

class _flexicontent_fields_common extends JTable {
	/**
	 * Get the parent asset id for the record
	 *
	 * @param   JTable   $table  A JTable object for the asset parent.
	 * @param   integer  $id     The id for the asset
	 *
	 * @return  integer  The id of the asset's parent
	 *
	 * @since   11.1
	 */
	protected function __getAssetParentId(JTable $table = null, $id = null)
	{
		$asset = JTable::getInstance('Asset');
		$asset->loadByName('com_flexicontent');
		return $asset->id;
	}
}

// This code has not removed to be an example of how to workaround adding TYPE to method parameters of parent class
if (FLEXI_J30GE) {
	class _flexicontent_fields extends _flexicontent_fields_common {
		protected function _getAssetParentId(JTable $table = null, $id = null) {
			return parent::__getAssetParentId($table, $id);
		}
	}
}

else {
	class _flexicontent_fields extends _flexicontent_fields_common {
		protected function _getAssetParentId($table = null, $id = null) {
			return parent::__getAssetParentId($table, $id);
		}
	}
}


/**
 * FLEXIcontent table class
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class flexicontent_fields extends _flexicontent_fields
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id						= null;

	/** @var int */
	var $asset_id 		= null;

	/** @var string */
	var $field_type		= null;

	/** @var string */
	var $name					= null;

	/** @var string */
	var $label				= null;

	/** @var string */
	var $description	= '';

	/** @var int */
	var $iscore				= 0;

	/** @var int */
	var $issearch			= 1;

	/** @var int */
	var $isadvsearch	= 0;

	/** @var int */
	var $isfilter			= 0;

	/** @var int */
	var $isadvfilter	= 0;

	/** @var int */
	var $untranslatable	= 0;

	/** @var int */
	var $formhidden			= 0;

	/** @var int */
	var $valueseditable	= 0;

	/** @var int */
	var $edithelp			= 2;

	/** @var string */
	var $positions		= '';

	/** @var string */
	var $attribs	 		= null;

	/** @var int */
	var $published		= 0;

	/** @var int */
	var $checked_out	= 0;

	/** @var date */
	var $checked_out_time	= '';

	/** @var int */
	var $access 			= 1;  // Public access

	/** @var int */
	var $ordering 		= 0;

	/** @var boolean */
	var $_trackAssets	= true;

	// Non-table (private) properties
	var $_record_name = 'field';
	var $_title = 'label';
	var $_alias = 'name';
	var $_force_ascii_alias = true;
	var $_allow_underscore = true;

	public function __construct(& $db)
	{
		$this->_records_dbtbl  = 'flexicontent_' . $this->_record_name . 's';
		$this->_records_jtable = 'flexicontent_' . $this->_record_name . 's';
		$this->_NAME = strtoupper($this->_record_name);

		parent::__construct('#__' . $this->_records_dbtbl, 'id', $db);
	}


	// overloaded check function
	public function check()
	{
		$title = $this->_title;
		$alias = $this->_alias;
		$original_alias = $this->$alias;

		// Check if 'title' was not given
		if (trim( $this->$title ) == '')
		{
			$msg = JText::_( 'FLEXI_ADD_' . strtoupper($title) );
			JFactory::getApplication()->enqueueMessage($msg, 'error');
			return false;
		}

		$valid_pattern = $this->_allow_underscore ? '/^[a-z_]+[a-z_0-9-]+$/i' : '/^[a-z]+[a-z0-9-]+$/i' ;
		$is_ascii = $this->iscore;
		$is_ascii = $is_ascii || preg_match($valid_pattern, $this->$alias);
		if (!$is_ascii)
		{
			$bad_alias = $this->$alias;
			$this->$alias = $this->$title;
		}

		// Use 'title' as alias if 'alias' is empty
		if (empty($this->$alias))
		{
			$this->$alias = $this->$title;
		}


		// ***
		// *** Make alias unique, unless it already ascii
		// ***

		if (!$is_ascii)
		{
			// Use record's language or use SITE's default language in case of record's language is ALL (or empty)
			$language = !empty($this->language) && $this->language != '*'
				? $this->language
				: JComponentHelper::getParams('com_languages')->get('site', '*');

			// Make alias safe, also transliterating it - EITHER - if unicode aliases are not enabled - OR - if force ascii alias for current record type is true 
			$this->$alias = $this->stringURLSafe($this->$alias, $language, $this->_force_ascii_alias);

			// Check for empty alias and fallback to using current date
			if (trim(str_replace('-', '', $this->$alias)) == '')
			{
				$this->$alias = JFactory::getDate()->format('Y-m-d-H-i-s');
			}
		}


		// ***
		// *** Make alias unique
		// ***

		$n = 1;
		$possible_alias = $this->$alias;
		while (1)
		{
			$query = 'SELECT id'
				. ' FROM #__' . $this->_records_dbtbl
				. ' WHERE ' . $alias . ' = '.$this->_db->Quote($possible_alias);
			$this->_db->setQuery($query);

			$xid = intval($this->_db->loadResult());
			if ($xid && $xid != intval($this->id))
			{
				$bad_original_alias = $original_alias;
				$possible_alias = $this->$alias . '_' . (++$n);
				continue;
			}
			break;
		}
		$this->$alias = $possible_alias;


		// ***
		// *** Add some warning messages
		// ***

		if (!empty($bad_alias))
		{
			$msg = JText::sprintf('FLEXI_WARN_' . $this->_NAME . '_' . strtoupper($alias) . '_CORRECTED', $bad_alias, $this->$alias);
			JFactory::getApplication()->enqueueMessage($msg, 'notice');
		}

		else if (!empty($bad_original_alias))
		{
			$msg = JText::sprintf('FLEXI_THIS_' . $this->_NAME . '_' . strtoupper($alias) . '_ALREADY_EXIST', $this->name);
			JFactory::getApplication()->enqueueMessage($msg, 'warning');
		}

		return true;
	}


	/**
	 * Method to compute the default name of the asset.
	 * The default name is in the form `table_name.id`
	 * where id is the value of the primary key of the table.
	 *
	 * @return	string
	 * @since	1.6
	 */
	protected function _getAssetName()
	{
		$k = $this->_tbl_key;
		return 'com_flexicontent.' . $this->_record_name . '.' . (int) $this->$k;
	}

	/**
	 * Method to return the title to use for the asset table.
	 *
	 * @return  string
	 *
	 * @since   11.1
	 */
	protected function _getAssetTitle()
	{
		return $this->_title;
	}


	/**
	 * Overloaded bind function.
	 *
	 * @param   array   $array   named array
	 * @param   string  $ignore  An optional array or space separated list of properties
	 *                           to ignore while binding.
	 *
	 * @return  mixed   Null if operation was satisfactory, otherwise returns an error
	 *
	 * @see     JTable:bind
	 * @since   11.1
	 */
	public function bind($array, $ignore = '')
	{
		if (isset($array['attribs']) && is_array($array['attribs'])) {
			$registry = new JRegistry;
			$registry->loadArray($array['attribs']);
			$array['attribs'] = (string)$registry;
		}

		// Bind the rules.
		if (isset($array['rules']) && is_array($array['rules']))
		{
			// (a) prepare the rules, IF for some reason empty group id (=inherit), are not removed from action ACTIONS, we do it manually
			foreach($array['rules'] as $action_name => $identities)
			{
				foreach($identities as $grpid => $val)
				{
					if ($val==="") {
						unset($array['rules'][$action_name][$grpid]);
					}
				}
			}
			
			// (b) Assign the rules
			$rules = new JAccessRules($array['rules']);
			$this->setRules($rules);
		}
		
		return parent::bind($array, $ignore);
	}


	/**
	 * Make given string safe, also transliterating it - EITHER - if unicode aliases are not enabled - OR - if force ascii alias for current record type is true 
	 *
	 * @param   string   $string       The string to make safe
	 * @param   string   $language     The language of the string
	 * @param   boolean  $force_ascii  Whether to force transliteration
	 *
	 * @return  string   A safe string, possibly transliterated
	 *
	 * @see     JTable:bind
	 * @since   11.1
	 */
	public function stringURLSafe($string, $language = '', $force_ascii)
	{
		if (JFactory::getConfig()->get('unicodeslugs') == 1 && !$force_ascii)
		{
			$output = JFilterOutput::stringURLUnicodeSlug($string);
		}
		else
		{
			if ($language === '*' || $language === '')
			{
				$languageParams = JComponentHelper::getParams('com_languages');
				$language = $languageParams->get('site');
			}
			$output = JFilterOutput::stringURLSafe($string, $language);
		}

		return $output;
	}

}