<?php
/**
 * @version 1.5 stable $Id: flexicontent_reviews.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
class flexicontent_reviews extends JTable
{
	// Non-table (private) properties
	var $_record_name = 'review';
	var $_title = 'title';
	var $_alias = null;
	var $_force_ascii_alias = false;
	var $_allow_underscore = false;

	public function __construct(& $db)
	{
		$this->_records_dbtbl  = 'flexicontent_' . $this->_record_name . 's' . '_dev';
		$this->_records_jtable = 'flexicontent_' . $this->_record_name . 's';
		$this->_NAME = strtoupper($this->_record_name);

		parent::__construct('#__' . $this->_records_dbtbl, 'id', $db);
	}


	// overloaded check function
	public function check()
	{
		// Set submit date if it is empty
		if ( !$this->submit_date )
		{
			$datenow = JFactory::getDate();
			$this->submit_date = $datenow->toSql();
		}
		
		// If edited by review submitter then also set the update_date
		if ( $this->id && $this->user_id == JFactory::getUser()->id )
		{
			$datenow = JFactory::getDate();
			$this->update_date = $datenow->toSql();
		}

		return true;
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