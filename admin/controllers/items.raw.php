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

// Register autoloader for parent controller, in case controller is executed by another component
JLoader::register('FlexicontentController', JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'controller.php');

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
	
		
	function getversionlist()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));
		@ob_end_clean();
		$id 		= JRequest::getInt('id', 0);
		$active 	= JRequest::getInt('active', 0);
		if(!$id) return;
		$revert 	= JHtml::image ( 'administrator/components/com_flexicontent/assets/images/arrow_rotate_anticlockwise.png', JText::_( 'FLEXI_REVERT' ) );
		$view 		= JHtml::image ( 'administrator/components/com_flexicontent/assets/images/magnifier.png', JText::_( 'FLEXI_VIEW' ) );
		$comment 	= JHtml::image ( 'administrator/components/com_flexicontent/assets/images/comments.png', JText::_( 'FLEXI_COMMENT' ) );

		$model 	= $this->getModel('item');
		$model->setId($id);
		$item = $model->getItem( $id );
		
		$cparams = JComponentHelper::getParams( 'com_flexicontent' );
		$versionsperpage = $cparams->get('versionsperpage', 10);
		$currentversion = $item->version;
		$page=JRequest::getInt('page', 0);
		$versioncount = $model->getVersionCount();
		$numpage = ceil($versioncount/$versionsperpage);
		if($page>$numpage) $page = $numpage;
		elseif($page<1) $page = 1;
		$limitstart = ($page-1)*$versionsperpage;
		$versions = $model->getVersionList();
		$versions	= $model->getVersionList($limitstart, $versionsperpage);
		
		$jt_date_format = FLEXI_J16GE ? 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS_J16GE' : 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS';
		$df_date_format = FLEXI_J16GE ? "d/M H:i" : "%d/%m %H:%M" ;
		$date_format = JText::_( $jt_date_format );
		$date_format = ( $date_format == $jt_date_format ) ? $df_date_format : $date_format;
		$ctrl_task = FLEXI_J16GE ? 'task=items.edit' : 'controller=items&task=edit';
		foreach($versions as $v) {
			$class = ($v->nr == $active) ? ' id="active-version"' : '';
			echo '
			<tr'.$class.'>
				<td class="versions">#'.$v->nr.'</td>
				<td class="versions">'.JHtml::_('date', (($v->nr == 1) ? $item->created : $v->date), $date_format ).'</td>
				<td class="versions">'.(($v->nr == 1) ? $item->creator : $v->modifier).'</td>
				<td class="versions" align="center">
					<a href="javascript:;" class="hasTooltip" title="'.JHtml::tooltipText( JText::_( 'FLEXI_COMMENT' ), ($v->comment ? $v->comment : 'No comment written'), 0, 1).'">'.$comment.'</a>
				'.(
				((int)$v->nr==(int)$currentversion) ? // is current version ?
					'<a onclick="javascript:return clickRestore(\'index.php?option=com_flexicontent&'.$ctrl_task.'&cid='.$item->id.'&version='.$v->nr.'\');" href="javascript:;">'.JText::_( 'FLEXI_CURRENT' ).'</a>' :
					'<a class="modal-versions" href="index.php?option=com_flexicontent&view=itemcompare&cid[]='.$item->id.'&version='.$v->nr.'&tmpl=component" title="'.JText::_( 'FLEXI_COMPARE_WITH_CURRENT_VERSION' ).'" rel="{handler: \'iframe\', size: {x:window.getSize().scrollSize.x-100, y: window.getSize().size.y-100}}">'.$view.'</a>
					<a onclick="javascript:return clickRestore(\'index.php?option=com_flexicontent&'.$ctrl_task.'&cid='.$item->id.'&version='.$v->nr.'&'. JSession::getFormToken() .'=1\');" href="javascript:;" title="'.JText::sprintf( 'FLEXI_REVERT_TO_THIS_VERSION', $v->nr ).'">'.$revert.'</a>
				').'
				</td>
			</tr>';
		}
		exit;
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
			$cache = FLEXIUtilities::getCache($group='', 0);
			$cache->clean('com_flexicontent_items');
			$cache = FLEXIUtilities::getCache($group='', 1);
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
	 * Method to fetch the tags for selecting in item form
	 * 
	 * @since 1.5
	 */
	function viewtags()
	{
		// Check for request forgeries
		JSession::checkToken('request') or jexit(JText::_('JINVALID_TOKEN'));

		$app    = JFactory::getApplication();
		$jinput = $app->input;
		$perms  = FlexicontentHelperPerm::getPerm();

		@ob_end_clean();
		//header("Content-type:text/json");
		//header('Content-type: application/json');
		//header('Content-type: text/plain; charset=utf-8');  // this text/plain is browser's default
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Cache-Control: no-cache");
		header("Pragma: no-cache");

		if ( !$perms->CanUseTags )
		{
			$array =  array("{\"id\":\"0\",\"name\":\"You have no access\"}");
		}
		else
		{
			$model = $this->getModel('item');
			$tagobjs = $model->gettags($jinput->get('q', '', 'string'));

			$array = array();
			if ($tagobjs) foreach($tagobjs as $tag)
			{
				$array[] = "{\"id\":\"".$tag->id."\",\"name\":\"".$tag->name."\"}";
			}

			if (empty($array))
			{
				$array[] = "{\"id\":\"0\",\"name\":\"" . ($perms->CanCreateTags ? 'New tag, click enter to create' : 'No tags found') . "\"}";
			}
		}

		echo "[\n" . implode(",\n", $array) . "\n]";
		exit;
	}
	
	
	/**
	 * Method to select new state for many items
	 * 
	 * @since 1.5
	 */
	function selectstate()
	{
		// Use general permissions since we do not have examine any specific item
		$perms = FlexicontentHelperPerm::getPerm();
		$auth_publish = $perms->CanPublish || $perms->CanPublishOwn || $perms->CanPublish==null || $perms->CanPublishOwn==null;
		$auth_delete  = $perms->CanDelete  || $perms->CanDeleteOwn  || $perms->CanDelete==null  || $perms->CanDeleteOwn==null;
		$auth_archive = $perms->CanArchives;
		
		if($auth_publish || $auth_archive || $auth_delete)
		{
			//header('Content-type: application/json');
			@ob_end_clean();
			header('Content-type: text/html; charset=utf-8');
			header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
			header("Cache-Control: no-cache");
			header("Pragma: no-cache");
			
			$fc_css = JUri::base(true).'/components/com_flexicontent/assets/css/j3x.css';
			echo '
			<link rel="stylesheet" href="'.JUri::base(true).'/components/com_flexicontent/assets/css/flexicontentbackend.css?'.FLEXI_VHASH.'" />
			<link rel="stylesheet" href="'.$fc_css.'?'.FLEXI_VHASH.'" />
			<link rel="stylesheet" href="'.JUri::root(true).'/media/jui/css/bootstrap.min.css" />
			';
			?>
	<div id="flexicontent" class="flexicontent">

			<?php
			$btn_class = FLEXI_J30GE ? ' btn btn-small' : ' fc_button fcsimple fcsmall';
			
			if ($auth_publish) {
				$state['P'] = array( 'name' =>'FLEXI_PUBLISHED', 'desc' =>'FLEXI_PUBLISHED_DESC', 'icon' => 'tick.png', 'btn_class' => 'btn-success' );
				$state['IP'] = array( 'name' =>'FLEXI_IN_PROGRESS', 'desc' =>'FLEXI_NOT_FINISHED_YET', 'icon' => 'publish_g.png', 'btn_class' => 'btn-success', 'clear' => true );
				$state['U'] = array( 'name' =>'FLEXI_UNPUBLISHED', 'desc' =>'FLEXI_UNPUBLISHED_DESC', 'icon' => 'publish_x.png', 'btn_class' => 'btn-warning' );
				$state['PE'] = array( 'name' =>'FLEXI_PENDING', 'desc' =>'FLEXI_NEED_TO_BE_APPROVED', 'icon' => 'publish_r.png', 'btn_class' => 'btn-warning' );
				$state['OQ'] = array( 'name' =>'FLEXI_TO_WRITE', 'desc' =>'FLEXI_TO_WRITE_DESC', 'icon' => 'publish_y.png', 'btn_class' => 'btn-warning', 'clear' => true );
			}
			if ($auth_archive) {
				$state['A'] = array( 'name' =>'FLEXI_ARCHIVED', 'desc' =>'FLEXI_ARCHIVED_DESC', 'icon' => 'archive.png', 'btn_class' => 'btn-info' );
			}
			if ($auth_delete) {
				$state['T'] = array( 'name' =>'FLEXI_TRASHED', 'desc' =>'FLEXI_TRASHED_TO_BE_DELETED', 'icon' => 'trash.png', 'btn_class' => 'btn-danger' );
			}
			//echo "<b>". JText::_( 'FLEXI_SELECT_STATE' ).":</b>";
			echo "<br /><br />";
		?>
			
		<?php
			foreach($state as $shortname => $statedata) {
				$css = "width:216px; margin:0px 12px 12px 0px;";
				$link = JUri::base(true)."/index.php?option=com_flexicontent&task=items.changestate&newstate=".$shortname."&". JSession::getFormToken() ."=1";
				$icon = "../components/com_flexicontent/assets/images/".$statedata['icon'];
		?>
			<span class="fc-filter nowrap_box">
				<?php /*<img src="<?php echo $icon; ?>" style="margin:4px 0 0 0; border-width:0px; vertical-align:top;" alt="<?php echo JText::_( $statedata['desc'] ); ?>" /> &nbsp;*/ ?>
				<span style="<?php echo $css; ?>" class="<?php echo $btn_class.' '.$statedata['btn_class']; ?>"
					onclick="window.parent.fc_parent_form_submit('fc_modal_popup_container', 'adminForm', {'newstate':'<?php echo $shortname; ?>', 'task':'items.changestate'}, {'task':'items.changestate', 'is_list':true});"
				>
					<?php echo JText::_( $statedata['name'] ); ?>
				</span>
			</span>
		<?php
				if ( isset($statedata['clear']) ) echo '<div class="fcclear"></div>';
			}
		?>
	</div>
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
		flexicontent_html::setitemstate($this, 'json');
	}
	
}
