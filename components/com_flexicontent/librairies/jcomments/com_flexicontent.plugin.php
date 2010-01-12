<?php
/**
 * JComments plugin for FLEXIcontent (http://www.flexicontent.org) contents support
 *
 * @version 1.4
 * @package JComments
 * @author Sergey M. Litvinov (smart@joomlatune.ru)
 * @copyright (C) 2006-2008 by Sergey M. Litvinov (http://www.joomlatune.ru)
 * @plugin author Emmanuel Danan (emmanuel@vistamedia.fr)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 **/
(defined('_VALID_MOS') OR defined('_JEXEC')) or die('Direct Access to this location is not allowed.');

class jc_com_flexicontent extends JCommentsPlugin
{
	function getObjectTitle($id)
	{
		$db = & JCommentsFactory::getDBO();
		$db->setQuery( 'SELECT title FROM #__content WHERE id = ' . $id );
		return $db->loadResult();
	}

	function getObjectLink($id)
	{
		require_once(JPATH_ROOT.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');
		$db = & JCommentsFactory::getDBO();

		$query = 'SELECT CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug,'
			. ' CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as categoryslug'
			. ' FROM #__content AS i'
			. ' LEFT JOIN #__categories AS c ON c.id = i.catid'
			. ' WHERE i.id = '.$id
			;
		$db->setQuery($query);
		$row = $db->loadObject();

		$link = JRoute::_(FlexicontentHelperRoute::getItemRoute($row->slug, $row->categoryslug));

		return $link;
	}

	function getObjectOwner($id)
	{
		$db = & JCommentsFactory::getDBO();
		$db->setQuery( 'SELECT created_by FROM #__content WHERE id = ' . $id );
		$userid = $db->loadResult();
		
		return $userid;
	}

	function getCategories($filter = '')
	{
		$db = & JCommentsFactory::getDBO();

		$query = "SELECT id AS `value`, title AS `text`"
			. "\n FROM #__categories"
			. (($filter != '') ? "\n WHERE id IN ( ".$filter." )" : '')
			. "\n ORDER BY title"
			;
		$db->setQuery( $query );
		$rows = $db->loadObjectList();

		return $rows;
	}
}
?>