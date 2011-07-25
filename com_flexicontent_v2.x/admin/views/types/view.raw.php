<?php
/* SVN FILE: $Id: view.html.php 76 2010-10-13 07:19:46Z i_am_keng $ */
/**
* FLEXIMyContent : This project is a joomla component used with FLEXIcontent component.
* This component make the author can create new article and display them own articles.
* Then they can approval request to publisher or upper user level.
*
* @package Joomla-FLEXIcontent
* @subpackage FLEXIMyContent
* @author $Author: i_am_keng $
* @copyright $Copyright$
* @version $Revision: 76 $
* @lastrevision $Date: 2010-10-13 14:19:46 +0700 (Wed, 13 Oct 2010) $
* @modifiedby $LastChangedBy: i_am_keng $
* @lastmodified $LastChangedDate: 2010-10-13 14:19:46 +0700 (Wed, 13 Oct 2010) $
* @license $License$
* @filesource $URL: http://gforge.mambo.or.th/svn/fleximycontent/branches/com_fleximycontent_1.0/site/views/types/view.html.php $
* 
* 
* FLEXIMyContent is a derivative work of the excellent FLEXIcontent 1.5 stable
* @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
* see www.flexicontent.org for more information
* 
*/
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.application.component.view');
class FlexicontentViewTypes extends JView{
	function display( $tpl = null ) {
		$document	= & JFactory::getDocument();
		$document->addStyleSheet('components/com_fleximycontent/assets/css/fleximycontentfrontend.css');

		$db = &JFactory::getDBO();
		$query = "SELECT id,name FROM #__flexicontent_types WHERE published='1' ORDER BY name ASC;";
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		$rows = is_array($rows)?$rows:array();
?>
<div style="margin:20px;">
<div id="toolbar" class="toolbar">
<div class="logoflxmc">
</div><div class="pageflxmc"><?php echo "<strong>".JText::_("Select Type")."</strong><br />"; ?></div>
</div><div style="
color:#0083CB;
float:left;
padding-left: 80px;
font-size:14px;
font-weight:bold;
line-height:48px;
text-align:left;
text-shadow:0 0 1px #CCCCCC;">

<?php

foreach($rows as $obj) {
	echo "<a href=\"index.php?option=com_flexicontent&amp;controller=items&amp;task=add&amp;typeid={$obj->id}\" style=\"color:blue;\">";
	echo $obj->name;
	echo "</a><br />";
}
?>
</div>
</div>
<?php
	}
}
?>
