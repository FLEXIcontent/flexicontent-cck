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
	var $name					= null;

	/** @var string */
	var $alias				= null;

	/** @var int */
	var $published		= 0;

	/** @var int */
	var $checked_out	= 0;

	/** @var date */
	var $checked_out_time	= '';

	// Non-table (private) properties
	var $_record_name = 'tag';
	var $_title = 'name';
	var $_alias = 'alias';
	var $_force_ascii_alias = false;

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

		// Check if 'title' was not given
		if (trim( $this->$title ) == '')
		{
			$msg = JText::_( 'FLEXI_ADD_' . strtoupper($title) );
			JFactory::getApplication()->enqueueMessage($msg, 'error');
			return false;
		}

		if ($this->_force_ascii_alias)
		{
			$valid_pattern = '/^[a-z_]+[a-z_0-9-]+$/i';
			$is_valid = false;
			$is_valid = $is_valid || preg_match($valid_pattern, $this->$alias);
			if (!$is_valid)
			{
				$bad_alias = $this->$alias;
				$this->$alias = null;
			}
		}

		// Check for existing 'alias'
		if (!empty($this->$alias))
		{
			$query = 'SELECT id'
				. ' FROM #__' . $this->_records_dbtbl
				. ' WHERE ' . $alias . ' = '.$this->_db->Quote($this->$alias);
			$this->_db->setQuery($query);

			$xid = intval($this->_db->loadResult());
			if ($xid && $xid != intval($this->id))
			{
				$msg = JText::sprintf('FLEXI_THIS_' . $this->_NAME . '_' . strtoupper($alias) . '_ALREADY_EXIST', $this->name);
				JFactory::getApplication()->enqueueMessage($msg, 'warning');
				return false;
			}
		}

		// Use 'title' as alias if 'alias' is empty
		else
		{
			$this->$alias = $this->$title;
		}

		// FLAGs
		$unicodeslugs = JFactory::getConfig()->get('unicodeslugs');

		$r = new ReflectionMethod('JApplicationHelper', 'stringURLSafe');
		$supports_content_language_transliteration = count( $r->getParameters() ) > 1;

		// Use ITEM's language or use SITE's default language in case of ITEM's language is ALL (or empty)
		$language = !empty($this->language) && $this->language != '*'
			? $this->language
			: JComponentHelper::getParams('com_languages')->get('site', '*');

		// Workaround for old joomla versions (Joomla <=3.5.x) that do not allow to set transliteration language to be element's language
		$this->_force_ascii_alias = $this->_force_ascii_alias || (!$unicodeslugs && !$supports_content_language_transliteration);

		// Force ascii alias if current record type requires ascii-only alias
		if ($this->_force_ascii_alias)
		{
			// Remove any '-' from the string since they will be used as concatenaters
			$this->$alias = str_replace('-', ' ', $this->$alias);
			
			// Do the transliteration accorting to ELEMENT's language
			$this->$alias = JLanguage::getInstance($language)->transliterate($this->$alias);
		}
		
		// Make alias safe and transliterate it
		$this->$alias = JApplicationHelper::stringURLSafe($this->$alias, $language);

		// Check for empty alias and fallback to using current date
		if (trim(str_replace('-', '', $this->$alias)) == '')
		{
			$this->$alias = JFactory::getDate()->format('Y-m-d-H-i-s');
		}

		if (!empty($bad_alias))
		{
			$msg = JText::sprintf('FLEXI_WARN_' . $this->_NAME . '_' . strtoupper($alias) . '_CORRECTED', $_alias, $this->$alias);
			JFactory::getApplication()->enqueueMessage($msg, 'notice');
		}

		return true;
	}
}