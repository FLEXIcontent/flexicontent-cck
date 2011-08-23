<?php
defined( '_JEXEC' ) or die( 'Restricted access' );
class FLEXIUtilities {
	function get_flexi_cat_extension() {
		static $flexi_cat_extension;
		
		$params =& JComponentHelper::getParams('com_flexicontent');
		$flexi_cat_extension = $params->get('flexi_cat_extension','com_content');
		return $flexi_cat_extension;
	}
	function isJ15() {
		static $j15;
		if(!isset($j15)) {
			if (!isset($GLOBALS['_VERSION']) && function_exists('jimport')) {
				jimport('joomla.version');
				$GLOBALS['_VERSION'] = new JVersion();
			}
			$j15 =(version_compare($GLOBALS['_VERSION']->RELEASE,"1.5")===0);
		}
		return $j15;
	}
	function getMainFrame() {
		$mainframe =& JFactory::getApplication();
		if(!isset($mainframe)) {
			$mainframe =& JFactory::getApplication();
		}
		return $mainframe;
	}
	function getAccessText($access) {
		global $accessText;
		if(!isset($accessText[$access])) {
			$item = &JTable::getInstance('flexicontent_items','');
			$dbo = &$item->getDBO();
			$query = "SELECT title FROM #__viewlevels WHERE id='$access';";
			$dbo->setQuery($query);
			$accessText[$access] = $dbo->loadResult();
		}
		return $accessText[$access];
	}
}
?>