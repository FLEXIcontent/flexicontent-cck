<?php
/**
 * @version 1.5 stable $Id: flexicontent_tags.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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

/**
 * FLEXIcontent table class
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class flexicontent_tags extends JTable
{
	/**
	 * Primary Key
	 * @var int
	 */
	var $id						= null;
	/** @var string */
	var $name					= '';
	/** @var string */
	var $alias				= '';
	/** @var int */
	var $published		= null;
	/** @var int */
	var $checked_out	= 0;
	/** @var date */
	var $checked_out_time	= '';

	function __construct(& $db) {
		parent::__construct('#__flexicontent_tags', 'id', $db);
	}
	
	// overloaded check function
	function check()
	{
		// Not typed in a name?
		if (trim( $this->name ) == '') {
			$this->_error = JText::_( 'FLEXI_ADD_NAME' );
			JError::raiseWarning('SOME_ERROR_CODE', $this->_error );
			return false;
		}
		
		/** check for existing name */
		$query = 'SELECT id'
				.' FROM #__flexicontent_tags'
				.' WHERE name = '.$this->_db->Quote($this->name)
				;
		$this->_db->setQuery($query);

		$xid = intval($this->_db->loadResult());
		if ($xid && $xid != intval($this->id)) {
			JError::raiseWarning('SOME_ERROR_CODE', JText::sprintf('FLEXI_TAG_NAME_ALREADY_EXIST', $this->name));
			//$this->_error = JText::sprintf('TAG NAME ALREADY EXIST', $this->name);
			return false;
		}

		// check for empty alias
		if(empty($this->alias)) {
			$this->alias = $this->name;
		}

		// FLAGs
		$unicodeslugs = JFactory::getConfig()->get('unicodeslugs');
		
		$r = new ReflectionMethod('JApplicationHelper', 'stringURLSafe');
		$supports_content_language_transliteration = count( $r->getParameters() ) > 1;
		
		// workaround for old joomla versions (Joomla <=3.5.x) that do not allowing to set transliteration language to be element's language
		if ( !$unicodeslugs && !$supports_content_language_transliteration )
		{
			// Use ITEM's language or use SITE's default language in case of ITEM's language is ALL (or empty)
			$language = !empty($this->language) && $this->language != '*' ?
				$this->language :
				JComponentHelper::getParams('com_languages')->get('site', '*') ;
			
			// Remove any '-' from the string since they will be used as concatenaters
			$this->alias = str_replace('-', ' ', $this->alias);
			
			// Do the transliteration accorting to ELEMENT's language
			$this->alias = JLanguage::getInstance($language)->transliterate($this->alias);
		}
		
		// make alias safe and transliterate it
		$this->alias = JApplicationHelper::stringURLSafe($this->alias, $this->language);
		
		
		// check for empty alias and fallback to using current date
		if (trim(str_replace('-', '', $this->alias)) == '')
		{
			$this->alias = JFactory::getDate()->format('Y-m-d-H-i-s');
		}

	
		return true;
	}
}
?>