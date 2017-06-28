<?php
/**
 * @version 1.0 $Id: checkboximage.php 1629 2013-01-19 08:45:07Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @subpackage plugin.checkboximage
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

class plgFlexicontent_fieldsCheckboximage extends FCIndexedField
{
	var $task_callable = array('getCascadedField');
	
	static $field_types = array('checkboximage');
	static $extra_props = array('image', 'valgrp', 'state');
	static $valueIsArr = 1;
	static $isDropDown = 0;
	static $promptEnabled = 0;
	static $usesImages = 1;
	
	
	// ***********
	// CONSTRUCTOR
	// ***********
	
	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
		JPlugin::loadLanguage('plg_flexicontent_fields_checkboximage', JPATH_ADMINISTRATOR);
	}
}