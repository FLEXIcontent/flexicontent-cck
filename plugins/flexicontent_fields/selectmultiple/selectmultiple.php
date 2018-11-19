<?php
/**
 * @package         FLEXIcontent
 * @version         3.2
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2017, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
JLoader::register('FCIndexedField', JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/fcfield/indexedfield.php');

class plgFlexicontent_fieldsSelectmultiple extends FCIndexedField
{
	static $field_types = null; // Automatic, do not remove since needed for proper late static binding, define explicitely when a field can render other field types

	static $extra_props = array();
	static $valueIsArr = 1;
	static $isDropDown = 1;
	static $promptEnabled = 0;
	static $usesImages = 0;


	// ***
	// *** CONSTRUCTOR
	// ***

	function __construct( &$subject, $params )
	{
		parent::__construct( $subject, $params );
	}
}