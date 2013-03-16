<?php
/**
 * @version 1.5 stable $Id: flexinotify.php 1333 2012-06-02 10:04:40Z ggppdk $
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

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );

jimport( 'joomla.plugin.plugin' );

/**
 * Flexicontent Notification Plugin
 *
 * @package		Joomla
 * @subpackage	FLEXIcontent
 * @since 		1.5.5
 */
class plgFlexicontentFlexinotify extends JPlugin
{

	/**
	 * Constructor
	 *
	 * For php4 compatability we must not use the __constructor as a constructor for plugins
	 * because func_get_args ( void ) returns a copy of all passed arguments NOT references.
	 * This causes problems with cross-referencing necessary for the observer design pattern.
	 *
	 * @param object $subject The object to observe
	 * @param object $params  The object that holds the plugin parameters
	 * @since 1.5
	 */
	function plgFlexicontentFlexinotify( &$subject, $params )
	{
		parent::__construct( $subject, $params );

		JPlugin::loadLanguage( 'plg_flexicontent_flexinotify' );
	}


	/**
	 * This method is executed just before an item is stored
	 *
	 * Method is called by the model
	 *
	 * @param 	object		The item object.
	 * @param 	boolean		Indicates if item is new
	 */
	/*function onBeforeSaveItem( &$item, $isnew ) {
		$post = JRequest::get('post');
		//echo "<pre>"; $post; echo "</pre>"; exit;
		
		//...
		
		if ($somethingbad) {
			$app = &JFactory::getApplication();
			$app->enqueueMessage( 'Saving cancel due to error ...', 'notice' );
			return false;
		}
		
		return true;
	}*/
	
	
	/**
	 * This method is executed just after an item stored (including custom fields)
	 *
	 * Method is called by the model
	 *
	 * @param 	object		The item object.
	 * @param 	object		The complete $_POST data
	 */
	function onAfterSaveItem( &$item, &$post )
	{
		global $mainframe;
		$notify	= isset($post['notify']) ? true : false;
		
		// Performance check
		if (!$notify) return;

		$subscribers = $this->_getSubscribers($item->id);
		// Don't do anything if there are no subscribers
		if (count($subscribers) == 0) return;

		// Get Plugin info
		$plugin = JPluginHelper::getPlugin('flexicontent', 'flexinotify');
		$pluginParams = FLEXI_J16GE ? new JRegistry($plugin->params) : new JParameter($plugin->params);
		
		foreach ($subscribers as $sub) {
			$this->_sendEmail($item, $sub, $pluginParams);
		}
	}
	
	
	/**
	 * This method is executed after item saving is complete (all data, e.g. including versioning metadata)
	 *
	 * Method is called by the model
	 *
	 * @param 	object		The item object.
	 * @param 	object		The complete $_POST data
	 */
	/*function onCompleteSaveItem( &$item, &$fields ) {
	}*/
	
	
	function _getSubscribers($itemid)
	{
		$db =& JFactory::getDBO();
		
		$query	= 'SELECT u.* '
				.' FROM #__flexicontent_favourites AS f'
				.' LEFT JOIN #__users AS u'
				.' ON u.id = f.userid'
				.' WHERE f.itemid = ' . (int)$itemid
				.'  AND u.block=0 '
				;
		$db->setQuery($query);
		$users = $db->loadObjectList();
		
		return $users;
	}

	function _sendEmail($item, $subscriber, $params)
	{
		global $globalcats;
		// Get the route helper
		require_once (JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'helpers'.DS.'route.php');

		// Message parameters
		// 1: $subname		Name of the subscriber
		// 2: $itemid		ID of the item
		// 3: $title		Title of the item
		// 4: $maincat		Main category of the item
		// 5: $link			Link of the item
		// 6: $sitename		Website

		jimport( 'joomla.mail.helper' );

		$mainframe =& JFactory::getApplication();
		
		$siteurl 	= JURI::base();
		$siteurl 	= str_replace('administrator/', '', $siteurl);
		$siteurl	= str_replace('&amp;', '&', $siteurl);

		$sitename	= $mainframe->getCfg('sitename') . ' - ' . $siteurl;
		$subname 	= $subscriber->name;
		$autologin	= $params->get('autologin', 1) ? '&fcu='.$subscriber->username . '&fcp='.$subscriber->password : '';
		$link 		= $siteurl . JRoute::_(FlexicontentHelperRoute::getItemRoute($item->id.':'.$item->alias, $globalcats[$item->catid]->slug)) . $autologin;
		$link		= str_replace('&amp;', '&', $link);
		$title		= $item->title;
		$maincat	= $globalcats[$item->catid]->title;
		$itemid		= $item->id;
		
		$sendername	= $params->get('sendername', $mainframe->getCfg('sitename'));
		$sendermail	= $params->get('sendermail', $mainframe->getCfg('mailfrom'));
		$sendermail	= JMailHelper::cleanAddress($sendermail);
		$from	 	= array( $sendermail, $sendername );
		$to		 	= JMailHelper::cleanAddress($subscriber->email);
		$subject	= $params->get('mailsubject', '') ? JMailHelper::cleanSubject($params->get('mailsubject')) : JText::_('FLEXI_SUBJECT_DEFAULT');
		$message 	= JText::sprintf('FLEXI_NOTIFICATION_MESSAGE', $subname, $itemid, $title, $maincat, $link, $sitename);
		
		$mailer =& JFactory::getMailer(); 
		$mailer->setSender($from);
		$mailer->addReplyTo($from);	
		$mailer->addRecipient($to);
		$mailer->setSubject($subject); 
		$mailer->setBody($message);
		$mailer->IsHTML(false);

		$mailer->send();
	}

}