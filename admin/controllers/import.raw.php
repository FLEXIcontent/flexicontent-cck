<?php
/**
 * @version 1.5 stable $Id: import.php 1650 2013-03-11 10:27:06Z ggppdk $
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

defined( '_JEXEC' ) or die( 'Restricted access' );

// Register autoloader for parent controller, in case controller is executed by another component
JLoader::register('FlexicontentController', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'controller.php');

/**
 * FLEXIcontent Component Import Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerImport extends FlexicontentController
{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();

		// Register Extra task
	}
	
	
	function getlineno() {
		$session = JFactory::getSession();
		$has_zlib = function_exists ( "zlib_encode" ); //version_compare(PHP_VERSION, '5.4.0', '>=');
		
		$conf   = $session->get('csvimport_config', "", 'flexicontent');
		$conf		= unserialize( $conf ? ($has_zlib ? zlib_decode(base64_decode($conf)) : base64_decode($conf)) : "" );
		$lineno = $session->get('csvimport_lineno', 999999, 'flexicontent');
		if ( !empty($conf) )
			echo 'success|'.count($conf['contents_parsed']).'|'.$lineno.'|'. JSession::getFormToken();
		else
			echo 'fail|0|0';
		jexit();
	}
}
