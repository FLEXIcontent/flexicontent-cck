<?php
/**
 * @version 1.5 stable $Id: default.php 1887 2014-04-24 23:53:14Z ggppdk $
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

$app       = JFactory::getApplication();
$option    = JRequest::getVar('option');
$user      = JFactory::getUser();
$template  = $app->getTemplate();

$btn_class = FLEXI_J30GE ? 'btn' : 'fc_button';
$tooltip_class = FLEXI_J30GE ? 'hasTooltip' : 'hasTip';
$edit_item_txt = JText::_( 'FLEXI_EDIT_ITEM' );

// hide dashboard buttons
$dashboard_buttons_hide = $this->params->get('dashboard_buttons_hide', array());
$dashboard_buttons_hide = FLEXIUtilities::paramToArray($dashboard_buttons_hide);

$sbtns = array_flip($dashboard_buttons_hide);
$skip_content_fieldset = isset($sbtns['items']) && isset($sbtns['additem']) && isset($sbtns['cats']) && isset($sbtns['addcat']) && isset($sbtns['comments']);
$skip_types_fieldset = isset($sbtns['types']) && isset($sbtns['addtype']) && isset($sbtns['fields']) && isset($sbtns['addfield']) && isset($sbtns['tags']) && isset($sbtns['addtag']) && isset($sbtns['files']);
$skip_contentviewing_fieldset = isset($sbtns['templates']) && isset($sbtns['index']) && isset($sbtns['stats']);
$skip_users_fieldset = isset($sbtns['users']) && isset($sbtns['adduser']) && isset($sbtns['groups']) && isset($sbtns['addgroup']);
$skip_expert_fieldset = isset($sbtns['import']) && isset($sbtns['plgfields']) && isset($sbtns['plgsystem']) && isset($sbtns['plgflexicontent']);

// disable dashboard sliders
$dashboard_sliders_disable = $this->params->get('dashboard_sliders_disable', array());
$dashboard_sliders_disable  = FLEXIUtilities::paramToArray($dashboard_sliders_disable);

// Other options
$modal_item_edit = $this->params->get('dashboard_modal_item_edit', 1);
$onclick_modal_edit = $modal_item_edit ? 'onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\'); return false;"' : '';
$disable_fc_logo = $this->params->get('dashboard_disable_fc_logo', 0);
$hide_fc_license_credits = $this->params->get('dashboard_hide_fc_license_credits', 1); /* hide inside sliders */

// Get/Check PHP requiremenets
$php_lims = flexicontent_html::checkPHPLimits();

$ssliders = array_flip($dashboard_sliders_disable);
$skip_sliders = isset($sbtns['pending']) && isset($sbtns['revised']) && isset($sbtns['inprogress']) && isset($sbtns['draft']) && isset($sbtns['version']);
$skip_sliders = $skip_sliders && $this->dopostinstall && $this->allplgpublish && !$hide_fc_license_credits && !isset($php_lims['warning']);

// ensures the PHP version is correct
if (version_compare(PHP_VERSION, FLEXI_PHP_NEEDED, '<'))
{
	echo '<div class="fc-mssg fc-error">';
	echo JText::sprintf( 'FLEXI_UPGRADE_PHP_VERSION_GE', FLEXI_PHP_NEEDED) . '<br/>';
	echo '</div>';
	return false;
}

$ctrl = FLEXI_J16GE ? 'items.' : '';
$items_task = FLEXI_J16GE ? 'task=items.' : 'controller=items&amp;task=';
?>

<div id="flexicontent" class="flexicontent">
<form action="index.php" method="post" name="adminForm" id="adminForm">

<?php if (!empty( $this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo str_replace('type="button"', '', $this->sidebar); ?>
	</div>
	<div id="j-main-container" class="span10">
<?php else : ?>
	<div id="j-main-container">
<?php endif;?>

		<?php
		if (version_compare(PHP_VERSION, FLEXI_PHP_RECOMMENDED, '<'))
		{
			echo '<div class="fc-mssg fc-note">';
			echo JText::sprintf( 'PHP version > %s is recommended', FLEXI_PHP_RECOMMENDED) . '<br/>';
			echo '</div>';
		}
		$_title = "PHP/DB Requirements";
		
		// Set a system message with warning of failed PHP limits
		$phplimits_printed = $app->getUserStateFromRequest( $option.'.flexicontent.phplimits_printed',	'phplimits_printed',	0, 'int' );
		if ($this->dopostinstall && isset($php_lims['notice']))
		{
			$app->setUserState( $option.'.flexicontent.phplimits_printed', $phplimits_printed+1 );
			if ($phplimits_printed < 1) {
				echo '<div class="fc-mssg fc-note">';
				echo '<b>PHP/DB requirements</b><br/>';
				foreach($php_lims as $type => $html) {
					echo implode('<br/>', $html);
				}
				echo JText::sprintf(
					'<br/>(you may have to contact your web hosting company for setting these for you)<br/>
					For more information on changing these limitations, please see this article: %s',
					'<a href="http://www.flexicontent.org/documentation/faq/78-installation-upgrade/591">PHP/DB Requirements</a>'
				);
				echo '</div>';
			}
		}
		
		if ( isset($php_lims['warning']) ) {
			$_title .= ' - <span class="badge badge-important">Warning</span>';
		} else if ( isset($php_lims['notice']) ) {
			$_title .= ' - <span class="badge badge-warning">Notice</span>';
		} else {
			$_title .= ' - <span class="badge badge-success">OK</span>';
		}
		?>
		
		<div id="fc-dash-boardbtns">
		<?php
		$config_saved = $this->params->get('flexi_cat_extension');
		if (!$this->dopostinstall)
		{
			echo '<div class="fc-mssg fc-warning">';
			echo JText::_( 'FLEXI_DO_POSTINSTALL' );
			echo '</div>';
		}
		else if ( !$this->existmenu || !$this->existcat || !$config_saved )
		{
			echo '<div class="fc-mssg fc-warning">';
			if ( !$config_saved )
			{
				if ( FLEXI_J16GE ) {
					$session = JFactory::getSession();
					$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
					$_width = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
					$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
					$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
					$conf_link = 'index.php?option=com_config&view=component&component=com_flexicontent&path=';
					$conf_link = '<a href="'.$conf_link.'" class="btn btn-warning">';
					$msg = JText::sprintf( 'FLEXI_CONFIGURATION_NOT_SAVED', $conf_link.JText::_("FLEXI_CONFIG").'</a>' );
				} else {
					$msg = str_replace('"_QQ_"', '"', JText::_( 'FLEXI_NO_SECTION_CHOOSEN' ));
				}
				echo $msg . '<br/>';
			}
			else if (!$this->existcat)	echo JText::_( 'FLEXI_NO_CATEGORIES_CREATED' );
			else if (!$this->existmenu)	echo JText::_( 'FLEXI_NO_MENU_CREATED' );
			echo '</div>';
		}

		if ($this->dopostinstall && $config_saved) {
			?><?php if (empty($skip_content_fieldset)): ?><fieldset class="fc-board-set"><legend class="fc-board-header-content-editing"><?php echo JText::_( 'FLEXI_NAV_SD_CONTENT_EDITING' );?></legend><div class="fc-board-set-inner"><?php
			$link = 'index.php?option='.$option.'&amp;view=items';
			if (!isset($sbtns['items'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-items.png', JText::_( 'FLEXI_ITEMS' ) );
			if (!isset($sbtns['additem']))
			{
				// Check if user can create in at least one published category
				require_once("components/com_flexicontent/models/item.php");
				$itemmodel = new FlexicontentModelItem();
				$CanAddAny = $itemmodel->getItemAccess()->get('access-create');
				if ($CanAddAny) {
					//$link = 'index.php?option='.$option.'&amp;view=item';
					$link = 'index.php?option='.$option.'&amp;view=types&amp;format=raw';
					FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-item-add.png', JText::_( 'FLEXI_NEW_ITEM' ), 1, 1, 600, 450 );
				}
			}
			/*if ($this->perms->CanArchives && !isset($sbtns['archives']))
			{
				$link = 'index.php?option='.$option.'&amp;view=archive';
				FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-archive.png', JText::_( 'FLEXI_ARCHIVE' ) );
			}*/
			if ($this->perms->CanCats && !isset($sbtns['addcat']))
			{
				$link = 'index.php?option='.$option.'&amp;view=categories';
				if (!isset($sbtns['cats'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-categories.png', JText::_( 'FLEXI_CATEGORIES' ) );
				$canCreateAny = FlexicontentHelperPerm::getPermAny('core.create');
				if ($canCreateAny)
				{
					$link = 'index.php?option='.$option.'&amp;view=category';
					FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-category-add.png', JText::_( 'FLEXI_NEW_CATEGORY' ) );
				}
			}
			if (isset($sbtns['comments'])) {} // skip
			else if (
				($this->params->get('comments')==1 && $this->perms->CanComments) ||  // Can administer JComments
				(!$this->params->get('comments') && $this->params->get('comments', 'comments_admin_link'))  // Custom comments extension
			) {
				echo '<span class="fc-board-button_sep" style="float:'.($lang->isRTL() ? 'right' : 'left').'"></span>';
				$link = ($this->params->get('comments')==1 && $this->perms->CanComments) ?
					'index.php?option=com_jcomments&amp;task=view&amp;fog=com_flexicontent' :
					$this->params->get('comments', 'comments_admin_link');
				FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-comments.png', JText::_( 'FLEXI_COMMENTS' ), 1 );
			}
			else if ($this->params->get('comments')==1 && !$this->perms->JComments_Installed)
			{
				echo '<span class="fc-board-button_sep" style="float:'.($lang->isRTL() ? 'right' : 'left').'"></span>';
				$link = '';
				FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-comments.png', JText::_( 'FLEXI_JCOMMENTS_MISSING' ), 1 );
			}
			?></div></fieldset><?php endif; ?><?php if (empty($skip_types_fieldset)): ?><fieldset class="fc-board-set"><legend class="fc-board-header"><?php echo JText::_( 'FLEXI_NAV_SD_TYPES_N_FIELDS' );?></legend><div class="fc-board-set-inner"><?php
			$add_sep = false;
			if ($this->perms->CanTypes)
			{
				if (!isset($sbtns['types'])) {
					$link = 'index.php?option='.$option.'&amp;view=types';
					FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-types.png', JText::_( 'FLEXI_TYPES' ) );
					$add_sep = true;
				}
				if (!isset($sbtns['addtype'])) {
					$link = 'index.php?option='.$option.'&amp;view=type';
					FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-type-add.png', JText::_( 'FLEXI_NEW_TYPE' ) );
					$add_sep = true;
				}
			}
			if ($this->perms->CanFields)
			{
				if (!isset($sbtns['fields'])) {
					$link = 'index.php?option='.$option.'&amp;view=fields';
					FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-fields.png', JText::_( 'FLEXI_FIELDS' ) );
					$add_sep = true;
				}
				if (!isset($sbtns['addfield'])) {
					$link = 'index.php?option='.$option.'&amp;view=field';
					FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-field-add.png', JText::_( 'FLEXI_NEW_FIELD' ) );
					$add_sep = true;
				}
				$addTagsBtns  = $this->perms->CanTags && (!isset($sbtns['tags']) || !isset($sbtns['addtag']));
				$addFilesBtns = $this->perms->CanFiles && !isset($sbtns['files']);
				if ($add_sep && ($addTagsBtns || $addFilesBtns))
					echo '<span class="fc-board-button_sep" style="float:'.($lang->isRTL() ? 'right' : 'left').'"></span>';
			}
			if ($this->perms->CanTags)
			{
				if (!isset($sbtns['tags'])) {
					$link = 'index.php?option='.$option.'&amp;view=tags';
					FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-tags.png', JText::_( 'FLEXI_TAGS' ) );
				}
				if (!isset($sbtns['addtag'])) {
					$link = 'index.php?option='.$option.'&amp;view=tag';
					FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-tag-add.png', JText::_( 'FLEXI_NEW_TAG' ) );
				}
			}
			if ($this->perms->CanFiles && !isset($sbtns['files']))
			{
				$link = 'index.php?option='.$option.'&amp;view=filemanager';
				FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-files.png', JText::_( 'FLEXI_FILEMANAGER' ) );
			}
			?></div></fieldset><?php endif; ?><?php if (empty($skip_contentviewing_fieldset)): ?><fieldset class="fc-board-set"><legend class="fc-board-header"><?php echo JText::_( 'FLEXI_NAV_SD_CONTENT_VIEWING' );?></legend><div class="fc-board-set-inner"><?php
			$add_sep = false;
			if ($this->perms->CanTemplates && !isset($sbtns['templates']))
			{
				$link = 'index.php?option='.$option.'&amp;view=templates';
				FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-templates.png', JText::_( 'FLEXI_TEMPLATES' ) );
				$add_sep = true;
			}
			if ($this->perms->CanIndex && !isset($sbtns['index']))
			{
				$link = 'index.php?option='.$option.'&amp;view=search';
				FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-searchindex.png', JText::_( 'FLEXI_SEARCH_INDEXES' ) );
				$add_sep = true;
			}
			if ($this->perms->CanStats && !isset($sbtns['stats']))
			{
				if ($add_sep)
					echo '<span class="fc-board-button_sep" style="float:'.($lang->isRTL() ? 'right' : 'left').'"></span>';
				
				$link = 'index.php?option='.$option.'&amp;view=stats';
				FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-statistics.png', JText::_( 'FLEXI_STATISTICS' ) );
			}
			?></div></fieldset><?php endif; ?><?php if (empty($skip_users_fieldset)): ?><fieldset class="fc-board-set"><legend class="fc-board-header"><?php echo JText::_( 'FLEXI_NAV_SD_USERS_N_GROUPS' );?></legend><div class="fc-board-set-inner"><?php
			if ($this->perms->CanAuthors)
			{
				if (!isset($sbtns['users'])) {
					$link = 'index.php?option='.$option.'&amp;view=users';
					FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-authors.png', JText::_( 'FLEXI_USERS' ) );
				}
				if (!isset($sbtns['adduser'])) {
					$link = 'index.php?option='.$option.'&amp;'.(FLEXI_J16GE ? 'task=users.add' : 'controller=users&amp;task=add');
					FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-author-add.png', JText::_( 'FLEXI_ADD_USER' ) );
				}
			}
			if ($this->perms->CanGroups)
			{
				if (!isset($sbtns['groups'])) {
					$link = 'index.php?option='.$option.'&amp;view=groups';
					FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-groups.png', JText::_( 'FLEXI_GROUPS' ) );
				}
				if (!isset($sbtns['addgroup'])) {
					$link = 'index.php?option='.$option.'&amp;'.(FLEXI_J16GE ? 'task=groups.add' : 'controller=groups&amp;task=add');
					FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-groups-add.png', JText::_( 'FLEXI_ADD_GROUP' ) );
				}
			}
			?></div></fieldset><?php endif; ?><?php if (empty($skip_expert_fieldset)): ?><fieldset class="fc-board-set"><legend class="fc-board-header"><?php echo JText::_( 'FLEXI_NAV_SD_EXPERT_USAGE' );?></legend><div class="fc-board-set-inner"><?php
			$add_sep = false;
			if ($this->perms->CanImport && !isset($sbtns['import']))
			{
				$link = 'index.php?option='.$option.'&amp;view=import';
				FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-import.png', JText::_( 'FLEXI_IMPORT' ) );
				$add_sep = true;	
			}
			if ($this->perms->CanPlugins)
			{
				if ($add_sep && (!isset($sbtns['plgfields']) || !isset($sbtns['plgsystem']) || !isset($sbtns['plgflexicontent'])))
					echo '<span class="fc-board-button_sep" style="float:'.($lang->isRTL() ? 'right' : 'left').'"></span>';
				
				if (!isset($sbtns['plgfields'])) {
					$link = 'index.php?option=com_plugins&amp;filter_folder=flexicontent_fields';
					FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-plugins.png', JText::_( 'FLEXI_PLUGINS' ). ' - Fields', 1 );
				}
				if (!isset($sbtns['plgsystem'])) {
					$link = 'index.php?option=com_plugins&amp;filter_folder=system&amp;filter_search=flexi';
					FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-plugins.png', JText::_( 'FLEXI_PLUGINS' ). ' - System', 1 );
				}
				if (!isset($sbtns['plgflexicontent'])) {
					$link = 'index.php?option=com_plugins&amp;filter_folder=flexicontent';
					FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-plugins.png', JText::_( 'FLEXI_PLUGINS' ). ' - Flexicontent', 1 );
				}
			}
			if ( $this->perms->CanEdit )
			{
				//$link = 'index.php?option=com_content&amp;view=featured';
				//if (!isset($sbtns['featured'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-featured.png', JText::_( 'FLEXI_FEATURED' ), 1 );
			}
			?></div></fieldset><?php endif; ?><?php if ($this->params->get('support_url')): ?><fieldset class="fc-board-set"><legend class="fc-board-header"><?php echo JText::_( 'FLEXI_HELP' );?></legend><div class="fc-board-set-inner"><?php
			if ($this->params->get('support_url'))
			{
				$link = $this->params->get('support_url');
				FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-help.png', JText::_( 'FLEXI_SUPPORT' ), 1 );
			}
			?></div></fieldset><?php endif; ?><?php
		}
		?>
		

		<?php
		if ( $this->params->get('show_updatecheck', 1) && $user->authorise('core.admin', 'com_flexicontent') )
		{
			$this->document->addScriptDeclaration("
			jQuery(document).ready(function () {
				if(jQuery.trim(jQuery('#displayfversion').html())=='') {
					jQuery('#displayfversion').html('<p><img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\"></p>');
					jQuery.ajax({
						url: 'index.php?option=com_flexicontent&task=fversioncompare&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1',
						success: function(str) {
							jQuery('#displayfversion').html(str);
							jQuery('#displayfversion').parent().css('height', 'auto');
						}
					});
				}
			});
			");
			echo '
			<fieldset class="fc-board-set">
				<legend class="fc-board-header-content-editing">'.JText::_( 'FLEXI_UPDATE_CHECK' ).'</legend>
				<div class="fc-board-set-inner">
					<div id="displayfversion" style="float: left;"></div>
				</div>
			</fieldset>
			';
		}
		?>
		
		</div> <!-- END OF #fc-dash-boardbtns -->
		
		
		<?php
		if (!$this->dopostinstall || !$this->allplgpublish) :
			// Make sure POST-INSTALLATION Task slider is open
			echo JHtml::_('sliders.start', 'fc-dash-sliders', array('useCookie'=>0, 'show'=>0, 'display'=>0, 'startOffset'=>0));
		elseif (!$skip_sliders) :
			echo JHtml::_('sliders.start', 'fc-dash-sliders', array('useCookie'=>1, 'show'=>-1, 'display'=>-1, 'startOffset'=>-1));
		endif;
		?>
		
		<?php if (!$this->dopostinstall || !$this->allplgpublish) : ?>
			<?php
			$title = JText::_( 'FLEXI_POST_INSTALL' );
			echo JHtml::_('sliders.panel', $title, 'postinstall' );
			echo $this->loadTemplate('postinstall');
			?>
		<?php endif; ?>
		
		<?php if (!$skip_sliders && $config_saved) : ?>
			<?php ob_start(); ?>
			<?php
				echo JHtml::_('sliders.panel', $_title, 'requirements' );
				echo '<table class="fc-table-list">';
				foreach($php_lims as $type => $html) {
					echo '<tr><td>'.implode('<br/>', $html).'</td></tr>';
				}
				echo '</table>';
			?>
			<?php $fc_requirements = ob_get_clean(); ?>
			
			
			<?php if ( isset($php_lims['warning']) ) : /* Place requirements at top slider if they are failing */ ?>
			<?php echo $fc_requirements; ?>
			<?php endif; ?>
			
			
			<?php if (!isset($ssliders['pending'])): ?>
			<?php
			$title = JText::_( 'FLEXI_PENDING_SLIDER' ).' - <span class="badge badge-warning">'.$this->totalrows['pending'].'</span>';
			echo JHtml::_('sliders.panel', $title, 'pending' );
			$show_all_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_state=PE';
			?>
			<table class="fc-table-list">
				
				<thead>
				<tr>
					<th><?php echo JText::_('FLEXI_TITLE'); ?></th>
					<th><?php echo JText::_('FLEXI_CREATED'); ?></th>
					<th><?php echo JText::_('FLEXI_AUTHOR'); ?></th>
				</tr>
				</thead>
				
				<tbody>
				<?php
				$k = 0;
				$n = count($this->pending);
				for ($i=0, $n; $i < $n; $i++) {
					$row = $this->pending[$i];
					$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $row->id);
					$canEdit 		= in_array('edit', $rights);
					$canEditOwn	= in_array('edit.own', $rights) && $row->created_by == $user->id;
					$link = 'index.php?option=com_flexicontent&amp;'.$items_task.'edit&amp;cid='. $row->id;
				?>
				<tr>
					<td>
					<?php
					if ((!$canEdit) && (!$canEditOwn)) {
						echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8');
					} else {
					?>
						<?php echo ($i+1).". "; ?>
						<a href="<?php echo $link; ?>" title="<?php echo $edit_item_txt; ?>" <?php echo $onclick_modal_edit; ?>>
							<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
						</a>
					<?php
					}
					?>
					</td>
					<td><?php echo JHTML::_('date',  $row->created); ?></td>
					<td><?php echo $row->creator; ?></td>
				</tr>
				<?php $k = 1 - $k; } ?>
				<?php if (count($this->pending) < $this->totalrows['pending']) : ?>
				<tr>
					<td colspan="3" style="padding-top:0!important;"> ... </td>
				</tr>
				<?php endif; ?>
				</tbody>
				
				<tfoot>
				<tr>
					<td colspan="3">
						<?php echo '<span class="'.$btn_class.'" onclick="window.open(\''.$show_all_link.'\')" >'.JText::_('FLEXI_ITEMS_MANAGER').'</span><br/>'; ?>
					</td>
				</tr>
				</tfoot>
			</table>
			<?php endif; /* !isset($ssliders['pending']) */ ?>
			
			<?php if (!isset($ssliders['revised'])): ?>
			<?php
			$title = JText::_( 'FLEXI_REVISED_VER_SLIDER' ).' - <span class="badge badge-warning">'.$this->totalrows['revised'].'</span>';
			echo JHtml::_('sliders.panel', $title, 'revised' );
			$show_all_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_state=RV';
			?>
			<table class="fc-table-list">
				
				<thead>
				<tr>
					<th><?php echo JText::_('FLEXI_TITLE'); ?></th>
					<th><?php echo JText::_('FLEXI_MODIFIED'); ?></th>
					<th><?php echo JText::_('FLEXI_NF_MODIFIER'); ?></th>
				</tr>
				</thead>
				
				<tbody>
				<?php
				$k = 0;
				$n = count($this->revised);
				for ($i=0, $n; $i < $n; $i++) {
					$row = $this->revised[$i];
					$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $row->id);
					$canEdit 		= in_array('edit', $rights);
					$canEditOwn	= in_array('edit.own', $rights) && $row->created_by == $user->id;
					$link = 'index.php?option=com_flexicontent&amp;'.$items_task.'edit&amp;cid='. $row->id;
				?>
				<tr>
					<td>
					<?php
					if ((!$canEdit) && (!$canEditOwn)) {
						echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8');
					} else {
					?>
						<?php echo ($i+1).". "; ?>
						<a href="<?php echo $link; ?>" title="<?php echo $edit_item_txt; ?>" <?php echo $onclick_modal_edit; ?>>
							<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
						</a>
					<?php
					}
					?>
					</td>
					<td><?php echo JHTML::_('date',  $row->modified); ?></td>
					<td><?php echo $row->modifier; ?></td>
				</tr>
				<?php $k = 1 - $k; } ?>
				<?php if (count($this->revised) < $this->totalrows['revised']) : ?>
				<tr>
					<td colspan="3" style="padding-top:0!important;"> ... </td>
				</tr>
				<?php endif; ?>
				</tbody>
				
				<tfoot>
				<tr>
					<td colspan="3">
						<?php echo '<span class="'.$btn_class.'" onclick="window.open(\''.$show_all_link.'\')" >'.JText::_('FLEXI_ITEMS_MANAGER').'</span><br/>'; ?>
					</td>
				</tr>
				</tfoot>
			</table>
			<?php endif; /* !isset($ssliders['revised']) */ ?>
			
			<?php if (!isset($ssliders['inprogress'])): ?>
			<?php
			$title = JText::_( 'FLEXI_IN_PROGRESS_SLIDER' ).' - <span class="badge badge-info">'.$this->totalrows['inprogress'].'</span>';
			echo JHtml::_('sliders.panel', $title, 'inprogress' );
			$show_all_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_state=IP';
			?>
			<table class="fc-table-list">
				
				<thead>
				<tr>
					<th><?php echo JText::_('FLEXI_TITLE'); ?></th>
					<th><?php echo JText::_('FLEXI_CREATED'); ?></th>
					<th><?php echo JText::_('FLEXI_AUTHOR'); ?></th>
				</tr>
				</thead>
				
				<tbody>
				<?php
				$k = 0;
				$n = count($this->inprogress);
				for ($i=0, $n; $i < $n; $i++) {
					$row = $this->inprogress[$i];
					$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $row->id);
					$canEdit 		= in_array('edit', $rights);
					$canEditOwn	= in_array('edit.own', $rights) && $row->created_by == $user->id;
					$link = 'index.php?option=com_flexicontent&amp;'.$items_task.'edit&amp;cid='. $row->id;
			?>
				<tr>
					<td>
					<?php
					if ((!$canEdit) && (!$canEditOwn)) {
						echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8');
					} else {
					?>
						<?php echo ($i+1).". "; ?>
						<a href="<?php echo $link; ?>" title="<?php echo $edit_item_txt; ?>" <?php echo $onclick_modal_edit; ?>>
							<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
						</a>
					<?php
					}
					?>
					</td>
					<td><?php echo JHTML::_('date',  $row->created); ?></td>
					<td><?php echo $row->creator; ?></td>
				</tr>
				<?php $k = 1 - $k; } ?>
				<?php if (count($this->inprogress) < $this->totalrows['inprogress']) : ?>
				<tr>
					<td colspan="3" style="padding-top:0!important;"> ... </td>
				</tr>
				<?php endif; ?>
				</tbody>
				
				<tfoot>
				<tr>
					<td colspan="3">
						<?php echo '<span class="'.$btn_class.'" onclick="window.open(\''.$show_all_link.'\')" >'.JText::_('FLEXI_ITEMS_MANAGER').'</span><br/>'; ?>
					</td>
				</tr>
				</tfoot>
			</table>
			<?php endif; /* !isset($ssliders['inprogress']) */ ?>
			
			<?php if (!isset($ssliders['draft'])): ?>
			<?php
			$title = JText::_( 'FLEXI_DRAFT_SLIDER' ).' - <span class="badge badge-info">'.$this->totalrows['draft'].'</span>';
			echo JHtml::_('sliders.panel', $title, 'draft' );
			$show_all_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_state=OQ';
			?>
			<table class="fc-table-list">
				
				<thead>
				<tr>
					<th><?php echo JText::_('FLEXI_TITLE'); ?></th>
					<th><?php echo JText::_('FLEXI_CREATED'); ?></th>
					<th><?php echo JText::_('FLEXI_AUTHOR'); ?></th>
				</tr>
				</thead>
				
				<tbody>
			<?php
				$k = 0;
				$n = count($this->draft);
				for ($i=0, $n; $i < $n; $i++) {
					$row = $this->draft[$i];
					$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $row->id);
					$canEdit 		= in_array('edit', $rights);
					$canEditOwn	= in_array('edit.own', $rights) && $row->created_by == $user->id;
					$link = 'index.php?option=com_flexicontent&amp;'.$items_task.'edit&amp;cid='. $row->id;
			?>
				<tr>
					<td>
					<?php
					if ((!$canEdit) && (!$canEditOwn)) {
						echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8');
					} else {
					?>
						<?php echo ($i+1).". "; ?>
						<a href="<?php echo $link; ?>" title="<?php echo $edit_item_txt; ?>" <?php echo $onclick_modal_edit; ?>>
							<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
						</a>
					<?php
					}
					?>
					</td>
					<td><?php echo JHTML::_('date',  $row->created); ?></td>
					<td><?php echo $row->creator; ?></td>
				</tr>
				<?php $k = 1 - $k; } ?>
				<?php if (count($this->draft) < $this->totalrows['draft']) : ?>
				<tr>
					<td colspan="3" style="padding-top:0!important;"> ... </td>
				</tr>
				<?php endif; ?>
				</tbody>
				
				<tfoot>
				<tr>
					<td colspan="3">
						<?php echo '<span class="'.$btn_class.'" onclick="window.open(\''.$show_all_link.'\')" >'.JText::_('FLEXI_ITEMS_MANAGER').'</span><br/>'; ?>
					</td>
				</tr>
				</tfoot>
			</table>
			<?php endif; /* !isset($ssliders['draft']) */ ?>
			
			<?php if (!isset($ssliders['version'])): ?>
			<?php
			if ( ! $this->params->get('show_updatecheck', 1) || !$user->authorise('core.admin', 'com_flexicontent') )
			{
				$this->document->addScriptDeclaration("
				jQuery(document).ready(function () {
					jQuery('#updatecomponent').click(function(e){
						if(jQuery.trim(jQuery('#displayfversion').html())=='') {
							jQuery('#displayfversion').html('<p><img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\"></p>');
							jQuery.ajax({
								url: 'index.php?option=com_flexicontent&task=fversioncompare&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1',
								success: function(str) {
									jQuery('#displayfversion').html(str);
									jQuery('#displayfversion').parent().css('height', 'auto');
								}
							});
						}
					});
				});
				");
				echo JHtml::_('sliders.panel', JText::_( 'FLEXI_VERSION_CHECKING' ), 'updatecomponent' );
				echo "<div id=\"displayfversion\" style='min-height:20px;'></div>";
			}
			?>
			<?php endif; /* !isset($ssliders['version']) */ ?>
			
			
			<?php ob_start(); ?>
			<div id="fc-dash-credits">
			<?php echo !$hide_fc_license_credits ? '<fieldset class="fc-board-set"><legend class="fc-board-header-content-editing">'.JText::_( 'About FLEXIcontent' ).'</legend>' : ''; ?>
				<div class="fc-board-set-inner">
				<?php
					$logo_style = ';';
					if (!$disable_fc_logo) echo (FLEXI_J16GE ?
						JHTML::image('administrator/components/com_flexicontent/assets/images/logo.png', 'FLEXIcontent', ' id="fc-dash-logo" ') :
						JHTML::_('image.site', 'logo.png', '../administrator/components/com_flexicontent/assets/images/', NULL, NULL, 'FLEXIcontent', ' id="fc-dash-logo" '));
				?>
					<span id="fc-dash-license" class="nowrap_box fc-mssg-inline fc-info fc-nobgimage" style="">
						FLEXIcontent <?php echo FLEXI_VERSION . ' ' . FLEXI_RELEASE; ?><br/> GNU/GPL licence, Copyright &copy; 2009-2016
					</span><br/><br/>
					<span id="fc-dash-devs" class="nowrap_box">
						<span class="fc-mssg-inline fc-nobgimage fc-noborder nowrap_box" style="text-align:center;" >
							<span class="label label-info <?php echo $tooltip_class;?>" title="Core developer">Emmanuel Danan</span>
							<span class="label label-info <?php echo $tooltip_class;?>" title="Core developer">Georgios Papadakis</span><br/><br/>
							<a class="<?php echo $btn_class.(FLEXI_J16GE ? ' btn-primary ' : ' ').$tooltip_class;?>" style="" href="http://www.flexicontent.org" title="FLEXIcontent home page" target="_blank">FLEXIcontent.org</a>
						</span>
						
						<span class="fc-mssg-inline fc-nobgimage fc-noborder nowrap_box">
							<span class="label label-info <?php echo $tooltip_class;?>" title="Core Developer">Marvelic Engine</span><br/><br/>
							<a class="<?php echo $btn_class.(FLEXI_J30GE ? ' btn-small ' : ' fcsmall fcsimple ').$tooltip_class;?>" style="" href="http://www.marvelic.co.th" target="_blank" title="<?php echo flexicontent_html::getToolTip("Marvelic Engine", "Marvelic Engine is a Joomla consultancy based in Bangkok, Thailand. Support services include consulting, Joomla implementation, training, and custom extensions development.", 0, 1); ?>">
								marvelic.co.th
							</a>
						</span>
						
						<span class="fc-mssg-inline fc-nobgimage fc-noborder nowrap_box">
							<span class="label <?php echo $tooltip_class;?>" title="Core Developer">Suriya Kaewmungmuang</span>
						</span>
						
						<span class="fc-mssg-inline fc-nobgimage fc-noborder nowrap_box">
							<span class="label <?php echo $tooltip_class;?>" title="Core Developer">Yannick Berges</span><br/><br/>
							<a class="<?php echo $btn_class.(FLEXI_J30GE ? ' btn-small ' : ' fcsmall fcsimple ').$tooltip_class;?>" style="" href="http://com3elles.com/" target="_blank" title="<?php echo flexicontent_html::getToolTip("Com'3Elles", "Com'3Elles, agence de communication, conseil et formations", 0, 1); ?>">
								com3elles.com
							</a>
						</span>
						
						<span class="fc-mssg-inline fc-nobgimage fc-noborder nowrap_box">
							<span class="label <?php echo $tooltip_class;?>" title="Developer / Contributor">Ruben Reyes</span><br/><br/>
							<a class="<?php echo $btn_class.(FLEXI_J30GE ? ' btn-small ' : ' fcsmall fcsimple ').$tooltip_class;?>" style="" href="http://www.lyquix.com" target="_blank" title="<?php echo flexicontent_html::getToolTip("Lyquix", "Lyquix - Philadelphia Marketing, Advertising, Web Design and Development Agency", 0, 1); ?>">
								lyquix.com
							</a>
						</span>
					</span>
					
				</div>
			<?php echo !$hide_fc_license_credits ? '</fieldset>' : ''; ?>
			
			</div>
			<?php $fc_logo_license = ob_get_clean(); ?>
			
			<?php
			// Place PHP/DB requirements at bottom if no warning found
			if ( !isset($php_lims['warning']) ) :
				echo $fc_requirements;
			endif;
			?>
			
			<?php
			if ($hide_fc_license_credits) :
				echo JHtml::_('sliders.panel', "About FLEXIcontent", 'aboutflexi' );
				echo $fc_logo_license;
			endif;
			?>
			
			<?php echo JHtml::_('sliders.end'); ?>
			
		<?php endif; /* !$skip_sliders */ ?>
		
		
		<?php if (!$hide_fc_license_credits) echo $fc_logo_license; ?>
		
		<input type="hidden" name="option" value="com_flexicontent" />
		<input type="hidden" name="controller" value="" />
		<input type="hidden" name="view" value="" />
		<input type="hidden" name="task" value="" />
		<?php echo JHTML::_( 'form.token' ); ?>
		
	<!-- fc_perf -->
	</div>  <!-- sidebar -->
</form>
</div><!-- #flexicontent end -->