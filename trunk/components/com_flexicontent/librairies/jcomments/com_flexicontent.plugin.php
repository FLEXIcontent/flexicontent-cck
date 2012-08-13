<?php
/**
 * JComments plugin for FLEXIcontent (http://www.flexicontent.org) contents support
 *
 * @version 2.3
 * @package JComments
 * @author Emmanuel Danan (emmanuel@vistamedia.fr)
 * @copyright (C) 2006-2012 by Sergey M. Litvinov (http://www.joomlatune.ru)
 * @copyright (C) 2011 by Emmanuel Danan (http://www.vistamedia.fr)
 * @license GNU/GPL: http://www.gnu.org/copyleft/gpl.html
 **/
(defined('_VALID_MOS') OR defined('_JEXEC')) or die('Direct Access to this location is not allowed.');

class jc_com_flexicontent extends JCommentsPlugin
{
	function getObjectInfo($id, $language = null)
	{
		$info = new JCommentsObjectInfo();

		$routerHelper = JPATH_ROOT.'/components/com_flexicontent/helpers/route.php';
		if (is_file($routerHelper)) {
			require_once($routerHelper);

			$db = JFactory::getDBO();
			$query = 'SELECT i.id, i.title, i.access, i.created_by '
				. ' , CASE WHEN CHAR_LENGTH(i.alias) THEN CONCAT_WS(\':\', i.id, i.alias) ELSE i.id END as slug'
				. ' , CASE WHEN CHAR_LENGTH(c.alias) THEN CONCAT_WS(\':\', c.id, c.alias) ELSE c.id END as catslug'
				. ' FROM #__content AS i'
				. ' LEFT JOIN #__categories AS c ON c.id = i.catid'
				. ' WHERE i.id = '.$id
				;
			$db->setQuery($query);
			$row = $db->loadObject();
			
			if (!empty($row)) {
				$info->title = $row->title;
				$info->access = $row->access;
				$info->userid = $row->created_by;
				$info->link = JRoute::_(FlexicontentHelperRoute::getItemRoute($row->slug, $row->catslug));
			}
		}

		return $info;
	}
}
?>