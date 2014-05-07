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

// Load JS tabber lib
$this->document->addScript( JURI::root().'components/com_flexicontent/assets/js/tabber-minimized.js' );
$this->document->addStyleSheet( JURI::root().'components/com_flexicontent/assets/css/tabber.css' );
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs

?>

<style>
.current:after{
	clear: both;
	content: "";
	display: block;
}
</style>

<div id="flexicontent" class="flexicontent">
<form action="index.php" method="post" name="adminForm" id="adminForm">

	<table cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td valign="top">
				
				<div class="flexi_params">
				
					<div class="fcdualline_container">
						<label for="title" class="flexi_label">
							<?php echo JText::_( 'FLEXI_TITLE' ); ?>
						</label>
						<div class="container_fcfield fcdualline">
							<input id="title" type="text" name="title" class="required" value="<?php echo $this->row->title; ?>" size="50" maxlength="100" />
						</div>
					</div>
					<div class="fcdualline_container">
						<label for="published" class="flexi_label">
							<?php echo JText::_( 'FLEXI_PUBLISHED' ); ?>
						</label>
						<div class="container_fcfield fcdualline">
							<?php echo JHTML::_('select.booleanlist', 'published', 'class="inputbox"', $this->row->published ); ?>
						</div>
					</div>
					<div class="fcclear"></div>
					
					<div class="fcdualline_container">
						<label for="alias" class="flexi_label">
							<?php echo JText::_( 'FLEXI_ALIAS' ); ?>
						</label>
						<div class="container_fcfield fcdualline">
							<input class="inputbox" type="text" name="alias" id="alias" size="50" maxlength="100" value="<?php echo $this->row->alias; ?>" />
						</div>
					</div>
					<div class="fcdualline_container">
						<label for="parent" class="flexi_label">
							<?php echo JText::_( 'FLEXI_PARENT' ); ?>
						</label>
						<div class="container_fcfield fcdualline">
							<?php echo $this->Lists['parent_id']; ?>
						</div>
					</div>
					<div class="fcclear"></div>
					
				</div>
				
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
				<fieldset class="flexiaccess" style="width: 95%;">
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
				
				
				<?php
				echo $this->tpane->startPane( 'core-tabs' );
				$title = JText::_( 'FLEXI_DESCRIPTION' ) ;
				echo $this->tpane->startPanel( $title, 'core-props' );
				?>
				

				<div class="flexi_params" style="margin:0px 24px; width: 99% !important;">
					<?php
						// parameters : areaname, content, hidden field, width, height, rows, cols
						echo $this->editor->display( FLEXI_J16GE ? 'jform[description]' : 'description',  $this->row->description, '100%', '350px', '75', '20', array('pagebreak', 'readmore') ) ;
					?>
				</div>
				
				<?php
				echo $this->tpane->endPanel();
				$title = JText::_( 'FLEXI_IMAGE' );
				echo $this->tpane->startPanel( $title, 'access' );
				?>
				
				<fieldset class="flexi_params">
					<table>
						<tr>
							<td> <label for="image"> <?php echo JText::_( 'FLEXI_CHOOSE_IMAGE' ).':'; ?> </label> </td>
							<td> <?php echo $this->Lists['imagelist']; ?> </td>
						</tr>
						<tr>
							<td></td>
							<td>
								<script language="javascript" type="text/javascript">
									jsimg = (document.forms[0].image.options.value!='') ?
										'../images/stories/' + getSelectedValue( 'adminForm', 'image' ) :
										'../images/M_images/blank.png';
									document.write('<img src=' + jsimg + ' name="imagelib" width="80" height="80" border="2" alt="Preview" />');
								</script>
								<br /><br />
							</td>
						</tr>
					</table>
				</fieldset>
				
				<?php
				echo $this->tpane->endPanel();				
				$title = JText::_( 'FLEXI_PARAMETERS_HANDLING' ) ;
				echo $this->tpane->startPanel( $title, 'cat-params-handling' );
				?>

				<fieldset class="flexi_params">
					<div class="fcdualline_container">
						<label for="parent" class="flexi_label hasTip" title="::<?php echo JText::_( 'FLEXI_COPY_PARAMETERS_DESC',true ); ?>">
							<?php echo JText::_( 'FLEXI_COPY_PARAMETERS' ); ?>
						</label>
						<div class="container_fcfield fcdualline">
							<?php echo $this->Lists['copycid']; ?>
						</div>
					</div>
					<div class="fcclear"></div>

					<div class="fcdualline_container">
						<label for="parent" class="flexi_label hasTip" title="::<?php echo JText::_( 'FLEXI_CATS_INHERIT_PARAMS_DESC',true ); ?>">
							<?php echo JText::_( 'FLEXI_CATS_INHERIT_PARAMS' ); ?>
						</label>
						<div class="container_fcfield fcdualline">
							<?php echo $this->Lists['inheritcid']; ?>
						</div>
					</div>
					<div class="fcclear"></div>
				</fieldset>
				
				<?php
				echo '<span class="fc-note fc-mssg">'.JText::_('FLEXI_CAT_PARAM_OVERRIDE_ORDER_DETAILS_INHERIT'). '</span>';
				echo $this->tpane->endPanel();
				
				if (!FLEXI_ACCESS) :
				$title = JText::_( 'FLEXI_ACCESS' );
				echo $this->tpane->startPanel( $title, 'access' );
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
				echo $this->tpane->endPanel();
				endif;
				
				$title = JText::_( 'FLEXI_PARAMETERS' ) ;
				echo $this->tpane->startPanel( $title, 'cat-params-common' );
				?>
				
				<div class="fctabber" style=''>
					
					<div class="tabbertab" style="padding: 0px;" >
						<h3 class="tabberheading"> <?php echo str_replace('&', ' / ', JText::_( 'FLEXI_PARAMS_CAT_INFO_OPTIONS' )); ?> </h3>
						<?php echo $this->form->render('params', "cat_info_options" ); ?>
					</div>
					
					<div class="tabbertab" style="padding: 0px;" >
						<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_PARAMS_SUBCATS_INFO_OPTIONS' ); ?> </h3>
						<?php echo $this->form->render('params', "subcats_info_options" ); ?>
					</div>
					
					<div class="tabbertab" style="padding: 0px;" >
						<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_PARAMS_PEERCATS_INFO_OPTIONS' ); ?> </h3>
						<?php echo $this->form->render('params', "peercats_info_options" ); ?>
					</div>
					
					<div class="tabbertab" style="padding: 0px;" >
						<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_PARAMS_CAT_ITEMS_LIST' ); ?> </h3>
						<?php echo $this->form->render('params', 'cat_items_list'); ?>
					</div>
					
					<div class="tabbertab" style="padding: 0px;" >
						<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_PARAMS_CAT_ITEM_MARKUPS' ); ?> </h3>
						<?php echo $this->form->render('params', 'cat_item_markups'); ?>
					</div>
					
					<div class="tabbertab" style="padding: 0px;" >
						<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_PARAMS_CAT_ITEM_FILTERING' ); ?> </h3>
						<?php echo $this->form->render('params', 'cat_item_filtering'); ?>
					</div>
					
					<div class="tabbertab" style="padding: 0px;" >
						<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_PARAMS_CAT_RSS_FEEDS' ); ?> </h3>
						<?php echo $this->form->render('params', 'cat_rss_feeds'); ?>
					</div>
					
					<?php if ( $this->cparams->get('enable_notifications', 0) && $this->cparams->get('nf_allow_cat_specific', 0) ) :?>
					<div class="tabbertab" style="padding: 0px;" >
						<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_EMAIL_NOTIFICATIONS_CONF' ); ?> </h3>
						<?php echo $this->form->render('params', 'cat_notifications_conf'); ?>
					</div>				
					<?php endif; ?>
					
				</div>
				
				<?php
				echo $this->tpane->endPanel();

				$title = JText::_( 'FLEXI_TEMPLATE' ) ;
				echo $this->tpane->startPanel( $title, 'cat-params-template' );
				echo '<span class="fc-note fc-mssg-inline" style="margin: 8px 0px!important;">' . JText::_( 'FLEXI_PARAMETERS_LAYOUT_EXPLANATION' ) ;
				?>
				<br/><br/>
				<ol style="margin:0 0 0 16px; padding:0;">
					<li style="margin:0; padding:0;"> Select TEMPLATE layout </li>
					<li style="margin:0; padding:0;"> Open slider with TEMPLATE (layout) PARAMETERS </li>
				</ol>
				<br/>
				<b>NOTE:</b> Common method for -displaying- fields is by <b>editing the template layout</b> in template manager and placing the fields into <b>template positions</b>
				</span>
				
				<?php
				echo $this->form->render('params', 'templates')."<br/>";
				echo $this->pane->startPane( 'det-pane' );
				foreach ($this->tmpls as $tmpl) {
					$title = JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name;
					echo $this->pane->startPanel( $title, "params-".$tmpl->name );
					echo $tmpl->params->render();
					echo $this->pane->endPanel();
				}
				echo $this->pane->endPane();
				
				echo $this->tpane->endPanel();
				echo $this->tpane->endPane();
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
</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>