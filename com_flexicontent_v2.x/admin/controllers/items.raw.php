<?php
/**
 * @version 1.5 stable $Id: items.php 1782 2013-10-08 22:47:51Z ggppdk $
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
 * FLEXIcontent Component Item Controller
 *
 * @package Joomla
 * @subpackage FLEXIcontent
 * @since 1.0
 */
class FlexicontentControllerItems extends FlexicontentController
{
	/**
	 * Constructor
	 *
	 * @since 1.0
	 */
	function __construct()
	{
		parent::__construct();
	}


	
	/**
	 * Method to reset hits
	 * 
	 * @since 1.0
	 */
	function resethits()
	{
		$id		= JRequest::getInt( 'id', 0 );
		$model = $this->getModel('item');

		$model->resetHits($id);
		
		if (FLEXI_J16GE) {
			$cache = FLEXIUtilities::getCache();
			$cache->clean('com_flexicontent_items');
		} else {
			$itemcache = JFactory::getCache('com_flexicontent_items');
			$itemcache->clean();
		}
		echo 0;
	}


	/**
	 * Method to reset votes
	 * 
	 * @since 1.0
	 */
	function resetvotes()
	{
		$id		= JRequest::getInt( 'id', 0 );
		$model = $this->getModel('item');

		$model->resetVotes($id);
		
		if (FLEXI_J16GE) {
			$cache = FLEXIUtilities::getCache();
			$cache->clean('com_flexicontent_items');
		} else {
			$itemcache = JFactory::getCache('com_flexicontent_items');
			$itemcache->clean();
		}
		
		echo JText::_( 'FLEXI_NOT_RATED_YET' );
	}

	
	/**
	 * Method to fetch the tags form
	 * 
	 * @since 1.5
	 */
	function viewtags() {
		// Check for request forgeries
		JRequest::checkToken('request') or jexit( 'Invalid Token' );

		$user	= JFactory::getUser();
		
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$CanUseTags = $permission->CanUseTags;
		} else if (FLEXI_ACCESS) {
			$CanUseTags = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'usetags', 'users', $user->gmid) : 1;
		} else {
			// no FLEXIAccess everybody can create / use tags
			$CanUseTags = 1;
		}
		if($CanUseTags) {
			//header('Content-type: application/json');
			@ob_end_clean();
			header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
			header("Cache-Control: no-cache");
			header("Pragma: no-cache");
			//header("Content-type:text/json");
			$model 		=  $this->getModel('item');
			$tagobjs 	=  $model->gettags(JRequest::getVar('q'));
			$array = array();
			echo "[";
			if ($tagobjs) foreach($tagobjs as $tag) {
				$array[] = "{\"id\":\"".$tag->id."\",\"name\":\"".$tag->name."\"}";
			}
			echo implode(",", $array);
			echo "]";
			exit;
		}
	}


	/**
	 * Method to select new state for many items
	 * 
	 * @since 1.5
	 */
	function selectstate() {
		$user	= JFactory::getUser();
		
		// General permission since we do not have a specific item yet
		if (FLEXI_J16GE) {
			$permission = FlexicontentHelperPerm::getPerm();
			$auth_publish = $permission->CanPublish || $permission->CanPublishOwn;
			$auth_delete  = $permission->CanDelete  || $permission->CanDeleteOwn;
			$auth_archive = $permission->CanArchives;
		} else if (FLEXI_ACCESS) {
			$auth_publish  = ($user->gid < 25) ? (FAccess::checkComponentAccess('com_content', 'publish', 'users', $user->gmid) || FAccess::checkComponentAccess('com_content', 'publishown', 'users', $user->gmid)) : 1;
			$auth_delete   = ($user->gid < 25) ? (FAccess::checkComponentAccess('com_content', 'delete', 'users', $user->gmid) || FAccess::checkComponentAccess('com_content', 'deleteown', 'users', $user->gmid)) : 1;
			$auth_archive  = ($user->gid < 25) ? FAccess::checkComponentAccess('com_flexicontent', 'archives', 'users', $user->gmid) : 1;
		} else {
			$auth_publish = $user->authorize('com_content', 'publish', 'content', 'all');
			$auth_delete  = $user->gid >= 23; // is at least manager
			$auth_archive = $user->gid >= 23; // is at least manager
		}
		
		if($auth_publish || $auth_archive || $auth_delete) {
			//header('Content-type: application/json');
			@ob_end_clean();
			header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
			header("Cache-Control: no-cache");
			header("Pragma: no-cache");

			echo '<link rel="stylesheet" href="'.JURI::base().'components/com_flexicontent/assets/css/flexicontentbackend.css" />';
			if      (FLEXI_J30GE) $fc_css = JURI::base().'components/com_flexicontent/assets/css/j3x.css';
			else if (FLEXI_J16GE) $fc_css = JURI::base().'components/com_flexicontent/assets/css/j25.css';
			else                  $fc_css = JURI::base().'components/com_flexicontent/assets/css/j15.css';
			echo '<link rel="stylesheet" href="'.$fc_css.'" />';
			
			if ($auth_publish) {
				$state['P'] = array( 'name' =>'FLEXI_PUBLISHED', 'desc' =>'FLEXI_PUBLISHED_DESC', 'icon' => 'tick.png', 'color' => 'darkgreen' );
				$state['IP'] = array( 'name' =>'FLEXI_IN_PROGRESS', 'desc' =>'FLEXI_NOT_FINISHED_YET', 'icon' => 'publish_g.png', 'color' => 'darkgreen', 'clear' => true );
				$state['U'] = array( 'name' =>'FLEXI_UNPUBLISHED', 'desc' =>'FLEXI_UNPUBLISHED_DESC', 'icon' => 'publish_x.png', 'color' => 'darkred' );
				$state['PE'] = array( 'name' =>'FLEXI_PENDING', 'desc' =>'FLEXI_NEED_TO_BE_APPROVED', 'icon' => 'publish_r.png', 'color' => 'darkred' );
				$state['OQ'] = array( 'name' =>'FLEXI_TO_WRITE', 'desc' =>'FLEXI_TO_WRITE_DESC', 'icon' => 'publish_y.png', 'color' => 'darkred', 'clear' => true );
			}
			if ($auth_archive) {
				$state['A'] = array( 'name' =>'FLEXI_ARCHIVED', 'desc' =>'FLEXI_ARCHIVED_STATE', 'icon' => 'archive.png', 'color' => 'gray' );
			}
			if ($auth_delete) {
				$state['T'] = array( 'name' =>'FLEXI_TRASHED', 'desc' =>'FLEXI_TRASHED_TO_BE_DELETED', 'icon' => 'trash.png', 'color' => 'gray' );
			}
			echo "<b>". JText::_( 'FLEXI_SELECT_STATE' ).":</b><br /><br />";
		?>
			
		<?php
			foreach($state as $shortname => $statedata) {
				$css = "width:28%; margin:0px 1% 12px 1%; padding:1%; color:".$statedata['color'].";";
				$link = JURI::base(true)."/index.php?option=com_flexicontent&task=items.changestate&newstate=".$shortname."&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1";
				$icon = "../components/com_flexicontent/assets/images/".$statedata['icon'];
		?>
				<a style="<?php echo $css; ?>" class="fc_button" href="javascript:;"
						onclick="
							window.parent.document.adminForm.newstate.value='<?php echo $shortname; ?>';
							if(window.parent.document.adminForm.boxchecked.value==0)
								alert('<?php echo JText::_('FLEXI_NO_ITEMS_SELECTED'); ?>');
							else
		<?php if (FLEXI_J16GE) { ?>
								window.parent.Joomla.submitbutton('items.changestate')";
		<?php } else { ?>
								window.parent.submitbutton('changestate')";
		<?php } ?>
						target="_parent">
					<img src="<?php echo $icon; ?>" width="16" height="16" border="0" alt="<?php echo JText::_( $statedata['desc'] ); ?>" />
					<?php echo JText::_( $statedata['name'] ); ?>
				</a>
		<?php
				if ( isset($statedata['clear']) ) echo "<div style='width:100%; float: left; clear both;'></div>";
			}
		?>
			
		<?php
			exit();
		}
	}
	
	
	/**
	 * Method to fetch total count the unassociated items
	 * 
	 * @since 1.5
	 */
	function getOrphansItems()
	{
		$model  = $this->getModel('items');
		$status = $model->getUnboundedItems($limit=1000000, $count_only=true, $checkNoExtData=true, $checkInvalidCat=false);
		echo $status;
		exit;
	}
	
	
	/**
	 * Method to fetch total count the unassociated items
	 * 
	 * @since 1.5
	 */
	function getBadCatItems()
	{
		$model  = $this->getModel('items');
		$status = $model->getUnboundedItems($limit=1000000, $count_only=true, $checkNoExtData=false, $checkInvalidCat=true);
		echo $status;
		exit;
	}
	
	
	/**
	 * Bind fields, category relations and items_ext data to Joomla! com_content imported articles
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function bindextdata()
	{
		// Need to recheck post-installation / integrity tasks after,
		// this should NOT effect RAW HTTP requests, used by AJAX ITEM binding
		//JFactory::getSession()->set('flexicontent.recheck_aftersave', true);
		
		$bind_limit = JRequest::getInt('bind_limit', 25000);
		if ($bind_limit < 1 || $bind_limit > 25000) $bind_limit = 25000;  // make sure this is valid
		$model = $this->getModel('items');
		$rows  = $model->getUnboundedItems($bind_limit, $count_only=false, $checkNoExtData=true, $checkInvalidCat=false, $noCache=true);
		$model->bindExtData($rows);
		jexit();
	}
	
	
	/**
	 * Fix Items having bad main category
	 *
	 * @access public
	 * @return void
	 * @since 1.5
	 */
	function fixmaincat()
	{
		$default_cat = JRequest::getInt('default_cat', 0);
		$model = $this->getModel('items');
		$model->fixMainCat($default_cat);
		jexit();
	}
	
	
	/**
	 * Logic to change the state of an item
	 *
	 * @access public
	 * @return void
	 * @since 1.0
	 */
	function setitemstate()
	{
		flexicontent_html::setitemstate($this);
	}
	
}
