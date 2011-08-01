<?php
/**
 * @version 1.5 stable $Id: flexicontent.acl.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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

/**************************************************************************
 * All permissions are granted and denied within FLEXIcontent
 * with the following commands 
 * Comment out or add permissions as you desire
 *************************************************************************/

$auth =& JFactory::getACL();

//who can add an item?
$auth->addACL('com_flexicontent', 'add', 'users', 'super administrator');
$auth->addACL('com_flexicontent', 'add', 'users', 'administrator');
$auth->addACL('com_flexicontent', 'add', 'users', 'manager');
$auth->addACL('com_flexicontent', 'add', 'users', 'editor');
$auth->addACL('com_flexicontent', 'add', 'users', 'author');
$auth->addACL('com_flexicontent', 'add', 'users', 'registered');

//Who can edit an item?
$auth->addACL('com_flexicontent', 'edit', 'users', 'super administrator');
$auth->addACL('com_flexicontent', 'edit', 'users', 'administrator');
$auth->addACL('com_flexicontent', 'edit', 'users', 'manager');
$auth->addACL('com_flexicontent', 'edit', 'users', 'editor');
//use the one from com_content as workaround?
$auth->addACL('com_content', 'edit', 'users', 'author', 'content', 'own');

//Who can change the state of a faq item?
//Note: Users who can change the state of an item will see unpublished, non approved,
//in progress and open question items
//Archived items are only accessible via the administration
$auth->addACL('com_flexicontent', 'state', 'users', 'super administrator');
$auth->addACL('com_flexicontent', 'state', 'users', 'administrator');
$auth->addACL('com_flexicontent', 'state', 'users', 'manager');
$auth->addACL('com_flexicontent', 'state', 'users', 'editor');

//Who can add new tags?
/*
$auth->addACL('com_flexicontent', 'newtags', 'users', 'super administrator');
$auth->addACL('com_flexicontent', 'newtags', 'users', 'administrator');
$auth->addACL('com_flexicontent', 'newtags', 'users', 'manager');
$auth->addACL('com_flexicontent', 'newtags', 'users', 'editor');
*/

//Who can upload files?
$auth->addACL('com_flexicontent', 'fileupload', 'users', 'super administrator');
$auth->addACL('com_flexicontent', 'fileupload', 'users', 'administrator');
$auth->addACL('com_flexicontent', 'fileupload', 'users', 'manager');
$auth->addACL('com_flexicontent', 'fileupload', 'users', 'editor');
?>