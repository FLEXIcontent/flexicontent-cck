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

// Import parent controller
jimport('legacy.controller.admin');

/**
 * FLEXIcontent Component Categories Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerCategories extends JControllerAdmin
{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		$this->text_prefix = 'com_content';
		parent::__construct();

		// Register Extra task
		$this->registerTask( 'params', 			'params' );
	}


	/**
	 * Proxy for getModel
	 *
	 * @param   string  $name    The model name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  The array of possible config values. Optional.
	 *
	 * @return  JModelLegacy  The model.
	 *
	 * @since   1.6
	 */
	public function getModel($name = 'Categories', $prefix = 'FlexicontentModel', $config = array('ignore_request' => true))
	{
		if ($this->input->get('task', '', 'cmd') == __FUNCTION__) die(__FUNCTION__ . ' : direct call not allowed');

		$name = $name ?: 'Categories';
		require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'models'.DS . strtolower($name) . '.php');

		return parent::getModel($name, $prefix, $config);
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
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));
		
		$copyid		= JRequest::getInt( 'copycid', '', 'post' );
		$destid		= JRequest::getVar( 'destcid', null, 'post', 'array' );
		$task		= JRequest::getVar( 'task' );

		$user = JFactory::getUser();
		$model 	= $this->getModel('category');		
		$params = $model->getParams($copyid);
		
		if (!$destid)
		{
			echo '<div class="copyfailed">'.JText::_( 'FLEXI_NO_TARGET' ).'</div>';
			return;
		}

		if (!$copyid)
		{
			echo '<div class="copyfailed">'.JText::_( 'FLEXI_NO_SOURCE' ).'</div>';
			return;
		}

		// Check for unauthorized categories
		$y = 0;
		$n = 0;
		$unauthorized = array();
		foreach ($destid as $id)
		{
			if ( !$user->authorise('core.edit', 'com_content.category.'.$id) )
			{
				$unauthorized[] = $id;
				continue;
			}

			$model->copyParams($id, $params)
				? $y++
				: $n++;
		}

		echo '<div class="copyok">'.JText::sprintf( 'FLEXI_CAT_PARAMS_COPIED', $y, $n ).'</div>';
		if ( count($unauthorized) )
		{
			echo '<div class="copyfailed">'.'Skipped '.count($unauthorized).' uneditable categories with ids: '.implode(', ',$unauthorized).'</div>';
		}
	}

	/**
	 * Logic to change the state of a category
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function setitemstate()
	{
		flexicontent_html::setitemstate($this, 'json', $_record_type = 'category');
	}
}
