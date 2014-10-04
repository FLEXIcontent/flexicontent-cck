<?php
/**
 * @version 1.5 stable $Id: author.php 967 2011-11-21 00:01:36Z ggppdk $
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

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die('Restricted access');
if (FLEXI_J16GE) {
	jimport('joomla.html.html');
	jimport('joomla.form.formfield');
}

/**
 * Renders a author element
 *
 * @package 	Joomla
 * @subpackage	FLEXIcontent
 * @since		1.0
 */
class JFormFieldAuthor extends JFormField
{
	/**
	 * Element name
	 * @access	protected
	 * @var		string
	 */
	var	$type = 'Author';

	function getInput()
	{
		$db = JFactory::getDBO();

		$query = 'SELECT DISTINCT u.id AS value, u.name AS text'
			. ' FROM #__users AS u'
			//. ' LEFT JOIN #__content AS c ON u.id=c.created_by' // COMMENTED OUT we want to display all users because field maybe used for selecting someone as author ???
			. ' WHERE u.block = 0'
			//. ' AND c.created_by IS NOT NULL'    // COMMENTED OUT we want to display all users because field maybe used for selecting someone as author ???
			; 
		$db->setQuery($query);
		$users = $db->loadObjectList();
		if ($db->getErrorNum())  JFactory::getApplication()->enqueueMessage(__FUNCTION__.'(): SQL QUERY ERROR:<br/>'.nl2br($db->getErrorMsg()),'error');
		
		$attribs = "";
		return JHTML::_('select.genericlist', $users, $this->name, $attribs, 'value', 'text', $this->value);
		//return JHTML::_('list.users', $this->name, $this->value);
	}
}