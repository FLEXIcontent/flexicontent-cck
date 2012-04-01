<?php
/**
 * @version 1.5 stable $Id: default.php 363 2010-07-05 15:40:10Z Matthieu.26 $
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
$mainframe = &JFactory::getApplication();
$template	= $mainframe->getTemplate();
$option = JRequest::getVar('option');
// ensures the PHP version is correct
if (version_compare(PHP_VERSION, '5.0.0', '<'))
{
	echo '<div class="fc-error">';
	echo JText::_( 'FLEXI_UPGRADE_PHP' ) . '<br />';
	echo '</div>';
	return false;
}
$this->document->addScript('components/com_flexicontent/assets/js/jquery-1.6.2.min.js');
//$this->document->addCustomTag('<script>jQuery.noConflict();</script>');
?>
<form action="index.php" method="post" name="adminForm" id="adminForm">
	<table cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td valign="top">
			<table class="adminlist">
				<tr>
					<td>
						<div id="cpanel">
						<?php
						if (!$this->dopostinstall)  {
							echo '<div class="fc-error">';
							echo JText::_( 'FLEXI_DO_POSTINSTALL' );
							echo '</div>';
						}else if (!$this->existmenu || !$this->existcat || !$this->params->get('flexi_cat_extension')) {
							echo '<div class="fc-error">';
							if (!$this->params->get('flexi_cat_extension') || $this->params->get('flexi_cat_extension') == '') echo JText::sprintf( 'FLEXI_CONFIGURATION_NOT_SAVED', "<a class='modal' rel=\"{handler: 'iframe', size: {x: 850, y: 550}, onClose: function() {}}\" href='index.php?option=com_config&view=component&component=com_flexicontent&path=&tmpl=component' style='color: red;'>".JText::_("GLOBAL_CONFIGURATION")."</a>" ) . '<br />';
							else if (!$this->existcat)	echo JText::_( 'FLEXI_NO_CATEGORIES_CREATED' );
							else if (!$this->existmenu)	echo JText::_( 'FLEXI_NO_MENU_CREATED' );
							echo '</div>';
						}

						if ($this->dopostinstall) {
							$link = 'index.php?option='.$option.'&amp;view=items';
							FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-items.png', JText::_( 'FLEXI_ITEMS' ) );
						}
						if ($this->dopostinstall && $this->permission->CanAdd)
						{
							//$link = 'index.php?option='.$option.'&amp;view=item';
							$link = 'index.php?option='.$option.'&amp;view=types&amp;format=raw';
							FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-item-add.png', JText::_( 'FLEXI_NEW_ITEM' ), 1, 1 );
						}
						
						if ($this->dopostinstall && ($this->permission->CanCats || $this->permission->CanAddCats) )
						{
							if ($this->permission->CanCats)
							{
								$link = 'index.php?option='.$option.'&amp;view=categories';
								FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-categories.png', JText::_( 'FLEXI_CATEGORIES' ) );
							}
							$link = 'index.php?option='.$option.'&amp;view=category';
							FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-category-add.png', JText::_( 'FLEXI_NEW_CATEGORY' ) );
						}
						
						if ($this->dopostinstall && $this->permission->CanTypes)
						{
							$link = 'index.php?option='.$option.'&amp;view=types';
							FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-types.png', JText::_( 'FLEXI_TYPES' ) );
							$link = 'index.php?option='.$option.'&amp;view=type';
							FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-type-add.png', JText::_( 'FLEXI_NEW_TYPE' ) );
						}
						
						if ($this->dopostinstall && $this->permission->CanFields)
						{
							$link = 'index.php?option='.$option.'&amp;view=fields';
							FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-fields.png', JText::_( 'FLEXI_FIELDS' ) );
							$link = 'index.php?option='.$option.'&amp;view=field';
							FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-field-add.png', JText::_( 'FLEXI_NEW_FIELD' ) );
						}

						if ($this->dopostinstall && $this->permission->CanTags)
						{
							$link = 'index.php?option='.$option.'&amp;view=tags';
							FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-tags.png', JText::_( 'FLEXI_TAGS' ) );
							$link = 'index.php?option='.$option.'&amp;view=tag';
							FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-tag-add.png', JText::_( 'FLEXI_NEW_TAG' ) );
						}

						if ($this->dopostinstall && $this->permission->CanArchives)
						{
							$link = 'index.php?option='.$option.'&amp;view=archive';
							FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-archive.png', JText::_( 'FLEXI_ARCHIVE' ) );
						}

						if ($this->dopostinstall && $this->permission->CanFiles)
						{
							$link = 'index.php?option='.$option.'&amp;view=filemanager';
							FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-files.png', JText::_( 'FLEXI_FILEMANAGER' ) );
						}
						
						if ($this->dopostinstall && $this->permission->CanTemplates)
						{
							$link = 'index.php?option='.$option.'&amp;view=templates';
							FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-templates.png', JText::_( 'FLEXI_TEMPLATES' ) );
						}

						if ($this->dopostinstall && $this->permission->CanStats)
						{
							$link = 'index.php?option='.$option.'&amp;view=stats';
							FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-statistics.png', JText::_( 'FLEXI_STATISTICS' ) );
						}

						if ($this->dopostinstall && $this->permission->CanPlugins)
						{
							$link = 'index.php?option=com_plugins&amp;filter_type=flexicontent_fields&amp;tmpl=component';
							FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-plugins.png', JText::_( 'FLEXI_PLUGINS' ), 1 );
						}
												
						if ( $this->dopostinstall && ($this->params->get('comments') == 1) )
						{
							if ($this->permission->CanComments)
							{
								$link = 'index.php?option=com_jcomments&amp;task=view&amp;fog=com_flexicontent&amp;tmpl=component';
								FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-comments.png', JText::_( 'FLEXI_COMMENTS' ), 1 );
							}
						}
						
						
						if ($this->dopostinstall && $this->params->get('support_url'))
						{
							$link = $this->params->get('support_url');
							FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-support.png', JText::_( 'FLEXI_SUPPORT' ), 1 );
						}
						?>
						</div>
					</td>
				</tr>
			</table>
			</td>
			<td valign="top" width="420px" style="padding: 7px 0 0 5px">
			<?php
			echo $this->pane->startPane( 'stat-pane' );
			if (!$this->dopostinstall || !$this->allplgpublish) {
				$title = JText::_( 'FLEXI_POST_INSTALL' );
				echo $this->pane->startPanel( $title, 'postinstall' );
				echo $this->loadTemplate('postinstall');
				echo $this->pane->endPanel();
			}
			
			$title = JText::_( 'FLEXI_PENDING_SLIDER' )." (".count($this->pending)."/".$this->totalrows['pending'].")";
			echo $this->pane->startPanel( $title, 'pending' );
			$show_all_link = 'index.php?option=com_flexicontent&view=items&filter_state=PE';
			echo "<div style='text-align:right;'><a href='$show_all_link' style='color:darkred;font-weight:bold;'>Show All</a></div>";
			?>
				<table class="adminlist">
			<?php
					$k = 0;
					$n = count($this->pending);
					$user =& JFactory::getUser();
					for ($i=0, $n; $i < $n; $i++) {
					$row = $this->pending[$i];
						$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $row->id);
						$canEdit 		= in_array('edit', $rights);
						$canEditOwn	= in_array('edit.own', $rights) && $row->created_by == $user->id;
						$link		= 'index.php?option=com_flexicontent&amp;task=items.edit&amp;cid[]='. $row->id;
			?>
					<tr>
						<td>
						<?php
						if ((!$canEdit) && (!$canEditOwn)) {
							echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8');
						} else {
						?>
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo $row->title; ?>">
								<?php echo ($i+1).". "; ?>
								<a href="<?php echo $link; ?>">
									<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
								</a>
							</span>
						<?php
						}
						?>
						</td>
					</tr>
					<?php $k = 1 - $k; } ?>
				</table>
				<?php echo $this->pane->endPanel(); ?>
				
				
				<?php
				$title = JText::_( 'FLEXI_REVISED_VER_SLIDER' )." (".count($this->revised)."/".$this->totalrows['revised'].")";
				echo $this->pane->startPanel( $title, 'revised' );
				$show_all_link = 'index.php?option=com_flexicontent&view=items&filter_state=RV';
				echo "<div style='text-align:right;'><a href='$show_all_link' style='color:darkred;font-weight:bold;'>Show All</a></div>";
				?>
				<table class="adminlist">
				<?php
					$k = 0;
					$n = count($this->revised);
					$user =& JFactory::getUser();
					for ($i=0, $n; $i < $n; $i++) {
						$row = $this->revised[$i];
						$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $row->id);
						$canEdit 		= in_array('edit', $rights);
						$canEditOwn	= in_array('edit.own', $rights) && $row->created_by == $user->id;
						$link		= 'index.php?option=com_flexicontent&amp;task=items.edit&amp;cid[]='. $row->id;
				?>
					<tr>
						<td>
						<?php
						if ((!$canEdit) && (!$canEditOwn)) {
							echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8');
						} else {
						?>
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo $row->title; ?>">
								<?php echo ($i+1).". "; ?>
								<a href="<?php echo $link; ?>">
									<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
								</a>
							</span>
						<?php
						}
						?>
						</td>
					</tr>
					<?php $k = 1 - $k; } ?>
				</table>
				<?php echo $this->pane->endPanel(); ?>
				
				
				<?php
				$title = JText::_( 'FLEXI_IN_PROGRESS_SLIDER' )." (".count($this->inprogress)."/".$this->totalrows['inprogress'].")";
				echo $this->pane->startPanel( $title, 'inprogress' );
				$show_all_link = 'index.php?option=com_flexicontent&view=items&filter_state=IP';
				echo "<div style='text-align:right;'><a href='$show_all_link' style='color:darkred;font-weight:bold;'>Show All</a></div>";
				?>
				<table class="adminlist">
				<?php
					$k = 0;
					$n = count($this->inprogress);
					$user =& JFactory::getUser();
					for ($i=0, $n; $i < $n; $i++) {
						$row = $this->inprogress[$i];
						$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $row->id);
						$canEdit 		= in_array('edit', $rights);
						$canEditOwn	= in_array('edit.own', $rights) && $row->created_by == $user->id;
						$link		= 'index.php?option=com_flexicontent&amp;task=items.edit&amp;cid[]='. $row->id;
				?>
					<tr>
						<td>
						<?php
						if ((!$canEdit) && (!$canEditOwn)) {
							echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8');
						} else {
						?>
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo $row->title; ?>">
								<?php echo ($i+1).". "; ?>
								<a href="<?php echo $link; ?>">
									<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
								</a>
							</span>
						<?php
						}
						?>
						</td>
					</tr>
					<?php $k = 1 - $k; } ?>
				</table>
				<?php echo $this->pane->endPanel(); ?>
				
				
				<?php
				$title = JText::_( 'FLEXI_DRAFT_SLIDER' )." (".count($this->draft)."/".$this->totalrows['draft'].")";
				echo $this->pane->startPanel( $title, 'draft' );
				$show_all_link = 'index.php?option=com_flexicontent&view=items&filter_state=OQ';
				echo "<div style='text-align:right;'><a href='$show_all_link' style='color:darkred;font-weight:bold;'>Show All</a></div>";
				?>
				<table class="adminlist">
				<?php
					$k = 0;
					$n = count($this->draft);
					$user =& JFactory::getUser();
					for ($i=0, $n; $i < $n; $i++) {
						$row = $this->draft[$i];
						$rights = FlexicontentHelperPerm::checkAllItemAccess($user->id, 'item', $row->id);
						$canEdit 		= in_array('edit', $rights);
						$canEditOwn	= in_array('edit.own', $rights) && $row->created_by == $user->id;
						$link		= 'index.php?option=com_flexicontent&amp;task=items.edit&amp;cid[]='. $row->id;
				?>
					<tr>
						<td>
						<?php
						if ((!$canEdit) && (!$canEditOwn)) {
							echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8');
						} else {
						?>
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo $row->title; ?>">
								<?php echo ($i+1).". "; ?>
								<a href="<?php echo $link; ?>">
									<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
								</a>
							</span>
						<?php
						}
						?>
						</td>
					</tr>
					<?php $k = 1 - $k; } ?>
				</table>		
				<?php echo $this->pane->endPanel(); ?>
				
				
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
								url: 'index.php?option=com_flexicontent&task=fversioncompare&".JUtility::getToken()."=1',
								success: function(str) {
									jQuery('#displayfversion').html(str);
								}
							});
						}
					});
				});
				");
				echo $this->pane->startPane( 'stat-pane' );
				echo $this->pane->startPanel( JText::_( 'FLEXI_VERSION_CHECKING' ), 'updatecomponent' );
				echo "<div id=\"displayfversion\"></div>";
				echo $this->pane->endPanel();
				echo $this->pane->endPane();
			}
			?>
				<div class="credits">
					<?php echo JHTML::_('image', 'administrator/components/com_flexicontent/assets/images/logo.png', 'FLEXIcontent' ); ?>
					<p><a href="http://www.flexicontent.org" target="_blank">FLEXIcontent</a> version <?php echo FLEXI_VERSION . ' ' . FLEXI_RELEASE; ?><br />released under the GNU/GPL licence</p>
					<p>Copyright &copy; 2009-2012
					<br />
					Emmanuel Danan<br />
					<a class="hasTip" href="http://www.vistamedia.fr" target="_blank" title="Vistamedia.fr::Professional Joomla! Development and Integration">www.vistamedia.fr</a> - <a class="hasTip" href="http://www.joomla.fr" target="_blank" title="Joomla.fr::The official French support portal">www.joomla.fr</a>
					<br />
					Marvelic Engine<br />
					<a class="hasTip" href="http://www.marvelic.co.th" target="_blank" title="Marvelic Engine::Marvelic Engine is a Joomla consultancy based in Bangkok, Thailand. Support services include consulting, Joomla implementation, training, and custom extensions development.">www.marvelic.co.th</a>
					</p>
					<p>Logo and icons : Greg Berthelot<br />
					<a class="hasTip" href="http://www.artefact-design.com" target="_blank" title="Artefact Design::Professional Joomla! Integration">www.artefact-design.com</a></p>
				</div>
			</td>
		</tr>
	</table>
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="" />
	<input type="hidden" name="view" value="" />
	<input type="hidden" name="task" value="" />
	<?php echo JHTML::_( 'form.token' ); ?>
</form>
