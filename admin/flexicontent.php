<?php
/**
 * @version 1.5 stable $Id: admin.flexicontent.php 1264 2012-05-04 15:55:52Z ggppdk $ 
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

// Joomla runs legacy components without its 'core.manage' check (ComponentDispatcher::checkAccess()), so do it here:
// without it, a user who may log in to the administrator but not manage FLEXIcontent could still call its tasks
if (!\Joomla\CMS\Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_flexicontent'))
{
	throw new \Joomla\CMS\Access\Exception\NotAllowed(\Joomla\CMS\Language\Text::_('JERROR_ALERTNOAUTHOR'), 403);
}

require_once (JPATH_COMPONENT.'/admin.flexicontent.php');
?>