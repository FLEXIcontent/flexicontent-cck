<?php
/**
 * @version 1.0 $Id: selectmultiple.php 1629 2013-01-19 08:45:07Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.selectmultiple
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */
defined( '_JEXEC' ) or die( 'Restricted access' );

JLoader::register('FCIndexedField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/indexedfield.php');

class plgFlexicontent_fieldsSelectmultiple extends FCIndexedField
{
	var $task_callable = array('getCascadedField');
	
	static $field_types = array('selectmultiple');
	static $extra_props = array();
	static $valueIsArr = 1;
	static $isDropDown = 1;
	static $promptEnabled = 0;
	static $usesImages = 0;
	
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_selectmultiple', JPATH_ADMINISTRATOR);
	}
}