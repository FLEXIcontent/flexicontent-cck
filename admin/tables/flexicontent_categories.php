<?php
/**
* @version		$Id: flexicontent_categories.php 171 2010-03-20 00:44:02Z emmanuel.danan $
* @package		Joomla.Framework
* @subpackage	Table
* @copyright	Copyright (C) 2005 - 2008 Open Source Matters. All rights reserved.
* @license		GNU/GPL, see LICENSE.php
* Joomla! is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/

// Check to ensure this file is within the rest of the framework
defined('_JEXEC') or die('Restricted access');

/**
 * Category table
 *
 * @package 	Joomla.Framework
 * @subpackage	Table
 * @since		1.0
 */
jimport('joomla.database.tablenested');
jimport('joomla.access.rules');
use Joomla\String\StringHelper;

class _flexicontent_categories_common extends JTableNested {
	protected function __getAssetParentId(JTable $table = null, $id = null)
	{
		// Initialise variables.
		$assetId = null;
		$db		= $this->getDbo();

		if ($this->parent_id > 1) {
			// This is a category under a category.
			// Build the query to get the asset id for the parent category.
			$query	= $db->getQuery(true);
			$query->select('asset_id');
			$query->from('#__categories');
			$query->where('id = '.(int) $this->parent_id);

			// Get the asset id from the database.
			$db->setQuery($query);
			if ($result = $db->loadResult()) {
				$assetId = (int) $result;
			}
		
		} else {
			// This is root category.
			// Build the query to get the asset id of component.
			$query	= $db->getQuery(true);
			$query->select('id');
			$query->from('#__assets');
			$query->where('name= "com_flexicontent"');

			// Get the asset id from the database.
			$db->setQuery($query);
			if ($result = $db->loadResult()) {
				$assetId = (int) $result;
			}
		}

		// Return the asset id.
		if ($assetId) {
			return $assetId;
		} else {
			return parent::_getAssetParentId($table, $id);
		}
	}
}

if (FLEXI_J30GE) {
	class _flexicontent_categories extends _flexicontent_categories_common {
		protected function _getAssetParentId(JTable $table = null, $id = null) {
			return parent::__getAssetParentId($table, $id);
		}
	}
}

else {
	class _flexicontent_categories extends _flexicontent_categories_common {
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
class flexicontent_categories extends _flexicontent_categories
{
	/** @var int Primary key */
	var $id					= null;
	/** @var int */
	var $asset_id			= null;
	var $parent_id			= null;
	var $lft			= null;
	var $rgt			= null;
	var $level			= null;
	/** @var string */
	var $path			= null;
	var $extension= 'com_content';
	/** @var string The menu title for the category (a short name)*/
	var $title				= null;
	/** @var string The the alias for the category*/
	var $alias				= null;
	var $note			= null;
	/** @var string */
	var $description		= null;
	/** @var boolean */
	var $published			= null;
	/** @var boolean */
	var $checked_out		= 0;
	/** @var time */
	var $checked_out_time	= 0;
	/** @var int */
	//var $ordering			= null;
	/** @var int */
	var $access				= null;
	/** @var string */
	var $params				= null;
	var $metadesc			= null;
	var $metakey			= null;
	var $metadata			= null;
	var $created_user_id		= null;
	var $created_time		= null;
	var $modified_user_id		= null;
	var $modified_time		= null;
	var $hits			= null;
	var $language			= null;

	/**
	* @param database A database connector object
	*/
	function __construct(& $db) {
		$this->extension = 'com_content';
		parent::__construct('#__categories', 'id', $db);
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
		// we use 'com_content' instead of $this->extension which contains 'com_flexicontent'
		$k = $this->_tbl_key;
		return 'com_content.category.'.(int) $this->$k;
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
		return $this->title;
	}

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
	// see (above) parent class method: _getAssetParentId($table = null, $id = null)
	
	
	/**
	 * Overloaded check function
	 *
	 * @access public
	 * @return boolean
	 * @see JTable::check
	 * @since 1.5
	 */
	function check()
	{
		// check for empty title
		if (trim( $this->title ) == '') {
			$this->setError(JText::sprintf( 'must contain a title', JText::_( 'FLEXI_Category' ) ));
			return false;
		}
		
		// check for empty alias
		if(empty($this->alias)) {
			$this->alias = $this->title;
		}
		
		// transliterate alias
		if ( !JFactory::getConfig()->get('unicodeslugs') )
		{
			$this->alias = $this->transliterate($this->alias);
		}
		
		// make alias safe
		$this->alias = JApplicationHelper::stringURLSafe($this->alias);
		if (trim(str_replace('-', '', $this->alias)) == '')
		{
			$this->alias = JFactory::getDate()->format('Y-m-d-H-i-s');
		}
		
		
		global $globalcats;
		
		// check for valid parent category
		if ( $this->id && in_array($this->parent_id, $globalcats[$this->id]->descendantsarray) ) {
			$this->setError( 'Parent of category is not allowed, you cannot move the category to be a child of itself' );
			return false;
		}

		// check for existing name
		/*$query = 'SELECT id'
		. ' FROM #__categories c'
		. ' WHERE c.extension="'.FLEXI_CAT_EXTENSION.'" AND title = '.$this->_db->Quote($this->title)
		. ' AND lft>=' . FLEXI_LFT_CATEGORY . ' AND rgt<=' . FLEXI_RGT_CATEGORY
		;
		$this->_db->setQuery( $query );

		$xid = intval( $this->_db->loadResult() );
		if ($xid && $xid != intval( $this->id )) {
			$this->_error = JText::sprintf( 'WARNNAMETRYAGAIN', JText::_( 'FLEXI_Category' ) );
			return false;
		}*/
		
		return true;
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
		$this->extension = 'com_content';
		
		if (isset($array['params']) && is_array($array['params'])) {
			$registry = new JRegistry;
			$registry->loadArray($array['params']);
			$array['params'] = (string)$registry;
		}

		if (isset($array['metadata']) && is_array($array['metadata'])) {
			$registry = new JRegistry;
			$registry->loadArray($array['metadata']);
			$array['metadata'] = (string)$registry;
		}

		// Bind the rules.
		if (isset($array['rules']) && is_array($array['rules'])) {
		
			// Make sure that empty group ids (=inherit) are removed from actions, (not needed this should already be done)
			foreach($array['rules'] as $action_name => $identities) {
				foreach($identities as $grpid => $val) {
					if ($val==="") {
						unset($array['rules'][$action_name][$grpid]);
					}
				}
			}
			$rules = new JAccessRules($array['rules']);
			$this->setRules($rules);
		}
		
		return parent::bind($array, $ignore);
	}
	
	/**
	 * Overriden JTable::store to set created/modified and user id.
	 *
	 * @param   boolean  $updateNulls  True to update fields even if they are null.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   11.1
	 */
	public function store($updateNulls = false)
	{
		$date	= JFactory::getDate();
		$user	= JFactory::getUser();

		if ($this->id) {
			// Existing category
			$this->modified_time	= FLEXI_J16GE ? $date->toSql() : $date->toMySQL();
			$this->modified_user_id	= $user->get('id');
			$is_new = false;
		} else {
			// New category
			$this->created_time		= FLEXI_J16GE ? $date->toSql() : $date->toMySQL();
			$this->created_user_id	= $user->get('id');
			$is_new = true;
		}
		
		// Verify that the alias is unique
		$table = JTable::getInstance('flexicontent_categories','');
		if ($table->load(array('alias'=>$this->alias,'parent_id'=>$this->parent_id,'extension'=>$this->extension)) && ($table->id != $this->id || $this->id==0)) {

			$this->setError(JText::_('JLIB_DATABASE_ERROR_CATEGORY_UNIQUE_ALIAS'));
			return false;
		}
		
		// NOT NEEDED handle by parent::store()
		/*if (isset($this->asset_id)) {
			$asset	= JTable::getInstance('Asset');
			if (!$asset->load($this->asset_id)) {
				$name = $this->_getAssetName();
				$asset->loadByName($name);
			
				$query = $this->_db->getQuery(true);
				$query->update($this->_db->quoteName($this->_tbl));
				$query->set('asset_id = '.(int)$asset->id);
				$query->where('id = '.(int) $this->id);
				$this->_db->setQuery($query);
				
				if (!$this->_db->execute()) {
					$e = new JException(JText::sprintf('JLIB_DATABASE_ERROR_STORE_FAILED_UPDATE_ASSET_ID', $this->_db->getErrorMsg()));
					$this->setError($e);
					return false;
				}
			}
		}*/
		
		$result = parent::store($updateNulls);
			
		// Force com_content extension for new categories ... and for existing ones, just to make sure ...
		if ($is_new || !$is_new)
		{
			$query 	= 'UPDATE #__categories'
				. ' SET extension = "com_content" '
				. ' WHERE id = ' . (int)$this->id;
				;
			$this->_db->setQuery($query);
			$this->_db->execute();
			if (!$this->_db->execute()) {
				$e = new JException(JText::sprintf($this->_db->getErrorMsg()));
				$this->setError($e);
				return false;
			}
		}
		
		return $result;
	}

	/**
	 * Original code form: Phoca International Alias Plugin for Joomla 1.5
	 * TODO: move to helper file or to common class parent
	 *
	 * This method processes a string and replaces all accented UTF-8 characters by unaccented
	 * ASCII-7 "equivalents", whitespaces are replaced by hyphens and the string is lowercased.
	 */
	function transliterate($string)
	{
		$langFrom    = array();
		$langTo      = array();
		
		$language = $this->language != '*' ?
			$this->language :
			JComponentHelper::getParams('com_languages')->get('site', '*') ;

		if ($language == '*') return $string;
		
		// BULGARIAN
		if ($language == 'bg-BG') {
			$bgLangFrom = array('А', 'а', 'Б', 'б', 'В', 'в', 'Г', 'г', 'Д', 'д', 'Е', 'е', 'Ж', 'ж', 'З', 'з', 'И', 'и', 'Й', 'й', 'К', 'к', 'Л', 'л', 'М', 'м', 'Н', 'н', 'О', 'о', 'П', 'п', 'Р', 'р', 'С', 'с', 'Т', 'т', 'У', 'у', 'Ф', 'ф', 'Х', 'х', 'Ц', 'ц', 'Ч', 'ч', 'Ш', 'ш', 'Щ', 'щ', 'Ъ', 'ъ', 'Ьо', 'ьо', 'Ю', 'ю', 'Я', 'я');
			$bgLangTo   = array('A', 'a', 'B', 'b', 'V', 'v', 'G', 'g', 'D', 'd', 'E', 'e', 'Zh', 'zh', 'Z', 'z', 'I', 'i', 'J', 'j', 'K', 'k', 'L', 'l', 'M', 'm', 'N', 'n', 'O', 'o', 'P', 'p', 'R', 'r', 'S', 's', 'T', 't', 'U', 'u', 'F', 'f', 'H', 'h', 'C', 'c', 'Ch', 'ch', 'Sh', 'sh', 'Sht', 'sht', 'Y', 'y', 'Io', 'io', 'Ju', 'ju', 'Ja', 'ja');
			$langFrom   = array_merge ($langFrom, $bgLangFrom);
			$langTo     = array_merge ($langTo, $bgLangTo);
		}
		
		// CZECH
		if ($language == 'cz-CZ') {
			$czLangFrom = array('á','č','ď','é','ě','í','ň','ó','ř','š','ť','ú','ů','ý','ž','Á','Č','Ď','É','Ě','Í','Ň','Ó','Ř','Š','Ť','Ú','Ů','Ý','Ž');
			$czLangTo   = array('a','c','d','e','e','i','n','o','r','s','t','u','u','y','z','a','c','d','e','e','i','ň','o','r','s','t','u','u','y','z');
			$langFrom   = array_merge ($langFrom, $czLangFrom);
			$langTo     = array_merge ($langTo, $czLangTo);
		}
		
		// CROATIAN
		if ($language == 'hr-HR' || $language == 'hr-BA') {
			$hrLangFrom = array('č','ć','đ','š','ž','Č','Ć','Đ','Š','Ž');
			$hrLangTo   = array('c','c','d','s','z','c','c','d','s','z');
			$langFrom   = array_merge ($langFrom, $hrLangFrom);
			$langTo     = array_merge ($langTo, $hrLangTo);
		}
		
		// GREEK
		if ($language == 'el-GR') {
			$grLangFrom = array('α', 'β', 'γ', 'δ', 'ε', 'ζ', 'η', 'θ',  'η', 'ι', 'κ', 'λ', 'μ', 'ν', 'ξ',  'ο', 'π', 'ρ', 'σ', 'τ', 'υ', 'φ', 'χ', 'ψ',  'ω', 'Α', 'Β', 'Γ', 'Δ', 'Ε', 'Ζ', 'Η', 'Θ',  'Ι', 'Κ', 'Λ', 'Μ', 'Ν', 'Ξ',  'Ο', 'Π', 'Ρ', 'Σ', 'Τ', 'Υ', 'Φ', 'Χ', 'Ψ',  'Ω', 'Ά', 'Έ', 'Ή', 'Ί', 'Ύ', 'Ό', 'Ώ', 'ά', 'έ', 'ή', 'ί', 'ύ', 'ό', 'ώ', 'ΰ', 'ΐ', 'ϋ', 'ϊ', 'ς', '«', '»' );
			$grLangTo   = array('a', 'b', 'g', 'd', 'e', 'z', 'h', 'th', 'i', 'i', 'k', 'l', 'm', 'n', 'ks', 'o', 'p', 'r', 's', 't', 'u', 'f', 'x', 'ps', 'o', 'A', 'B', 'G', 'D', 'E', 'Z', 'I', 'Th', 'I', 'K', 'L', 'M', 'N', 'Ks', 'O', 'P', 'R', 'S', 'T', 'Y', 'F', 'X', 'Ps', 'O', 'A', 'E', 'I', 'I', 'U', 'O', 'O', 'a', 'e', 'i', 'i', 'u', 'o', 'o', 'u', 'i', 'u', 'i', 's', '_', '_' );
			$langFrom   = array_merge ($langFrom, $grLangFrom);
			$langTo     = array_merge ($langTo, $grLangTo);
		}
		
		// HUNGARIAN
		if ($language == 'hu-HU') {
			$huLangFrom = array('á','é','ë','í','ó','ö','ő','ú','ü','ű','Á','É','Ë','Í','Ó','Ö','Ő','Ú','Ü','Ű');
			$huLangTo   = array('a','e','e','i','o','o','o','u','u','u','a','e','e','i','o','o','o','u','u','u');
			$langFrom   = array_merge ($langFrom, $huLangFrom);
			$langTo     = array_merge ($langTo, $huLangTo);
		}
		
		// POLISH
		if ($language == 'pl-PL') {
			$plLangFrom = array('ą','ć','ę','ł','ń','ó','ś','ź','ż','Ą','Ć','Ę','Ł','Ń','Ó','Ś','Ź','Ż');
			$plLangTo   = array('a','c','e','l','n','o','s','z','z','a','c','e','l','n','o','s','z','z');
			$langFrom   = array_merge ($langFrom, $plLangFrom);
			$langTo     = array_merge ($langTo, $plLangTo);
		}
		
		// RUSSIAN
		if ($language == 'ru-RU') {
			$ruLangFrom = array('А', 'а', 'Б', 'б', 'В', 'в', 'Г', 'г', 'Д', 'д', 'Е', 'е', 'Ё', 'ё', 'Ж', 'ж', 'З', 'з', 'И', 'и', 'Й', 'й', 'К', 'к', 'Л', 'л', 'М', 'м', 'Н', 'н', 'О', 'о', 'П', 'п', 'Р', 'р', 'С', 'с', 'Т', 'т', 'У', 'у', 'Ф', 'ф', 'Х', 'х', 'Ц', 'ц', 'Ч', 'ч', 'Ш', 'ш', 'Щ', 'щ', 'Ъ', 'ъ', 'Ы', 'ы', 'Ь', 'ь', 'Э', 'э', 'Ю', 'ю', 'Я', 'я');
			$ruLangTo   = array('A', 'a', 'B', 'b', 'V', 'v', 'G', 'g', 'D', 'd', 'E', 'e', 'Jo', 'jo', 'Zh', 'zh', 'Z', 'z', 'I', 'i', 'J', 'j', 'K', 'k', 'L', 'l', 'M', 'm', 'N', 'n', 'O', 'o', 'P', 'p', 'R', 'r', 'S', 's', 'T', 't', 'U', 'u', 'F', 'f', 'H', 'h', 'C', 'c', 'Ch', 'ch', 'Sh', 'sh', 'Shch', 'shch', '', '', 'Y', 'y', '', '', 'E', 'e', 'Yu', 'yu', 'Ya', 'ya');
			$langFrom   = array_merge ($langFrom, $ruLangFrom);
			$langTo     = array_merge ($langTo, $ruLangTo);
		}
		
		// SLOVAK
		if ($language == 'sk-SK') {
			$skLangFrom = array('á','ä','č','ď','é','í','ľ','ĺ','ň','ó','ô','ŕ','š','ť','ú','ý','ž','Á','Ä','Č','Ď','É','Í','Ľ','Ĺ','Ň','Ó','Ô','Ŕ','Š','Ť','Ú','Ý','Ž');
			$skLangTo   = array('a','a','c','d','e','i','l','l','n','o','o','r','s','t','u','y','z','a','a','c','d','e','i','l','l','n','o','o','r','s','t','u','y','z');
			$langFrom   = array_merge ($langFrom, $skLangFrom);
			$langTo     = array_merge ($langTo, $skLangTo);
		}
		
		// SLOVENIAN
		if ($language == 'sl-SI') {
			$slLangFrom = array('č','š','ž','Č','Š','Ž');
			$slLangTo   = array('c','s','z','c','s','z');
			$langFrom   = array_merge ($langFrom, $slLangFrom);
			$langTo     = array_merge ($langTo, $slLangTo);
		}
		
		// LITHUANIAN
		if ($language == 'lt-LT') {
			$ltLangFrom = array('ą','č','ę','ė','į','š','ų','ū','ž','Ą','Č','Ę','Ė','Į','Š','Ų','Ū','Ž');
			$ltLangTo   = array('a','c','e','e','i','s','u','u','z','A','C','E','E','I','S','U','U','Z');
			$langFrom   = array_merge ($langFrom, $ltLangFrom);
			$langTo     = array_merge ($langTo, $ltLangTo);
		}
		
		// ICELANDIC
		if ($language == 'is-IS') {
			$isLangFrom = array('þ', 'æ', 'ð', 'ö', 'í', 'ó', 'é', 'á', 'ý', 'ú', 'Þ', 'Æ', 'Ð', 'Ö', 'Í', 'Ó', 'É', 'Á', 'Ý', 'Ú');
			$isLangTo   = array('th','ae','d', 'o', 'i', 'o', 'e', 'a', 'y', 'u', 'Th','Ae','D', 'O', 'I', 'O', 'E', 'A', 'Y', 'U');
			$langFrom   = array_merge ($langFrom, $isLangFrom);
			$langTo     = array_merge ($langTo, $isLangTo);
		}
		
		// TURKISH
		if ($language == 'tr-TR') {
			$tuLangFrom = array('ş','ı','ö','ü','ğ','ç','Ş','İ','Ö','Ü','Ğ','Ç');
			$tuLangTo   = array('s','i','o','u','g','c','S','I','O','U','G','C');
			$langFrom   = array_merge ($langFrom, $tuLangFrom);
			$langTo     = array_merge ($langTo, $tuLangTo);
		}
		
		
		// GERMAN - because of german names used in Czech, Hungarian, Polish or Slovak (because of possible
		// match - e.g. German a => ae, but Slovak a => a ... we can use only one, so we use:
		// a not ae, u not ue, o not oe, ? will be ss
		
		$deLangFrom  = array('ä','ö','ü','ß','Ä','Ö','Ü');
		$deLangTo    = array('a','o','u','ss','a','o','u');
		//$deLangTo  = array('ae','oe','ue','ss','ae','oe','ue');
		
		$langFrom    = array_merge ($langFrom, $deLangFrom);
		$langTo      = array_merge ($langTo, $deLangTo);
		
		$string = StringHelper::str_ireplace($langFrom, $langTo, $string);
		$string = StringHelper::strtolower($string);
		
		return $string;
	}
}
