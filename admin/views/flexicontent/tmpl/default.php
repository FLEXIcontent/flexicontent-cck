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
$skip_content_fieldset = isset($sbtns['items']) && isset($sbtns['additem']) && isset($sbtns['cats']) && isset($sbtns['addcat']);
$skip_types_fieldset = isset($sbtns['types']) && isset($sbtns['addtype']) && isset($sbtns['fields']) && isset($sbtns['addfield']) && isset($sbtns['tags']) && isset($sbtns['addtag']) && isset($sbtns['files']);
$skip_contentviewing_fieldset = isset($sbtns['templates']) && isset($sbtns['stats']);
$skip_users_fieldset = isset($sbtns['users']) && isset($sbtns['adduser']) && isset($sbtns['groups']) && isset($sbtns['addgroup']);
$skip_expert_fieldset = isset($sbtns['import']) && isset($sbtns['index']) && isset($sbtns['plugins']) && isset($sbtns['comments']);

// disable dashboard sliders
$dashboard_sliders_disable = $this->params->get('dashboard_sliders_disable', array());
$dashboard_sliders_disable  = FLEXIUtilities::paramToArray($dashboard_sliders_disable);

// Other options
$modal_item_edit = $this->params->get('dashboard_modal_item_edit', 1);
$onclick_modal_edit = $modal_item_edit ? 'onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\'); return false;"' : '';
$disable_fc_logo = $this->params->get('dashboard_disable_fc_logo', 0);
$hide_fc_license = $this->params->get('dashboard_hide_fc_license', 1); /* hide inside sliders */

$ssliders = array_flip($dashboard_sliders_disable);
$skip_sliders = isset($sbtns['pending']) && isset($sbtns['revised']) && isset($sbtns['inprogress']) && isset($sbtns['draft']) && isset($sbtns['version']);
$skip_sliders = $skip_sliders && $this->dopostinstall && $this->allplgpublish && !$hide_fc_license;

// ensures the PHP version is correct
if (version_compare(PHP_VERSION, '5.0.0', '<'))
{
	echo '<div class="fc-mssg fc-error">';
	echo JText::_( 'FLEXI_UPGRADE_PHP' ) . '<br/>';
	echo '</div>';
	return false;
}

$ctrl = FLEXI_J16GE ? 'items.' : '';
$items_task = FLEXI_J16GE ? 'task=items.' : 'controller=items&amp;task=';
?>

<div class="flexicontent">
<form action="index.php" method="post" name="adminForm" id="adminForm">

<?php if (!empty( $this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo $this->sidebar; ?>
	</div>
	<div id="j-main-container" class="span10">
<?php else : ?>
	<div id="j-main-container">
<?php endif;?>

	<table cellspacing="0" cellpadding="0" border="0" width="">
		<tr>
			<td valign="top">
				<div id="fc-dash-boardbtns">
				<?php
				$config_not_saved =
					(!FLEXI_J16GE && !$this->params->get('flexi_section')) ||
					(FLEXI_J16GE && !$this->params->get('flexi_cat_extension'));
				if (!$this->dopostinstall)
				{
					echo '<div class="fc-mssg fc-warning">';
					echo JText::_( 'FLEXI_DO_POSTINSTALL' );
					echo '</div>';
				}
				else if ( !$this->existmenu || !$this->existcat || $config_not_saved )
				{
					echo '<div class="fc-mssg fc-warning">';
					if ( $config_not_saved )
					{
						if ( FLEXI_J16GE ) {
							$session = JFactory::getSession();
							$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
							$_width = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
							$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
							$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
							$conf_link = 'index.php?option=com_config&view=component&component=com_flexicontent&path=';
							$conf_link = FLEXI_J30GE ? "<a href='".$conf_link."' style='color: red;'>" :
								"<a class='modal' rel=\"{handler: 'iframe', size: {x: ".$_width.", y: ".$_height."}, onClose: function() {}}\" href='".$conf_link."&tmpl=component' style='color: red;'>" ;
							$msg = JText::sprintf( 'FLEXI_CONFIGURATION_NOT_SAVED', $conf_link.JText::_("FLEXI_CONFIG")."</a>" );
						} else {
							$msg = str_replace('"_QQ_"', '"', JText::_( 'FLEXI_NO_SECTION_CHOOSEN' ));
						}
						echo $msg . '<br/>';
					}
					else if (!$this->existcat)	echo JText::_( 'FLEXI_NO_CATEGORIES_CREATED' );
					else if (!$this->existmenu)	echo JText::_( 'FLEXI_NO_MENU_CREATED' );
					echo '</div>';
				}

				if ($this->dopostinstall) {
					?><?php if (empty($skip_content_fieldset)): ?><fieldset class="fc-board-set"><legend class="fc-board-header-content-editing"><?php echo JText::_( 'FLEXI_NAV_SD_CONTENT_EDITING' );?></legend><div class="fc-board-set-inner"><?php
					$link = 'index.php?option='.$option.'&amp;view=items';
					if (!isset($sbtns['items'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-items.png', JText::_( 'FLEXI_ITEMS' ) );
					if ($this->perms->CanAdd)
					{
						//$link = 'index.php?option='.$option.'&amp;view=item';
						$link = 'index.php?option='.$option.'&amp;view=types&amp;format=raw';
						if (!isset($sbtns['additem'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-item-add.png', JText::_( 'FLEXI_NEW_ITEM' ), 1, 1, 600, 450 );
					}
					/*if ($this->perms->CanArchives)
					{
						$link = 'index.php?option='.$option.'&amp;view=archive';
						FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-archive.png', JText::_( 'FLEXI_ARCHIVE' ) );
					}*/
					if ($this->perms->CanCats)
					{
						$link = 'index.php?option='.$option.'&amp;view=categories';
						if (!isset($sbtns['cats'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-categories.png', JText::_( 'FLEXI_CATEGORIES' ) );
						$CanAddCats = FLEXI_J16GE ? $this->perms->CanAdd : $this->perms->CanAddCats;
						if ($CanAddCats)
						{
							$link = 'index.php?option='.$option.'&amp;view=category';
							if (!isset($sbtns['addcat'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-category-add.png', JText::_( 'FLEXI_NEW_CATEGORY' ) );
						}
					}
					?><div></fieldset><?php endif; ?><?php if (empty($skip_types_fieldset)): ?><fieldset class="fc-board-set"><legend class="fc-board-header"><?php echo JText::_( 'FLEXI_NAV_SD_TYPES_N_FIELDS' );?></legend><div class="fc-board-set-inner"><?php
					if ($this->perms->CanTypes)
					{
						$link = 'index.php?option='.$option.'&amp;view=types';
						if (!isset($sbtns['types'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-types.png', JText::_( 'FLEXI_TYPES' ) );
						$link = 'index.php?option='.$option.'&amp;view=type';
						if (!isset($sbtns['addtype'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-type-add.png', JText::_( 'FLEXI_NEW_TYPE' ) );
					}
					if ($this->perms->CanFields)
					{
						$link = 'index.php?option='.$option.'&amp;view=fields';
						if (!isset($sbtns['fields'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-fields.png', JText::_( 'FLEXI_FIELDS' ) );
						$link = 'index.php?option='.$option.'&amp;view=field';
						if (!isset($sbtns['addfield'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-field-add.png', JText::_( 'FLEXI_NEW_FIELD' ) );
					}
					if ($this->perms->CanTags)
					{
						$link = 'index.php?option='.$option.'&amp;view=tags';
						if (!isset($sbtns['tags'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-tags.png', JText::_( 'FLEXI_TAGS' ) );
						$link = 'index.php?option='.$option.'&amp;view=tag';
						if (!isset($sbtns['addtag'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-tag-add.png', JText::_( 'FLEXI_NEW_TAG' ) );
					}
					if ($this->perms->CanFiles)
					{
						$link = 'index.php?option='.$option.'&amp;view=filemanager';
						if (!isset($sbtns['files'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-files.png', JText::_( 'FLEXI_FILEMANAGER' ) );
					}
					?><div></fieldset><?php endif; ?><?php if (empty($skip_contentviewing_fieldset)): ?><fieldset class="fc-board-set"><legend class="fc-board-header"><?php echo JText::_( 'FLEXI_NAV_SD_CONTENT_VIEWING' );?></legend><div class="fc-board-set-inner"><?php
					if ($this->perms->CanTemplates)
					{
						$link = 'index.php?option='.$option.'&amp;view=templates';
						if (!isset($sbtns['templates'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-templates.png', JText::_( 'FLEXI_TEMPLATES' ) );
					}
					if ($this->perms->CanStats)
					{
						$link = 'index.php?option='.$option.'&amp;view=stats';
						if (!isset($sbtns['stats'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-statistics.png', JText::_( 'FLEXI_STATISTICS' ) );
					}
					?><div></fieldset><?php endif; ?><?php if (empty($skip_users_fieldset)): ?><fieldset class="fc-board-set"><legend class="fc-board-header"><?php echo JText::_( 'FLEXI_NAV_SD_USERS_N_GROUPS' );?></legend><div class="fc-board-set-inner"><?php
					if ($this->perms->CanAuthors)
					{
						$link = 'index.php?option='.$option.'&amp;view=users';
						if (!isset($sbtns['users'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-authors.png', JText::_( 'FLEXI_USERS' ) );
						$link = 'index.php?option='.$option.'&amp;'.(FLEXI_J16GE ? 'task=users.add' : 'controller=users&amp;task=add');
						if (!isset($sbtns['adduser'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-author-add.png', JText::_( 'FLEXI_ADD_USER' ) );
					}
					if ($this->perms->CanGroups)
					{
						$link = 'index.php?option='.$option.'&amp;view=groups';
						if (!isset($sbtns['groups'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-groups.png', JText::_( 'FLEXI_GROUPS' ) );
						$link = 'index.php?option='.$option.'&amp;'.(FLEXI_J16GE ? 'task=groups.add' : 'controller=groups&amp;task=add');
						if (!isset($sbtns['addgroup'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-groups-add.png', JText::_( 'FLEXI_ADD_GROUP' ) );
					}
					?><div></fieldset><?php endif; ?><?php if (empty($skip_expert_fieldset)): ?><fieldset class="fc-board-set"><legend class="fc-board-header"><?php echo JText::_( 'FLEXI_NAV_SD_EXPERT_USAGE' );?></legend><div class="fc-board-set-inner"><?php
					if ($this->perms->CanImport)
					{
						$link = 'index.php?option='.$option.'&amp;view=import';
						if (!isset($sbtns['import'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-import.png', JText::_( 'FLEXI_IMPORT' ) );
					}
					if ($this->perms->CanIndex)
					{
						$link = 'index.php?option='.$option.'&amp;view=search';
						if (!isset($sbtns['index'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-searchindex.png', JText::_( 'FLEXI_SEARCH_INDEXES' ) );
					}
					if ($this->perms->CanPlugins)
					{
						$link = 'index.php?option=com_plugins&amp;filter_type=flexicontent_fields&amp;tmpl=component';
						if (!isset($sbtns['plugins'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-plugins.png', JText::_( 'FLEXI_PLUGINS' ), 1 );
					}
					if ( $this->params->get('comments')==1 && $this->perms->CanComments)
					{
						$link = 'index.php?option=com_jcomments&amp;task=view&amp;fog=com_flexicontent&amp;tmpl=component';
						if (!isset($sbtns['comments'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-comments.png', JText::_( 'FLEXI_COMMENTS' ), 1 );
					}
					if ( FLEXI_J16GE && $this->perms->CanEdit )
					{
						//$link = 'index.php?option=com_content&amp;view=featured&amp;tmpl=component';
						//if (!isset($sbtns['archives'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-featured.png', JText::_( 'FLEXI_FEATURED' ), 1 );
					}
					if (FLEXI_ACCESS)
					{
						if ($this->perms->CanRights)
						{
							$link = 'index.php?option=com_flexiaccess';
							FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-permissions.png', JText::_( 'FLEXI_EDIT_ACL' ) );
						}
					}
					if ($this->params->get('support_url'))
					{
						$link = $this->params->get('support_url');
						FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-help.png', JText::_( 'FLEXI_SUPPORT' ), 1 );
					}
					?><div></fieldset><?php endif; ?><?php
				}
				?>
				</div>
			
	
				<?php if ($this->dopostinstall && $this->allplgpublish) : ?>
				<?php /*POST-INSTALL OK*/ ?>
				<?php endif; ?>
				
				<?php if (!$skip_sliders) :
					echo FLEXI_J16GE ? JHtml::_('sliders.start', 'fc-dash-sliders', array('useCookie'=>1, 'show'=>-1, 'display'=>-1, 'startOffset'=>-1)) : $this->pane->startPane( 'stat-pane' );
					?>
				
					<?php if (!$this->dopostinstall || !$this->allplgpublish) : ?>
					<?php
					$title = JText::_( 'FLEXI_POST_INSTALL' );
					echo FLEXI_J16GE ?
						JHtml::_('sliders.panel', $title, 'postinstall' ) :
						$this->pane->startPanel( $title, 'postinstall' );
					echo $this->loadTemplate('postinstall');
					echo FLEXI_J16GE ? '' : $this->pane->endPanel();
					?>
					<?php endif; ?>
				
					<?php if (!isset($ssliders['pending'])): ?>
					<?php
					$title = JText::_( 'FLEXI_PENDING_SLIDER' )." (".count($this->pending)."/".$this->totalrows['pending'].")";
					echo FLEXI_J16GE ? JHtml::_('sliders.panel', $title, 'pending' ) : $this->pane->startPanel( $title, 'pending' );
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
						<?php
						$k = 0;
						$n = count($this->pending);
						for ($i=0, $n; $i < $n; $i++) {
							$row = $this->pending[$i];
							if (FLEXI_J16GE) {
								$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $row->id);
								$canEdit 		= in_array('edit', $rights);
								$canEditOwn	= in_array('edit.own', $rights) && $row->created_by == $user->id;
							} else if (FLEXI_ACCESS) {
								$rights = FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $row->id, $row->catid);
								$canEdit 		= in_array('edit', $rights) || ($user->gid > 24);
								$canEditOwn		= (in_array('editown', $rights) && ($row->created_by == $user->id)) || ($user->gid > 24);
							} else {
								$canEdit	= 1;
								$canEditOwn	= 1;
							}
						$link = 'index.php?option=com_flexicontent&amp;'.$items_task.'edit&amp;cid[]='. $row->id;
				?>
						<tbody>
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
						</tbody>
						<tfoot>
						</tr>
							<td colspan="3">
								<?php echo '<span class="'.$btn_class.'" onclick="window.open(\''.$show_all_link.'\')" >Show All</span><br/>'; ?>
							</td>
						</tr>
						</tfoot>
					</table>
					<?php echo FLEXI_J16GE ? '' : $this->pane->endPanel(); ?>
					<?php endif; /* !isset($ssliders['pending']) */ ?>
					
					<?php if (!isset($ssliders['revised'])): ?>
					<?php
					$title = JText::_( 'FLEXI_REVISED_VER_SLIDER' )." (".count($this->revised)."/".$this->totalrows['revised'].")";
					echo FLEXI_J16GE ? JHtml::_('sliders.panel', $title, 'revised' ) : $this->pane->startPanel( $title, 'revised' );
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
						<?php
						$k = 0;
						$n = count($this->revised);
						for ($i=0, $n; $i < $n; $i++) {
							$row = $this->revised[$i];
							if (FLEXI_J16GE) {
								$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $row->id);
								$canEdit 		= in_array('edit', $rights);
								$canEditOwn	= in_array('edit.own', $rights) && $row->created_by == $user->id;
							} else if (FLEXI_ACCESS) {
								$rights = FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $row->id, $row->catid);
								$canEdit 		= in_array('edit', $rights) || ($user->gid > 24);
								$canEditOwn		= (in_array('editown', $rights) && ($row->created_by == $user->id)) || ($user->gid > 24);
							} else {
								$canEdit	= 1;
								$canEditOwn	= 1;
							}
							$link = 'index.php?option=com_flexicontent&amp;'.$items_task.'edit&amp;cid[]='. $row->id;
					?>
						<tbody>
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
						</tbody>
						<tfoot>
						</tr>
							<td colspan="3">
								<?php echo '<span class="'.$btn_class.'" onclick="window.open(\''.$show_all_link.'\')" >Show All</span><br/>'; ?>
							</td>
						</tr>
						</tfoot>
					</table>
					<?php echo FLEXI_J16GE ? '' : $this->pane->endPanel(); ?>
					<?php endif; /* !isset($ssliders['revised']) */ ?>
					
					<?php if (!isset($ssliders['inprogress'])): ?>
					<?php
					$title = JText::_( 'FLEXI_IN_PROGRESS_SLIDER' )." (".count($this->inprogress)."/".$this->totalrows['inprogress'].")";
					echo FLEXI_J16GE ?
						JHtml::_('sliders.panel', $title, 'inprogress' ) :
						$this->pane->startPanel( $title, 'inprogress' );
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
						<?php
						$k = 0;
						$n = count($this->inprogress);
						for ($i=0, $n; $i < $n; $i++) {
							$row = $this->inprogress[$i];
							if (FLEXI_J16GE) {
								$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $row->id);
								$canEdit 		= in_array('edit', $rights);
								$canEditOwn	= in_array('edit.own', $rights) && $row->created_by == $user->id;
							} else if (FLEXI_ACCESS) {
								$rights = FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $row->id, $row->catid);
								$canEdit 		= in_array('edit', $rights) || ($user->gid > 24);
								$canEditOwn		= (in_array('editown', $rights) && ($row->created_by == $user->id)) || ($user->gid > 24);
							} else {
								$canEdit	= 1;
								$canEditOwn	= 1;
							}
							$link = 'index.php?option=com_flexicontent&amp;'.$items_task.'edit&amp;cid[]='. $row->id;
					?>
						<tbody>
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
						</tbody>
						<tfoot>
						</tr>
							<td colspan="3">
								<?php echo '<span class="'.$btn_class.'" onclick="window.open(\''.$show_all_link.'\')" >Show All</span><br/>'; ?>
							</td>
						</tr>
						</tfoot>
					</table>
					<?php echo FLEXI_J16GE ? '' : $this->pane->endPanel(); ?>
					<?php endif; /* !isset($ssliders['inprogress']) */ ?>
					
					<?php if (!isset($ssliders['draft'])): ?>
					<?php
					$title = JText::_( 'FLEXI_DRAFT_SLIDER' )." (".count($this->draft)."/".$this->totalrows['draft'].")";
					echo FLEXI_J16GE ?
						JHtml::_('sliders.panel', $title, 'draft' ) : 
						$this->pane->startPanel( $title, 'draft' );
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
					<?php
						$k = 0;
						$n = count($this->draft);
						for ($i=0, $n; $i < $n; $i++) {
							$row = $this->draft[$i];
							if (FLEXI_J16GE) {
								$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $row->id);
								$canEdit 		= in_array('edit', $rights);
								$canEditOwn	= in_array('edit.own', $rights) && $row->created_by == $user->id;
							} else if (FLEXI_ACCESS) {
								$rights = FAccess::checkAllItemAccess('com_content', 'users', $user->gmid, $row->id, $row->catid);
								$canEdit 		= in_array('edit', $rights) || ($user->gid > 24);
								$canEditOwn		= (in_array('editown', $rights) && ($row->created_by == $user->id)) || ($user->gid > 24);
							} else {
								$canEdit	= 1;
								$canEditOwn	= 1;
							}
						$link = 'index.php?option=com_flexicontent&amp;'.$items_task.'edit&amp;cid[]='. $row->id;
					?>
						<tbody>
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
						</tbody>
						<tfoot>
						</tr>
							<td colspan="3">
								<?php echo '<span class="'.$btn_class.'" onclick="window.open(\''.$show_all_link.'\')" >Show All</span><br/>'; ?>
							</td>
						</tr>
						</tfoot>
					</table>
					<?php echo FLEXI_J16GE ? '' : $this->pane->endPanel(); ?>
					<?php endif; /* !isset($ssliders['draft']) */ ?>
					
					<?php if (!isset($ssliders['version'])): ?>
					<?php
					if($this->params->get('show_updatecheck', 1) == 1) {	 
						/*if(@$this->check['connect'] == 0) {
							$title = JText::_( 'FLEXI_CANNOT_CHECK_VERSION' );
						} else {
							if (@$this->check['current'] == 0 ) {	 
								$title = JText::_( 'FLEXI_VERSION_OK' );
							} else {
								$title = JText::_( 'FLEXI_NEW_VERSION' );
							}
						}*/
						$this->document->addScriptDeclaration("
						jQuery(document).ready(function () {
							jQuery('#updatecomponent').click(function(e){
								if(jQuery.trim(jQuery('#displayfversion').html())=='') {
									jQuery('#displayfversion').html('<p class=\"qf_centerimg\"><img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" align=\"center\"></p>');
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
						echo FLEXI_J16GE ?
							JHtml::_('sliders.panel', JText::_( 'FLEXI_VERSION_CHECKING' ), 'updatecomponent' ) :
							$this->pane->startPanel( JText::_( 'FLEXI_VERSION_CHECKING' ), 'updatecomponent' ); 
						echo "<div id=\"displayfversion\" style='min-height:20px;'></div>";
						echo FLEXI_J16GE ? '' : $this->pane->endPanel();
					}
					?>
					<?php endif; /* !isset($ssliders['version']) */ ?>
				
				<?php ob_start(); ?>
				<div id="fc-dash-credits">
				<?php echo !$hide_fc_license ? '<fieldset class="fc-board-set"><legend class="fc-board-header-content-editing">'.JText::_( 'About FLEXIcontent' ).'</legend>' : ''; ?>
					<div class="fc-board-set-inner">
					<?php
						$logo_style = ';';
						if (!$disable_fc_logo) echo (FLEXI_J16GE ?
							JHTML::image('administrator/components/com_flexicontent/assets/images/logo.png', 'FLEXIcontent', ' id="fc-dash-logo" ') :
							JHTML::_('image.site', 'logo.png', '../administrator/components/com_flexicontent/assets/images/', NULL, NULL, 'FLEXIcontent', ' id="fc-dash-logo" '));
					?>
						<span id="fc-dash-license" class="nowrap_box fc-mssg-inline fc-info fc-nobgimage" style="">
							FLEXIcontent <?php echo FLEXI_VERSION . ' ' . FLEXI_RELEASE; ?><br/> GNU/GPL licence, Copyright &copy; 2009-2015
						</span><br/><br/>
						<span id="fc-dash-devs" class="nowrap_box">
							<span class="fc-mssg-inline fc-nobgimage fc-mssg-inline-box nowrap_box" style="text-align:center;" >
								<span class="label label-info <?php echo $tooltip_class;?>" title="Core developer">Emmanuel Danan</span>
								<span class="label label-info <?php echo $tooltip_class;?>" title="Core developer">Georgios Papadakis</span><br/><br/>
								<a class="<?php echo $btn_class.(FLEXI_J16GE ? ' btn-primary ' : ' ').$tooltip_class;?>" style="" href="http://www.flexicontent.org" title="FLEXIcontent home page" target="_blank">FLEXIcontent.org</a>
							</span>
							
							<span class="fc-mssg-inline fc-nobgimage fc-mssg-inline-box nowrap_box">
								<span class="label <?php echo $tooltip_class;?>" title="Developer">Marvelic Engine</span><br/><br/>
								<a class="<?php echo $btn_class.(FLEXI_J30GE ? ' btn-small ' : ' fcsmall fcsimple ').$tooltip_class;?>" style="" href="http://www.marvelic.co.th" target="_blank" title="<?php echo flexicontent_html::getToolTip("Marvelic Engine", "Marvelic Engine is a Joomla consultancy based in Bangkok, Thailand. Support services include consulting, Joomla implementation, training, and custom extensions development.", 0, 1); ?>">
									marvelic.co.th
								</a>
							</span>
							
							<span class="fc-mssg-inline fc-nobgimage fc-mssg-inline-box nowrap_box">
								<span class="label <?php echo $tooltip_class;?>" title="Developer">Suriya Kaewmungmuang</span>
							</span>
							
							<span class="fc-mssg-inline fc-nobgimage fc-mssg-inline-box nowrap_box">
								<span class="label <?php echo $tooltip_class;?>" title="Developer">Ruben Reyes</span><br/><br/>
								<a class="<?php echo $btn_class.(FLEXI_J30GE ? ' btn-small ' : ' fcsmall fcsimple ').$tooltip_class;?>" style="" href="http://www.lyquix.com" target="_blank" title="<?php echo flexicontent_html::getToolTip("Lyquix", "Lyquix - Philadelphia Marketing, Advertising, Web Design and Development Agency", 0, 1); ?>">
									lyquix.com
								</a>
							</span>
						</span>
						
					</div>
				<?php echo !$hide_fc_license ? '</fieldset>' : ''; ?>
				
				</div>
				<?php $fc_logo_license = ob_get_clean(); ?>
				
				<?php
				if ($hide_fc_license) {
					echo FLEXI_J16GE ?
						JHtml::_('sliders.panel', "About FLEXIcontent", 'inprogress' ) :
						$this->pane->startPanel( $title, 'inprogress' );
					echo $fc_logo_license;
					echo FLEXI_J16GE ? '' : $this->pane->endPanel();
				}
				?>
				
				<?php echo FLEXI_J16GE ? JHtml::_('sliders.end') : $this->pane->endPane();?>
				<?php endif; /* !$skip_sliders */ ?>
				
				<?php if (!$hide_fc_license) echo $fc_logo_license; ?>
			</td>
		</tr>
	</table>
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="" />
	<input type="hidden" name="view" value="" />
	<input type="hidden" name="task" value="" />
	<?php echo JHTML::_( 'form.token' ); ?>
	
	</div>
</form>
</div>