<?php
/**
 * @version 1.5 stable $Id: view.html.php 549 2011-03-28 04:21:56Z emmanuel.danan@gmail.com $
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

// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );
require_once(JPATH_BASE.DS."administrator".DS."components".DS."com_flexicontent".DS."views".DS."fileselement".DS."view.html.php");

//allow css override
$app = JFactory::getApplication();
$document = JFactory::getDocument();
if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css')) {
	$document->addStyleSheet(JURI::base(true).'/templates/'.$app->getTemplate().'/css/flexicontent.css');
}

?>