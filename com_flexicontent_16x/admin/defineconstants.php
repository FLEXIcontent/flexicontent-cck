<?php
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );

// Set filepath
$params =& JComponentHelper::getParams('com_flexicontent');
define('COM_FLEXICONTENT_FILEPATH',    JPATH_ROOT.DS.$params->get('file_path', 'components/com_flexicontent/uploads'));
define('COM_FLEXICONTENT_MEDIAPATH',   JPATH_ROOT.DS.$params->get('media_path', 'components/com_flexicontent/medias'));

// Define some constants
if($flexi_category = $params->get('flexi_category')) {
	if (!defined('FLEXI_CATEGORY')) {
		define('FLEXI_CATEGORY', $flexi_category);
		$db = &JFactory::getDBO();
		$query = "SELECT lft,rgt FROM #__categories WHERE id='".FLEXI_CATEGORY."';";
		$db->setQuery($query);
		$obj = $db->loadObject();
		if (!defined('FLEXI_CATEGORY_LFT'))	define('FLEXI_CATEGORY_LFT', $obj->lft);
		if (!defined('FLEXI_CATEGORY_RGT'))	define('FLEXI_CATEGORY_RGT', $obj->rgt);
	}
}else{
	if (!defined('FLEXI_CATEGORY'))	define('FLEXI_CATEGORY', $params->get('flexi_category'));
	if (!defined('FLEXI_CATEGORY_LFT'))	define('FLEXI_CATEGORY_LFT', 0);
	if (!defined('FLEXI_CATEGORY_RGT'))	define('FLEXI_CATEGORY_RGT', 0);
}
if (!defined('FLEXI_FISH'))		define('FLEXI_FISH',	($params->get('flexi_fish', 0) && (JPluginHelper::isEnabled('system', 'jfdatabase'))) ? 1 : 0);
define('FLEXI_VERSION',	'1.6');
define('FLEXI_RELEASE',	'Beta (r622)');
?>