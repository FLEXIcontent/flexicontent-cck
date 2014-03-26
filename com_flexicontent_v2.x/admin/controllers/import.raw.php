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

jimport('joomla.application.component.controller');

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
		$conf   = $session->get('csvimport_config', "", 'flexicontent');
		$conf		= unserialize(zlib_decode(base64_decode($conf)));
		$lineno = $session->get('csvimport_lineno', 999999, 'flexicontent');
		if ( !empty($conf) )
			echo 'success|'.count($conf['contents_parsed']).'|'.$lineno.'|'.(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());
		else
			echo 'fail|0|0';
		jexit();
	}
}
