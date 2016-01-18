<?php
/**
 * @version 1.5 stable $Id: tags.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
 * FLEXIcontent Component Tags Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerTags extends FlexicontentController
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
		$this->registerTask( 'import', 			'import' );
	}
	
	
	/**
	 * Logic to import a tag list
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function import( )
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$list  = JRequest::getVar( 'taglist', null, 'post', 'string' );
		$list  = preg_replace("/[\"'\\\]/u", "", $list);
		
		$model = $this->getModel('tags');		
		$logs  = $model->importList($list);
		
		if ($logs) {
			if ($logs['success']) {
				echo '<div class="copyok">'.JText::sprintf( 'FLEXI_TAG_IMPORT_SUCCESS', $logs['success'] ).'</div>';
			}
			if ($logs['error']) {
				echo '<div class="copywarn>'.JText::sprintf( 'FLEXI_TAG_IMPORT_FAILED', $logs['error'] ).'</div>';
			}
		} else {
			echo '<div class="copyfailed">'.JText::_( 'FLEXI_NO_TAG_TO_IMPORT' ).'</div>';
		}
	}
	
	
	/**
	 *  Add new Tag from item screen
	 *
	 */
	function addtag()
	{
		// Check for request forgeries
		JRequest::checkToken('request') or jexit( 'Invalid Token' );
		
		$name 	= JRequest::getString('name', '');
		$array = JRequest::getVar('cid',  0, '', 'array');
		$cid   = (int)$array[0];
		
		// Check if tag exists (id exists or name exists)
		JLoader::register("FlexicontentModelTag", JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS.'tag.php');
		$model 	= new FlexicontentModelTag();
		//$model 	= $this->getModel('tag');
		$model->setId($cid);
		$tag = $model->getTag($name);
		
		if ($tag && $tag->id)
		{
			// Since tag was found just output the loaded tag
			$id   = $model->get('id');
			$name = $model->get('name');
			echo $id."|".$name;
			jexit();
		}
		
		if ($cid)
		{
			echo "0|Tag not found";
			jexit();
		}
		
		if (!FlexicontentHelperPerm::getPerm()->CanCreateTags)
		{
			echo "0|".JText::_('FLEXI_NO_AUTH_CREATE_NEW_TAGS');
			jexit();
		}
		
		// Add the new tag and output it so that it gets loaded by the form
		try {
			$result = $model->addtag($name);
			echo  $result  ?  $model->_tag->id."|".$model->_tag->name :  "0|New tag was not created" ;
		} catch (Exception $e) {
			echo "0|New tag creation failed";
		}
		jexit();
	}
}