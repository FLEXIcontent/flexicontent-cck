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

jimport('joomla.application.component.controller');

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
		// JRequest::checkToken('request') or jexit( 'Invalid Token' );

		$name 	= JRequest::getString('name', '');
		$model 	= $this->getModel('tag');
		$array = JRequest::getVar('cid',  0, '', 'array');
		$cid = (int)$array[0];
		$model->setId($cid);
		if($cid==0) {
			$result = $model->addtag($name);
			if($result)
				echo $model->_tag->id."|".$model->_tag->name;
			//else echo "|";
		} else {
			$id = $model->get('id');
			$name = $model->get('name');
			echo $id."|".$name;
		}
	}

}