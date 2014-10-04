<?php
/**
 * @version 1.5 stable $Id: categories.php 171 2010-03-20 00:44:02Z emmanuel.danan $
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
 * FLEXIcontent Component Categories Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerCategories extends FlexicontentController
{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		if (FLEXI_J16GE) {
			$this->text_prefix = 'com_content';
		}
		parent::__construct();

		// Register Extra task
		$this->registerTask( 'params', 			'params' );
	}


	/**
	 * Logic to copy params from one category to others
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function params( )
	{
		// Check for request forgeries
		JRequest::checkToken() or jexit( 'Invalid Token' );
		
		$copyid		= JRequest::getInt( 'copycid', '', 'post' );
		$destid		= JRequest::getVar( 'destcid', null, 'post', 'array' );
		$task		= JRequest::getVar( 'task' );

		$user = JFactory::getUser();
		$model 	= $this->getModel('category');		
		$params = $model->getParams($copyid);
		
		if (!$destid) {
			echo '<div class="copyfailed">'.JText::_( 'FLEXI_NO_TARGET' ).'</div>';
			print_r($destid);
			return;
		}
		if ($copyid)
		{
			$y = 0;
			$n = 0;
			$unauthorized = array();
			foreach ($destid as $id)
			{
				// Check unauthorized categories is for J1.6+ only (categories have ACL edit action)
				if ( FLEXI_J16GE && !$user->authorise('core.edit', 'com_content.category.'.$id) ) {
					$unauthorized[] = $id;
					continue;
				}
				if ($model->copyParams($id, $params)) {
					$y++;
				} else {
					$n++;				
				}
			}
			echo '<div class="copyok">'.JText::sprintf( 'FLEXI_CAT_PARAMS_COPIED', $y, $n ).'</div>';
			if ( FLEXI_J16GE && count($unauthorized) ) {
				echo '<div class="copyfailed">'.'Skipped '.count($unauthorized).' uneditable categories with ids: '.implode(', ',$unauthorized).'</div>';
			}
		} else {
			echo '<div class="copyfailed">'.JText::_( 'FLEXI_NO_SOURCE' ).'</div>';
		}
	}
}
