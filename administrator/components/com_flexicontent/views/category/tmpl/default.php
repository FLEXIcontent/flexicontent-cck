<?php
/**
 * @version 1.5 stable $Id$
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

defined('_JEXEC') or die('Restricted access');

/*
$layouts = array();
foreach ($this->tmpls as $tmpl) {
	$layouts[] = $tmpl->name;
}
$layouts = implode("','", $layouts);

$this->document->addScriptDeclaration("
	window.addEvent('domready', function() {
		activatePanel('blog');
	});
	");
dump($this->row);
*/
?>

<form action="index.php" method="post" name="adminForm" id="adminForm">

	<table cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td valign="top">
				<table  class="adminform">
					<tr>
						<td class="key">
							<label for="title">
								<?php echo JText::_( 'FLEXI_TITLE' ).':'; ?>
							</label>
						</td>
						<td>
							<input id="title" name="title" class="required" value="<?php echo $this->row->title; ?>" size="50" maxlength="100" />
						</td>
						<td>
							<label for="published">
								<?php echo JText::_( 'FLEXI_PUBLISHED' ).':'; ?>
							</label>
						</td>
						<td>
							<?php echo JHTML::_('select.booleanlist', 'published', 'class="inputbox"', $this->row->published ); ?>
						</td>
					</tr>
					<tr>
						<td>
							<label for="alias">
								<?php echo JText::_( 'FLEXI_ALIAS' ).':'; ?>
							</label>
						</td>
						<td>
							<input class="inputbox" type="text" name="alias" id="alias" size="50" maxlength="100" value="<?php echo $this->row->alias; ?>" />
						</td>
						<td>
							<label for="parent">
								<?php echo JText::_( 'FLEXI_PARENT' ).':'; ?>
							</label>
						</td>
						<td>
							<?php echo $this->Lists['parent_id']; ?>
						</td>
					</tr>
					<tr>
						<td>
							<label for="parent">
								<?php echo JText::_( 'FLEXI_COPY_PARAMETERS' ).':'; ?>
							</label>
						</td>
						<td>
							<?php echo $this->Lists['copyid']; ?>
						</td>
						<td>
						</td>
						<td>
						</td>
					</tr>
				</table>
									
				<?php
				if (FLEXI_ACCESS) :
				$this->document->addScriptDeclaration("
					window.addEvent('domready', function() {
						var slideaccess = new Fx.Slide('tabacces');
						var slidenoaccess = new Fx.Slide('notabacces');
						slideaccess.hide();
						$$('fieldset.flexiaccess legend').addEvent('click', function(ev) {
							slideaccess.toggle();
							slidenoaccess.toggle();
						});
					});
				");
				?>
				<fieldset class="flexiaccess">
					<legend><?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT' ); ?></legend>
					<table id="tabacces" class="admintable" width="100%">
						<tr>
							<td>
								<div id="access"><?php echo $this->Lists['access']; ?></div>
							</td>
						</tr>
					</table>
					<div id="notabacces">
						<?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT_DESC' ); ?>
					</div>
				</fieldset>
				<?php endif; ?>

				<table class="adminform">
					<tr>
						<td>
							<?php
								// parameters : areaname, content, hidden field, width, height, rows, cols
								echo $this->editor->display( 'description',  $this->row->description, '100%;', '350', '75', '20', array('pagebreak', 'readmore') ) ;
							?>
						</td>
					</tr>
				</table>
				
			</td>
			<td valign="top" width="430" style="padding: 7px 0 0 5px">
				<?php
				echo JText::_('FLEXI_CAT_PARAM_OVERRIDE_ORDER_DETAILS');
				$title = JText::_( 'FLEXI_ACCESS' );
				echo $this->pane->startPane( 'det-pane' );
				if (!FLEXI_ACCESS) :
				echo $this->pane->startPanel( $title, 'access' );
				?>
				<table>
					<tr>
						<td>
							<label for="access">
								<?php echo JText::_( 'FLEXI_ACCESS' ).':'; ?>
							</label>
						</td>
						<td>
							<?php echo $this->Lists['access']; ?>
						</td>
					</tr>
				</table>
				<?php
				echo $this->pane->endPanel();
				endif;
				$title = JText::_( 'FLEXI_IMAGE' );
				echo $this->pane->startPanel( $title, 'image' );
				?>
				<table>
					<tr>
						<td>
							<label for="image">
								<?php echo JText::_( 'FLEXI_CHOOSE_IMAGE' ).':'; ?>
							</label>
						</td>
						<td>
							<?php echo $this->Lists['imagelist']; ?>
						</td>
					</tr>
					<tr>
						<td></td>
						<td>
							<script language="javascript" type="text/javascript">
								if (document.forms[0].image.options.value!=''){
									jsimg='../images/stories/' + getSelectedValue( 'adminForm', 'image' );
								} else {
									jsimg='../images/M_images/blank.png';
								}
								document.write('<img src=' + jsimg + ' name="imagelib" width="80" height="80" border="2" alt="Preview" />');
							</script>
							<br /><br />
						</td>
					</tr>
				</table>
				<?php
				echo $this->pane->endPanel();

				$title = JText::_( 'FLEXI_PARAMETERS_CAT_INFO_OPTIONS' );
				echo $this->pane->startPanel( $title, "params-cat_info_options" );
				echo $this->form->render('params', "cat_info_options" );
				echo $this->pane->endPanel();

				$title = JText::_( 'FLEXI_PARAMETERS_CAT_ITEMS_LIST' );
				echo $this->pane->startPanel( $title, "params-item_list_creation" );
				echo $this->form->render('params', 'item_list_creation');
				echo $this->pane->endPanel();
				
				$title = JText::_( 'FLEXI_PARAMETERS_CAT_ITEM_FILTERING' );
				echo $this->pane->startPanel( $title, "params-item_filtering" );
				echo $this->form->render('params', 'item_filtering');
				echo $this->pane->endPanel();
				
				$title = JText::_( 'FLEXI_PARAMETERS_CAT_RSS_FEEDS' );
				echo $this->pane->startPanel( $title, "params-rss_feeds" );
				echo $this->form->render('params', 'rss_feeds');
				echo $this->pane->endPanel();
				
				if ( $this->cparams->get('enable_notifications', 0) && $this->cparams->get('nf_allow_cat_specific', 0) )
				{
					$title = JText::_( 'FLEXI_EMAIL_NOTIFICATIONS_ASSIGNED_ITEM_CONF' );
					echo $this->pane->startPanel( $title, "params-notifications_conf" );
					echo $this->form->render('params', 'notifications_conf');
					echo $this->pane->endPanel();
				}
				
				
				echo '<h3 class="themes-title">' . JText::_( 'FLEXI_PARAMETERS_LAYOUT_THEMES' ) . '</h3>';
				echo $this->form->render('params', 'templates')."<br/>";

				foreach ($this->tmpls as $tmpl) {
					$title = JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name;
					echo $this->pane->startPanel( $title, "params-".$tmpl->name );
					echo $tmpl->params->render();
					echo $this->pane->endPanel();
				}

				echo $this->pane->endPane();
				?>
			</td>
		</tr>
	</table>

<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
<input type="hidden" name="section" value="<?php echo $this->row->section; ?>" />
<input type="hidden" name="controller" value="categories" />
<input type="hidden" name="view" value="category" />
<input type="hidden" name="task" value="" />
</form>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>