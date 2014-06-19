<?php
/**
 * @version 1.5 stable $Id: form.php 1901 2014-05-07 02:37:25Z ggppdk $
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

$app   = JFactory::getApplication();
$user  = JFactory::getUser();
$session = JFactory::getSession();

// Create some variables
$isnew = !$this->item->id;
$typeid = $isnew ? JRequest::getInt('typeid') : $this->item->type_id;
$this->menuCats = $isnew ? $this->menuCats : false;  // just make sure ...

$newly_submitted = $session->get('newly_submitted', array(), 'flexicontent');
$newly_submitted_item = @ $newly_submitted[$this->item->id];
$submit_redirect_url_fe = $this->params->get('submit_redirect_url_fe');
$isredirected_after_submit = $newly_submitted_item && $submit_redirect_url_fe;

// Parameter configured to be displayed
if (FLEXI_J16GE) $fieldSets = $this->form->getFieldsets('attribs');
		
// J2.5+ requires Edit State privilege while J1.5 requires Edit privilege
$publication_priv = FLEXI_J16GE ? 'canpublish' : 'canedit';

// For tabsets/tabs ids (focusing, etc)
$tabSetCnt = -1;
$tabCnt = array();

$secondary_displayed =
  ($this->menuCats  && $this->menuCats->cid) ||   // New Content  -with-  Menu Override, check if secondary categories were enabled in menu
  (!$this->menuCats && $this->lists['cid']);      // New Content but  -without-  Menu override ... OR Existing Content, check if secondary are permitted  OR already set
$cats_canselect_sec =
	($this->menuCats && $this->menuCats->cancid) ||
	(!$this->menuCats && $this->perms['multicat'] && $this->perms['canchange_seccat']) ;
$usetags_fe = $this->params->get('usetags_fe', 1);
$tags_displayed = $typeid && ( ($this->perms['cantags'] && $usetags_fe) || (count(@$this->usedtagsdata) && $usetags_fe==2) ) ;

// Create reusable html code
$infoimage = JHTML::image ( 'components/com_flexicontent/assets/images/icon-16-hint.png', JText::_( 'FLEXI_NOTES' ) );
$close_btn = FLEXI_J30GE ? '<a class="close" data-dismiss="alert">&#215;</a>' : '<a class="fc-close" onclick="this.parentNode.parentNode.removeChild(this.parentNode);">&#215;</a>';
$alert_box = FLEXI_J30GE ? '<div %s class="alert alert-%s %s">'.$close_btn.'%s</div>' : '<div %s class="fc-mssg fc-%s %s">'.$close_btn.'%s</div>';
$btn_class = FLEXI_J30GE ? 'btn' : 'fc_button';

// Calculate refer parameter for returning to this page when user ends editing/submitting
$return = JRequest::getString('return', '', 'get');
if ($return) {
	$referer = base64_decode( $return );
} else {
	$referer = str_replace(array('"', '<', '>', "'"), '', @$_SERVER['HTTP_REFERER']);
}


// Placement configuration
$via_core_field   = $this->placementConf['via_core_field'];
$via_core_prop    = $this->placementConf['via_core_prop'];
$placeable_fields = $this->placementConf['placeable_fields'];
$tab_fields       = $this->placementConf['tab_fields'];
$tab_titles       = $this->placementConf['tab_titles'];
$all_tab_fields   = $this->placementConf['all_tab_fields'];
$coreprop_missing = $this->placementConf['coreprop_missing'];


// add extra css/js for the edit form
if ($this->params->get('form_extra_css'))    $this->document->addStyleDeclaration($this->params->get('form_extra_css'));
if ($this->params->get('form_extra_css_fe')) $this->document->addStyleDeclaration($this->params->get('form_extra_css_fe'));
if ($this->params->get('form_extra_js'))     $this->document->addScriptDeclaration($this->params->get('form_extra_js'));
if ($this->params->get('form_extra_js_fe'))  $this->document->addScriptDeclaration($this->params->get('form_extra_js_fe'));

// Load JS tabber lib
$this->document->addScript( JURI::root().'components/com_flexicontent/assets/js/tabber-minimized.js' );
$this->document->addStyleSheet( JURI::root().'components/com_flexicontent/assets/css/tabber.css' );
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs

if ( $this->perms['cantags'] && $this->params->get('usetags_fe', 1)==1 ) {
	$this->document->addScript(JURI::root().'components/com_flexicontent/librairies/jquery-autocomplete/jquery.bgiframe.min.js');
	$this->document->addScript(JURI::root().'components/com_flexicontent/librairies/jquery-autocomplete/jquery.ajaxQueue.js');
	$this->document->addScript(JURI::root().'components/com_flexicontent/librairies/jquery-autocomplete/jquery.autocomplete.min.js');
	// These are not used in frontend form (in order to keep it simpler, maybe we will add via parameter ...)
	//$this->document->addScript(JURI::root().'components/com_flexicontent/assets/js/jquery.pager.js');     // e.g. pagination for item versions
	//$this->document->addScript(JURI::root().'components/com_flexicontent/assets/js/jquery.autogrow.js');  // e.g. autogrow version comment textarea
	
	$this->document->addStyleSheet('components/com_flexicontent/librairies/jquery-autocomplete/jquery.autocomplete.css');
	$this->document->addScriptDeclaration("
		jQuery(document).ready(function () {
			jQuery(\"#input-tags\").autocomplete(\"".JURI::base(true)."/index.php?option=com_flexicontent&view=item&task=viewtags&tmpl=component&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1\", {
				width: 260,
				max: 100,
				matchContains: false,
				mustMatch: false,
				selectFirst: false,
				dataType: \"json\",
				parse: function(data) {
					return jQuery.map(data, function(row) {
						return {
							data: row,
							value: row.name,
							result: row.name
						};
					});
				},
				formatItem: function(row) {
					return row.name;
				}
			}).result(function(e, row) {
				jQuery(\"#input-tags\").attr('tagid',row.id);
				jQuery(\"#input-tags\").attr('tagname',row.name);
				addToList(row.id, row.name);
			}).keydown(function(event) {
				if((event.keyCode==13)&&(jQuery(\"#input-tags\").attr('tagid')=='0') ) {//press enter button
					addtag(0, jQuery(\"#input-tags\").attr('value'));
					resetField();
					return false;
				}else if(event.keyCode==13) {
					resetField();
					return false;
				}
			});
			function resetField() {
				jQuery(\"#input-tags\").attr('tagid',0);
				jQuery(\"#input-tags\").attr('tagname','');
				jQuery(\"#input-tags\").attr('value','');
			}
			jQuery(\".deletetag\").click(function(e){
				parent = jQuery(jQuery(this).parent());
				parent.remove();
				return false;
			});
		});
	");
} else {
	$this->document->addScriptDeclaration("
		jQuery(document).ready(function () {
			jQuery(\".deletetag\").click(function(e){
				return false;
			});
		});
	");
}
?>
<script language="javascript" type="text/javascript">
jQuery(document).ready(function() {
	document.formvalidator.setHandler('cid',
		function (value) {
			if(value == -1) {
				return true;
			} else {
				timer = new Date();
				time = timer.getTime();
				regexp = new Array();
				regexp[time] = new RegExp('^[1-9]{1}[0-9]{0,}$');
				return regexp[time].test(value);
			}
		}
	);
});
function addToList(id, name) {
	obj = jQuery('#ultagbox');
	obj.append("<li class=\"tagitem\"><span>"+name+"</span><input type='hidden' name='jform[tag][]' value='"+id+"' /><a href=\"javascript:;\"  class=\"deletetag\" onclick=\"javascript:deleteTag(this);\" title=\"<?php echo JText::_( 'FLEXI_DELETE_TAG' ); ?>\"></a></li>");
}
function addtag(id, tagname) {
	if(id==null) {
		id=0;
	}
	if(tagname == '') {
		alert('<?php echo JText::_( 'FLEXI_ENTER_TAG', true); ?>' );
		return;
	}
	if(id) return;
	var tag = new itemscreen();
	tag.addtag( id, tagname, 'index.php?option=com_flexicontent&task=addtag&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1');
}

function deleteTag(obj) {
	var parent = obj.parentNode;
	parent.innerHTML = "";
	parent.parentNode.removeChild(parent);
}

</script>

<?php
$page_classes  = 'flexi_edit';
$page_classes .= $this->pageclass_sfx ? ' page'.$this->pageclass_sfx : '';
?>
<div id="flexicontent" class="<?php echo $page_classes; ?>" style="font-size:90%;<?php echo $this->params->get('form_container_css_fe'); ?>">

	<?php if ($this->params->def( 'show_page_heading', 1 )) : ?>
	<h1 class="componentheading">
		<?php echo $this->params->get('page_heading'); ?>
	</h1>
	<?php endif; ?>

	<?php
	$allowbuttons_fe = $this->params->get('allowbuttons_fe');
	if ( empty($allowbuttons_fe) )						$allowbuttons_fe = array();
	else if ( ! is_array($allowbuttons_fe) )	$allowbuttons_fe = !FLEXI_J16GE ? array($allowbuttons_fe) : explode("|", $allowbuttons_fe);
	
	$allowlangmods_fe = $this->params->get('allowlangmods_fe');
	if ( empty($allowlangmods_fe) )						$allowlangmods_fe = array();
	else if ( ! is_array($allowlangmods_fe) )	$allowlangmods_fe = !FLEXI_J16GE ? array($allowlangmods_fe) : explode("|", $allowlangmods_fe);
	?>

	<form action="<?php echo $this->action ?>" method="post" name="adminForm" id="adminForm" class="form-validate" enctype="multipart/form-data">

		<div id="flexi_form_submit_msg">
			<?php echo JText::_('FLEXI_FORM_IS_BEING_SUBMITTED'); ?>
		</div>
		<div id="flexi_form_submit_btns" class="flexi_buttons">
			
			<?php if ( $this->perms['canedit'] || in_array( 'apply', $allowbuttons_fe) || !$typeid ) : ?>
				<button class="<?php echo $btn_class;?> btn-success" type="button" onclick="return flexi_submit('apply', 'flexi_form_submit_btns', 'flexi_form_submit_msg');">
					<span class="fcbutton_apply"><?php echo JText::_( !$isnew ? 'FLEXI_APPLY' : ($typeid ? 'FLEXI_ADD' : 'FLEXI_APPLY_TYPE' ) ) ?></span>
				</button>
			<?php endif; ?>
			
			<?php if ( $typeid ) : ?>
				
				<button class="<?php echo $btn_class;?>  btn-success" type="button" onclick="return flexi_submit('save', 'flexi_form_submit_btns', 'flexi_form_submit_msg');">
					<span class="fcbutton_save"><?php echo JText::_( !$isnew ? 'FLEXI_SAVE_A_RETURN' : 'FLEXI_ADD_A_RETURN' ) ?></span>
				</button>
			
				<?php if ( in_array( 'save_preview', $allowbuttons_fe) && !$isredirected_after_submit ) : ?>
					<button class="<?php echo $btn_class;?>  btn-success" type="button" onclick="return flexi_submit('save_a_preview', 'flexi_form_submit_btns', 'flexi_form_submit_msg');">
						<span class="fcbutton_preview_save"><?php echo JText::_( !$isnew ? 'FLEXI_SAVE_A_PREVIEW' : 'FLEXI_ADD_A_PREVIEW' ) ?></span>
					</button>
				<?php endif; ?>

				<?php
					$params = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=100%,height=100%,directories=no,location=no';
					$link   = JRoute::_(FlexicontentHelperRoute::getItemRoute($this->item->id.':'.$this->item->alias, $this->item->catid, 0, $this->item).'&preview=1');
				?>
			
				<?php if ( in_array( 'preview_latest', $allowbuttons_fe) && !$isredirected_after_submit && !$isnew ) : ?>
					<button class="<?php echo $btn_class;?>  btn-default" type="button" onclick="window.open('<?php echo $link; ?>','preview2','<?php echo $params; ?>'); return false;">
						<span class="fcbutton_preview"><?php echo JText::_( $this->params->get('use_versioning', 1) ? 'FLEXI_PREVIEW_LATEST' :'FLEXI_PREVIEW' ) ?></span>
					</button>
				<?php endif; ?>
			
			<?php endif; ?>
			
			<button class="<?php echo $btn_class;?>  btn-danger" type="button" onclick="return flexi_submit('cancel', 'flexi_form_submit_btns', 'flexi_form_submit_msg')">
				<span class="fcbutton_cancel"><?php echo JText::_( 'FLEXI_CANCEL' ) ?></span>
			</button>
			
		</div>
    
		<?php
			$submit_msg = $approval_msg = '';
			// A message about submitting new Content via configuration parameter
			if ( $isnew && $this->params->get('submit_message') ) {
				$submit_msg = sprintf( $alert_box, '', 'note', 'fc-nobgimage', JText::_($this->params->get('submit_message')) );
			}
			
			// Autopublishing new item regardless of publish privilege, use a menu item specific
			// message if this is set, or notify user of autopublishing with a default message
			if ( $isnew && $this->params->get('autopublished') ) {
				$approval_msg = $this->params->get('autopublished_message') ? $this->params->get('autopublished_message') :  JText::_( 'FLEXI_CONTENT_WILL_BE_AUTOPUBLISHED' ) ;
				$approval_msg = str_replace('_PUBLISH_UP_DAYS_INTERVAL_', $this->params->get('autopublished_up_interval') / (24*60), $approval_msg);
				$approval_msg = str_replace('_PUBLISH_DOWN_DAYS_INTERVAL_', $this->params->get('autopublished_up_interval') / (24*60), $approval_msg);
				$approval_msg = sprintf( $alert_box, '', 'info', $approval_msg );
			}
			else {
				// Current user does not have general publish privilege, aka new/existing items will surely go through approval/reviewal process
				if ( !$this->perms['canpublish'] ) {
					if ($isnew) {
						$approval_msg = JText::_( 'FLEXI_REQUIRES_DOCUMENT_APPROVAL' ) ;
						$approval_msg = sprintf( $alert_box, '', 'note', 'fc-nobgimage', $approval_msg );
					} else if ( $this->params->get('use_versioning', 1) ) {
						$approval_msg = JText::_( 'FLEXI_REQUIRES_VERSION_REVIEWAL' ) ;
						$approval_msg = sprintf( $alert_box, '', 'note', 'fc-nobgimage', $approval_msg );
					} else {
						$approval_msg = JText::_( 'FLEXI_CHANGES_APPLIED_IMMEDIATELY' ) ;
						$approval_msg = sprintf( $alert_box, '', 'info', 'fc-nobgimage', $approval_msg );
					}
				}
				
				// Have general publish privilege but may not have privilege if item is assigned to specific category or is of a specific type
				else {
					if ($isnew) {
						$approval_msg = JText::_( 'FLEXI_MIGHT_REQUIRE_DOCUMENT_APPROVAL' ) ;
						$approval_msg = sprintf( $alert_box, '', 'note', 'fc-nobgimage', $approval_msg );
					} else if ( $this->params->get('use_versioning', 1) ) {
						$approval_msg = JText::_( 'FLEXI_MIGHT_REQUIRE_VERSION_REVIEWAL' ) ;
						$approval_msg = sprintf( $alert_box, '', 'note', 'fc-nobgimage', $approval_msg );
					} else {
						$approval_msg = JText::_( 'FLEXI_CHANGES_APPLIED_IMMEDIATELY' ) ;
						$approval_msg = sprintf( $alert_box, '', 'info', 'fc-nobgimage', $approval_msg );
					}
				}
			}
			?>

<?php if ($submit_msg || $approval_msg) : ?>
	<div class="fcclear" style="height:12px!important;"></div>
	<?php echo $submit_msg . $approval_msg; ?>
<?php else : ?>
	<div class="fcclear"></div>
<?php endif; ?>

<?php if ( $this->captcha_errmsg ) : ?>

	<?php echo sprintf( $alert_box, '', 'error', '', $this->captcha_errmsg );?>

<?php elseif ( $this->captcha_field ) : ?>
	
	<?php ob_start();  // captcha ?>
		<fieldset class="flexi_params fc_edit_container_full">
		<?php echo $this->captcha_field; ?>
		</fieldset>
	<?php $captured['captcha'] = ob_get_clean(); ?>

<?php endif; ?>



<?php ob_start();  // title ?>
	<?php
	$field = $this->fields['title'];
	$field_description = $field->description ? $field->description :
		JText::_(FLEXI_J16GE ? $this->form->getField('title')->__get('description') : 'TIPTITLEFIELD');
	$label_tooltip = 'class="hasTip flexi_label" title="'.'::'.htmlspecialchars($field_description, ENT_COMPAT, 'UTF-8').'"';
	?>
	<label id="jform_title-lbl" for="jform_title" <?php echo $label_tooltip; ?> >
		<?php echo $field->label; //JText::_( 'FLEXI_TITLE' ); ?>
	</label>
	<?php /*echo $this->form->getLabel('title');*/ ?>
	
	<div class="container_fcfield container_fcfield_id_1 container_fcfield_name_title" id="container_fcfield_1">
	<?php	if ( isset($this->item->item_translations) ) :?>
		
		<!-- tabber start -->
		<div class="fctabber" style=''>
			<div class="tabbertab" style="padding: 0px;" >
				<h3 class="tabberheading"> <?php echo '-'.$this->itemlang->name.'-'; // $t->name; ?> </h3>
				<?php echo $this->form->getInput('title');?>
			</div>
			<?php foreach ($this->item->item_translations as $t): ?>
				<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*') : ?>
					<div class="tabbertab" style="padding: 0px;" >
						<h3 class="tabberheading"> <?php echo $t->name; // $t->shortcode; ?> </h3>
						<?php
						$ff_id = 'jfdata_'.$t->shortcode.'_title';
						$ff_name = 'jfdata['.$t->shortcode.'][title]';
						?>
						<input class="inputbox fc_form_title fcfield_textval" style='margin:0px;' type="text" id="<?php echo $ff_id; ?>" name="<?php echo $ff_name; ?>" value="<?php echo @$t->fields->title->value; ?>" size="40" maxlength="254" />
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<!-- tabber end -->
		
	<?php else : ?>
		<?php echo $this->form->getInput('title');?>
	<?php endif; ?>

	</div>
<?php $captured['title'] = ob_get_clean();



if ($this->params->get('usealias_fe', 1)) : ob_start();  // alias ?>
	<?php
	$field_description = JText::_(FLEXI_J16GE ? $this->form->getField('alias')->__get('description') : 'ALIASTIP');
	$label_tooltip = 'class="hasTip flexi_label" title="'.'::'.htmlspecialchars($field_description, ENT_COMPAT, 'UTF-8').'"';
	?>
	<label id="jform_alias-lbl" for="jform_alias" <?php echo $label_tooltip; ?> >
		<?php echo JText::_( 'FLEXI_ALIAS' ); ?>
	</label>
	
	<div class="container_fcfield container_fcfield_name_alias">
	<?php	if ( isset($this->item->item_translations) ) :?>
	
		<!-- tabber start -->
		<div class="fctabber" style=''>
			<div class="tabbertab" style="padding: 0px;" >
				<h3 class="tabberheading"> <?php echo '-'.$this->itemlang->name.'-'; // $t->name; ?> </h3>
				<?php echo $this->form->getInput('alias');?>
			</div>
			<?php foreach ($this->item->item_translations as $t): ?>
				<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*') : ?>
					<div class="tabbertab" style="padding: 0px;" >
						<h3 class="tabberheading"> <?php echo $t->name; // $t->shortcode; ?> </h3>
						<?php
						$ff_id = 'jfdata_'.$t->shortcode.'_alias';
						$ff_name = 'jfdata['.$t->shortcode.'][alias]';
						?>
						<input class="inputbox fc_form_alias fcfield_textval" style='margin:0px;' type="text" id="<?php echo $ff_id; ?>" name="<?php echo $ff_name; ?>" value="<?php echo @$t->fields->alias->value; ?>" size="40" maxlength="254" />
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<!-- tabber end -->
		
	<?php else : ?>
		<?php echo $this->form->getInput('alias');?>
	<?php endif; ?>
	
	</div>
<?php $captured['alias'] = ob_get_clean(); endif;



if ($typeid==0) : ob_start();  // type ?>
	<?php
	$field = $this->fields['document_type'];
	$field_description = $field->description ? $field->description :
		JText::_(FLEXI_J16GE ? $this->form->getField('type_id')->__get('description') : 'FLEXI_TYPE_DESC');
	$label_tooltip = 'class="hasTip flexi_label" title="'.'::'.htmlspecialchars($field_description, ENT_COMPAT, 'UTF-8').'"';
	?>
	<label id="jform_type_id-lbl" for="jform_type_id" for_bck="jform_type_id" <?php echo $label_tooltip; ?> >
		<?php echo @$field->label ? $field->label : JText::_( 'FLEXI_TYPE' ); ?>
	</label>
	<div class="container_fcfield container_fcfield_id_8 container_fcfield_name_type" id="container_fcfield_8">
		<?php echo $this->lists['type']; ?>
		<span class="editlinktip hasTip" style="display:inline-block;" title="<?php echo htmlspecialchars(JText::_( 'FLEXI_NOTES' ), ENT_COMPAT, 'UTF-8'); ?>::<?php echo htmlspecialchars(JText::_( 'FLEXI_TYPE_CHANGE_WARNING' ), ENT_COMPAT, 'UTF-8');?>">
			<?php echo $infoimage; ?>
		</span>
		<?php echo sprintf( $alert_box, 'id="fc-change-warning" style="display:none;"', 'warning', '', '<h4>'.JText::_( 'FLEXI_WARNING' ).'</h4> '.JText::_( 'FLEXI_TAKE_CARE_CHANGING_FIELD_TYPE' ) ); ?>
	</div>
<?php $captured['type'] = ob_get_clean(); endif;



if ( $isnew && $this->params->get('autopublished') ) :  // Auto publish new item via menu override ?>

	<input type="hidden" id="jform_state" name="jform[state]" value="1" />
	<input type="hidden" id="jform_vstate" name="jform[vstate]" value="2" />

<?php else : ob_start();  // state (and vstate) ?>

	<?php
	$field = $this->fields['state'];
	$field_description = $field->description ? $field->description :
		JText::_(FLEXI_J16GE ? $this->form->getField('state')->__get('description') : 'FLEXI_STATE_DESC');
	$label_tooltip = 'class="hasTip flexi_label" title="'.'::'.htmlspecialchars($field_description, ENT_COMPAT, 'UTF-8').'"';
	?>
	<label id="jform_state-lbl" for="jform_state" <?php echo $label_tooltip; ?> >
		<?php echo @$field->label ? $field->label : JText::_( 'FLEXI_STATE' ); ?>
	</label>
	
	<?php if ( $this->perms['canpublish'] ) : // Display state selection field to the user that can publish ?>

		<div class="container_fcfield container_fcfield_id_10 container_fcfield_name_state fcdualline" id="container_fcfield_10" style="margin-right:4% !important;" >
			<?php echo $this->lists['state']; ?>
			<?php //echo $this->form->getInput('state'); ?>
			<span class="editlinktip hasTip" style="display:inline-block;" title="<?php echo htmlspecialchars(JText::_( 'FLEXI_NOTES' ), ENT_COMPAT, 'UTF-8'); ?>::<?php echo htmlspecialchars(JText::_( 'FLEXI_STATE_CHANGE_WARNING' ), ENT_COMPAT, 'UTF-8');?>">
				<?php echo $infoimage; ?>
			</span>
		</div>
		
		<?php	if ( $this->params->get('use_versioning', 1) && $this->params->get('allow_unapproved_latest_version', 0) ) : /* PARAMETER MISSING currently disabled */ ?>
			<div style="float:left; width:50%;">
				<?php
					//echo "<br/>".$this->form->getLabel('vstate') . $this->form->getInput('vstate');
					$label_tooltip = 'class="hasTip flexi_label fcdualline" title="'.htmlspecialchars(JText::_( 'FLEXI_PUBLIC_DOCUMENT_CHANGES' ), ENT_COMPAT, 'UTF-8').'::'.htmlspecialchars(JText::_( 'FLEXI_PUBLIC_DOCUMENT_CHANGES_DESC' ), ENT_COMPAT, 'UTF-8').'"';
				?>
				<label id="jform_vstate-lbl" for="jform_vstate" <?php echo $label_tooltip; ?> >
					<?php echo JText::_( 'FLEXI_PUBLIC_DOCUMENT_CHANGES' ); ?>
				</label>
				<div class="container_fcfield container_fcfield_name_vstate fcdualline">
					<?php echo $this->lists['vstate']; ?>
				</div>
			</div>
		<?php	else : ?>
	  	<input type="hidden" id="jform_vstate" name="jform[vstate]" value="2" />
		<?php	endif; ?>
		
	<?php else :  // Display message to user that he/she can not publish or that changes are applied immediately for existing published item ?>

		<div class="container_fcfield container_fcfield_id_10 container_fcfield_name_state" id="container_fcfield_10">
  		<?php 
  			echo JText::_( ($isnew || $this->params->get('use_versioning', 1)) ? 'FLEXI_NEEDS_APPROVAL' : 'FLEXI_WITHOUT_APPROVAL' );
				// Enable approval if versioning disabled, this make sense since if use can edit item THEN item should be updated !!!
				$item_vstate = $this->params->get('use_versioning', 1) ? 1 : 2;
  		?>
			<input type="hidden" id="state" name="jform[state]" value="<?php echo !$isnew ? $this->item->state : -4; ?>" />
			<input type="hidden" id="vstate" name="jform[vstate]" value="<?php echo $item_vstate; ?>" />
		</div>

	<?php endif; ?>
<?php $captured['state'] = ob_get_clean(); endif;



if ( $typeid && $this->params->get('allowdisablingcomments_fe') ) : ob_start();  // disable_comments ?>
	<?php
	$label_tooltip = 'class="hasTip flexi_label" title="'.htmlspecialchars(JText::_ ( 'FLEXI_ALLOW_COMMENTS' ), ENT_COMPAT, 'UTF-8').'::'.htmlspecialchars(JText::_( 'FLEXI_ALLOW_COMMENTS_DESC' ), ENT_COMPAT, 'UTF-8').'"';
	?>
	<label id="jform_attribs_comments-title" <?php echo $label_tooltip; ?> >
		<?php echo JText::_( 'FLEXI_ALLOW_COMMENTS' );?>
	</label>
	<div class="container_fcfield container_fcfield_name_comments">
		<?php echo $this->lists['disable_comments']; ?>
	</div>
<?php $captured['disable_comments'] = ob_get_clean(); endif;



if ( $typeid && $this->params->get('allow_subscribers_notify_fe', 0) && $this->subscribers) :  ob_start();  // notify_subscribers ?>
	<?php
	$label_tooltip = 'class="hasTip flexi_label" title="'.'::'.htmlspecialchars(JText::_( 'FLEXI_NOTIFY_NOTES' ), ENT_COMPAT, 'UTF-8').'"';
	?>
	<label id="jform_notify-lbl" for="jform_notify" <?php echo $label_tooltip; ?> >
		<?php echo JText::_( 'FLEXI_NOTIFY_FAVOURING_USERS' ); ?>
	</label>
	<div class="container_fcfield container_fcfield_name_notify">
		<?php echo $this->lists['notify']; ?>
	</div>
<?php $captured['notify_subscribers'] = ob_get_clean(); endif;



ob_start();  // categories ?>
	<fieldset class="basicfields_set" id="fcform_categories_container">
		<legend>
			<?php echo JText::_( 'FLEXI_CATEGORIES' ); ?>
		</legend>
		
		<label id="jform_catid-lbl" for="jform_catid" for_bck="jform_catid" class="flexi_label">
			<?php echo JText::_( !$secondary_displayed ? 'FLEXICONTENT_CATEGORY' : 'FLEXI_PRIMARY_CATEGORY' ); ?>
		</label>
		<div class="container_fcfield container_fcfield_name_catid">
			<?php /* MENU SPECIFIED main category (new item) or main category according to perms */ ?>
			<?php echo $this->menuCats ? $this->menuCats->catid : $this->lists['catid']; ?>
			<?php
				if ($cats_canselect_sec) {
					// display secondary categories if permitted
					$mcats_tooltip = 'class="editlinktip hasTip" style="display:inline-block;" title="'
						.htmlspecialchars(JText::_ ( 'FLEXI_NOTES' ), ENT_COMPAT, 'UTF-8').'::'
						.htmlspecialchars(JText::_ ( 'FLEXI_CATEGORIES_NOTES' ), ENT_COMPAT, 'UTF-8').'" ';
					echo '<span '.$mcats_tooltip.'>'.$infoimage.'</span>';
				}
			?>
		</div>
		
		<?php if ($secondary_displayed) : /* MENU SPECIFIED categories subset (instead of categories with CREATE perm) */ ?>
			
			<div class="fcclear"></div>
			<label id="jform_cid-lbl" for="jform_cid" for_bck="jform_cid" class="flexi_label">
				<?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' );?>
			</label>
			<div class="container_fcfield container_fcfield_name_cid">
				<?php /* MENU SPECIFIED secondary categories (new item) or categories according to perms */ ?>
				<?php echo @$this->menuCats->cid ? $this->menuCats->cid : $this->lists['cid']; ?>
			</div>
			
		<?php endif; ?>

		<?php if ( !empty($this->lists['featured_cid']) ) : ?>
			<div class="fcclear"></div>
			<label id="jform_featured_cid-lbl" for="jform_featured_cid" for_bck="jform_featured_cid" class="flexi_label">
				<?php echo JText::_( 'FLEXI_FEATURED_CATEGORIES' ); ?>
			</label>
			<div class="container_fcfield container_fcfield_name_featured_cid">
				<?php echo $this->lists['featured_cid']; ?>
			</div>
		<?php endif; ?>
	</fieldset>
<?php $captured['categories'] = ob_get_clean();



if ($tags_displayed) : ob_start();  // tags ?>
	<fieldset class="basicfields_set" id="fcform_tags_container">
		<legend>
			<?php echo JText::_( 'FLEXI_TAGS' ); ?>
		</legend>
		
		<?php
		$field = $this->fields['tags'];
		$label_tooltip = $field->description ? 'class="hasTip flexi_label" title="'.'::'.htmlspecialchars($field->description, ENT_COMPAT, 'UTF-8').'"' : 'class="flexi_label"';
		?>
		<label id="jform_tag-lbl" for="jform_tag" <?php echo $label_tooltip; ?> >
			<?php echo $field->label; ?>
			<?php /*echo JText::_( 'FLEXI_TAGS' );*/ ?>
		</label>
		<div class="container_fcfield container_fcfield_name_tags">
			
			<div class="qf_tagbox" id="qf_tagbox">
				<ul id="ultagbox">
				<?php
					foreach($this->usedtagsdata as $tag) {
						if ( $this->perms['cantags'] && $this->params->get('usetags_fe', 1)==1 ) {
							echo '<li class="tagitem"><span>'.$tag->name.'</span>';
							echo '<input type="hidden" name="jform[tag][]" value="'.$tag->id.'" /><a href="javascript:;" onclick="javascript:deleteTag(this);" class="deletetag" align="right" title="'.JText::_('FLEXI_DELETE_TAG').'"></a></li>';
						} else {
							echo '<li class="tagitem plain"><span>'.$tag->name.'</span>';
							echo '<input type="hidden" name="jform[tag][]" value="'.$tag->id.'" /></li>';
						}
					}
				?>
				</ul>
			</div>

			<?php if ( $this->perms['cantags'] && $this->params->get('usetags_fe', 1)==1 ) : ?>
			<div class="fcclear"></div>
			<div id="tags">
				<label for="input-tags">
					<?php echo JText::_( 'FLEXI_ADD_TAG' ); ?>
				</label>
				<input type="text" id="input-tags" name="tagname" tagid='0' tagname='' />
				<span id='input_new_tag' ></span>
				<span class="editlinktip hasTip" style="display:inline-block;" title="<?php echo htmlspecialchars(JText::_( 'FLEXI_NOTES' ), ENT_COMPAT, 'UTF-8'); ?>::<?php echo htmlspecialchars(JText::_( 'FLEXI_TAG_EDDITING_FULL' ), ENT_COMPAT, 'UTF-8');?>">
					<?php echo $infoimage; ?>
				</span>
			</div>
			<?php endif; ?>
		</div>
	</fieldset>
<?php $captured['tags'] = ob_get_clean(); endif;



if ((FLEXI_FISH || FLEXI_J16GE) && $this->params->get('uselang_fe', 1)) : ob_start(); // language ?>
	<fieldset class="basicfields_set" id="fcform_language_container">
		<legend>
			<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
		</legend>
		
		<span class="flexi_label">
			<?php echo $this->form->getLabel('language'); ?>
		</span>
		
		<div class="container_fcfield container_fcfield_name_language">
			<?php if ( (in_array( 'mod_item_lang', $allowlangmods_fe) || $isnew) && $this->params->get('uselang_fe', 1)==1 ) : ?>
				<?php echo $this->lists['languages']; ?>
			<?php else: ?>
				<?php echo $this->itemlang->image.' ['.$this->itemlang->name.']'; ?>
			<?php endif; ?>
		</div>

		<?php if ( $this->params->get('enable_translation_groups') ) : ?>

			<div class="fcclear"></div>
			<?php
				$label_tooltip = 'class="hasTip flexi_label" title="'.'::'.htmlspecialchars(JText::_( 'FLEXI_ORIGINAL_CONTENT_ITEM_DESC' ), ENT_COMPAT, 'UTF-8').'"';
			?>
			<label id="jform_lang_parent_id-lbl" for="jform_lang_parent_id" <?php echo $label_tooltip; ?> >
				<?php echo JText::_( 'FLEXI_ORIGINAL_CONTENT_ITEM' );?>
			</label>
			
			<div class="container_fcfield container_fcfield_name_originalitem">
			<?php if ( !$isnew  && (substr(flexicontent_html::getSiteDefaultLang(), 0,2) == substr($this->item->language, 0,2) || $this->item->language=='*') ) : ?>
				<br/><?php echo JText::_( $this->item->language=='*' ? 'FLEXI_ORIGINAL_CONTENT_ALL_LANGS' : 'FLEXI_ORIGINAL_TRANSLATION_CONTENT' );?>
				<input type="hidden" name="jform[lang_parent_id]" id="jform_lang_parent_id" value="<?php echo $this->item->id; ?>" />
			<?php else : ?>
				<?php
				if ( in_array( 'mod_item_lang', $allowlangmods_fe) || $isnew || $this->item->id==$this->item->lang_parent_id) {
					$app = JFactory::getApplication();
					$option = JRequest::getVar('option');
					$app->setUserState( $option.'.itemelement.langparent_item', 1 );
					$app->setUserState( $option.'.itemelement.type_id', $typeid);
					$app->setUserState( $option.'.itemelement.created_by', $this->item->created_by);
					//echo '<small>'.JText::_( 'FLEXI_ORIGINAL_CONTENT_IGNORED_IF_DEFAULT_LANG' ).'</small><br/>';
					echo $this->form->getInput('lang_parent_id');
				?>
					<span class="editlinktip hasTip" style="display:inline-block;" title="<?php echo htmlspecialchars(JText::_( 'FLEXI_NOTES' ), ENT_COMPAT, 'UTF-8'); ?>::<?php echo htmlspecialchars(JText::_( 'FLEXI_ORIGINAL_CONTENT_IGNORED_IF_DEFAULT_LANG' ), ENT_COMPAT, 'UTF-8');?>">
						<?php echo $infoimage; ?>
					</span>
				<?php
				} else {
					echo JText::_( 'FLEXI_ORIGINAL_CONTENT_ALREADY_SET' );
				}
				?>
			<?php endif; ?>
			</div>
		
			<div class="fcclear"></div>
			<label id="langassocs-lbl" for="langassocs" class="flexi_label" >
				<?php echo JText::_( 'FLEXI_ASSOC_TRANSLATIONS' );?>
			</label>
			
			<div class="container_fcfield container_fcfield_name_langassocs">
			<?php
			if ( !empty($this->lang_assocs) )
			{
				$row_modified = 0;
				foreach($this->lang_assocs as $assoc_item) {
					if ($assoc_item->id == $this->item->lang_parent_id) {
						$row_modified = strtotime($assoc_item->modified);
						if (!$row_modified)  $row_modified = strtotime($assoc_item->created);
					}
				}
				
				foreach($this->lang_assocs as $assoc_item)
				{
					if ($assoc_item->id==$this->item->id) continue;
					
					$_link  = 'index.php?option=com_flexicontent&view='.FLEXI_ITEMVIEW.'&task=edit&id='. $assoc_item->id;
					$_title = htmlspecialchars(JText::_( 'FLEXI_EDIT_ASSOC_TRANSLATION' ), ENT_COMPAT, 'UTF-8').':: ['. $assoc_item->lang .'] '. htmlspecialchars($assoc_item->title, ENT_COMPAT, 'UTF-8');
					echo "<a class='fc_assoc_translation editlinktip hasTip' target='_blank' href='".$_link."' title='".$_title."' >";
					//echo $assoc_item->id;
					if ( !empty($assoc_item->lang) && !empty($this->langs->{$assoc_item->lang}->imgsrc) ) {
						echo ' <img src="'.$this->langs->{$assoc_item->lang}->imgsrc.'" alt="'.$assoc_item->lang.'" />';
					} else if( !empty($assoc_item->lang) ) {
						echo $assoc_item->lang=='*' ? JText::_("All") : $assoc_item->lang;
					}
					
					$assoc_modified = strtotime($assoc_item->modified);
					if (!$assoc_modified)  $assoc_modified = strtotime($assoc_item->created);
					if ( $assoc_modified < $row_modified ) echo "(!)";
					echo "</a>";
				}
			}
			?>
			</div>
		<?php endif; /* IF enable_translation_groups */ ?>
		
	</fieldset>
<?php $captured['language'] = ob_get_clean(); endif;



if ( $this->perms['canright'] ) : ob_start(); // perms ?>
	<?php
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
		<div id="tabacces">
			<div id="accessrules"><?php echo $this->form->getInput('rules'); ?></div>
		</div>
		<div id="notabacces">
		<?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT_DESC' ); ?>
		</div>
	</fieldset>
<?php $captured['perms'] = ob_get_clean(); endif;



if ($typeid && $this->params->get('usepublicationdetails_fe', 1) && (!FLEXI_J16GE || $this->perms[$publication_priv]) ) : // timezone_info, publication_details ?>

	<?php ob_start(); ?>
		<?php
		// Dates displayed in the item form, are in user timezone for J2.5, and in site's default timezone for J1.5
		$site_zone = JFactory::getApplication()->getCfg('offset');
		$user_zone = JFactory::getUser()->getParam('timezone', $site_zone);
		if (FLEXI_J16GE) {
			$tz = new DateTimeZone( $user_zone );
			$tz_offset = $tz->getOffset(new JDate()) / 3600;
		} else {
			$tz_offset = $site_zone;
		}
		$tz_info =  $tz_offset > 0 ? ' UTC +' . $tz_offset : ' UTC ' . $tz_offset;
		if (FLEXI_J16GE) $tz_info .= ' ('.$user_zone.')';
		$msg = JText::sprintf( FLEXI_J16GE ? 'FLEXI_DATES_IN_USER_TIMEZONE_NOTE' : 'FLEXI_DATES_IN_SITE_TIMEZONE_NOTE', ' ', $tz_info );
		echo sprintf( $alert_box, '', 'info', 'fc-nobgimage', $msg );
		?>
	<?php $captured['timezone_info'] = ob_get_clean(); ?>
		
	<?php if ($this->perms['isSuperAdmin'] && $this->params->get('usepublicationdetails_fe', 1) == 2 ) : ob_start(); ?>
		<fieldset class="flexi_params panelform">
			<?php echo $this->form->getLabel('created_by'); ?>
			<div class="container_fcfield"><?php echo $this->form->getInput('created_by'); ?></div>
		</fieldset>
	<?php $captured['createdby'] = ob_get_clean(); endif; ?>
	
	<?php if ($this->perms['editcreationdate'] && $this->params->get('usepublicationdetails_fe', 1) == 2 ) : ob_start(); ?>
		<fieldset class="flexi_params panelform">
			<?php echo $this->form->getLabel('created'); ?>
			<div class="container_fcfield"><?php echo $this->form->getInput('created'); ?></div>
		</fieldset>
	<?php $captured['created'] = ob_get_clean(); endif; ?>
	
	<?php ob_start(); ?>
		<fieldset class="flexi_params panelform">
			<?php echo $this->form->getLabel('created_by_alias'); ?>
			<div class="container_fcfield"><?php echo $this->form->getInput('created_by_alias'); ?></div>
		</fieldset>
	<?php $captured['created_by_alias'] = ob_get_clean(); ?>
	
	<?php ob_start(); ?>
		<fieldset class="flexi_params panelform">
			<?php echo $this->form->getLabel('publish_up'); ?>
			<div class="container_fcfield"><?php echo $this->form->getInput('publish_up'); ?></div>
		</fieldset>
	<?php $captured['publish_up'] = ob_get_clean(); ?>
	
	<?php ob_start(); ?>
		<fieldset class="flexi_params panelform">
			<?php echo $this->form->getLabel('publish_down'); ?>
			<div class="container_fcfield"><?php echo $this->form->getInput('publish_down'); ?></div>
		</fieldset>
	<?php $captured['publish_down'] = ob_get_clean(); ?>
	
	<?php ob_start(); ?>
		<fieldset class="flexi_params panelform">
			<?php echo $this->form->getLabel('access'); ?>
			<?php if ($this->perms['canacclvl']) :?>
				<div class="container_fcfield"><?php echo $this->form->getInput('access'); ?></div>
			<?php else :?>
				<div class="container_fcfield"><span class="label"><?php echo $this->item->access_level; ?></span></div>
			<?php endif; ?>
		</fieldset>
	<?php $captured['access'] = ob_get_clean(); ?>
<?php endif;



if ( $typeid && $this->params->get('usemetadata_fe', 1) ) : ob_start(); // metadata ?>
	<fieldset class="panelform params_set">
		<legend>
			<?php echo JText::_( 'FLEXI_META' ); ?>
		</legend>
		
		<?php if ( $this->params->get('usemetadata_fe', 1) >= 1) : ?>
			<div class="fcclear"></div>
			<?php echo $this->form->getLabel('metadesc'); ?>
				
			<div class="container_fcfield">
				<?php	if ( isset($this->item->item_translations) ) :?>
					
					<!-- tabber start -->
					<div class="fctabber" style='display:inline-block;'>
						<div class="tabbertab" style="padding: 0px;" >
							<h3 class="tabberheading"> <?php echo '-'.$this->itemlang->name.'-'; // $t->name; ?> </h3>
							<?php echo $this->form->getInput('metadesc'); ?>
						</div>
						<?php foreach ($this->item->item_translations as $t): ?>
							<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*') : ?>
								<div class="tabbertab" style="padding: 0px;" >
									<h3 class="tabberheading"> <?php echo $t->name; // $t->shortcode; ?> </h3>
									<?php
									$ff_id = 'jfdata_'.$t->shortcode.'_metadesc';
									$ff_name = 'jfdata['.$t->shortcode.'][metadesc]';
									?>
									<textarea id="<?php echo $ff_id; ?>" class="fcfield_textareaval" rows="3" cols="80" name="<?php echo $ff_name; ?>"><?php echo @$t->fields->metadesc->value; ?></textarea>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
					<!-- tabber end -->
					
				<?php else : ?>
					<?php echo $this->form->getInput('metadesc'); ?>
				<?php endif; ?>
			</div>
				
			<div class="fcclear"></div>
			<?php echo $this->form->getLabel('metakey'); ?>
			
			<div class="container_fcfield">
				<?php	if ( isset($this->item->item_translations) ) :?>
					
					<!-- tabber start -->
					<div class="fctabber" style='display:inline-block;'>
						<div class="tabbertab" style="padding: 0px;" >
							<h3 class="tabberheading"> <?php echo '-'.$this->itemlang->name.'-'; // $t->name; ?> </h3>
							<?php echo $this->form->getInput('metakey'); ?>
						</div>
						<?php foreach ($this->item->item_translations as $t): ?>
							<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*') : ?>
								<div class="tabbertab" style="padding: 0px;" >
									<h3 class="tabberheading"> <?php echo $t->name; // $t->shortcode; ?> </h3>
									<?php
									$ff_id = 'jfdata_'.$t->shortcode.'_metakey';
									$ff_name = 'jfdata['.$t->shortcode.'][metakey]';
									?>
									<textarea id="<?php echo $ff_id; ?>" class="fcfield_textareaval" rows="3" cols="80" name="<?php echo $ff_name; ?>"><?php echo @$t->fields->metakey->value; ?></textarea>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
					<!-- tabber end -->
					
				<?php else : ?>
					<?php echo $this->form->getInput('metakey'); ?>
				<?php endif; ?>
				
			</div>
		<?php endif; ?>
		
		
		<?php if ($this->params->get('usemetadata_fe', 1) == 2 ) :?>
			<?php foreach($this->form->getGroup('metadata') as $field) : ?>
			<div class="fcclear"></div>
			<?php if ($field->hidden) : ?>
				<span style="display:none !important;">
					<?php echo $field->input; ?>
				</span>
			<?php else : ?>
				<?php echo $field->label; ?>
				<div class="container_fcfield">
					<?php echo $field->input;?>
				</div>
			<?php endif; ?>
			<?php endforeach; ?>
		<?php endif; ?>
		
	</fieldset>
<?php $captured['metadata'] = ob_get_clean(); endif;



if ( $typeid && $this->params->get('useseoconf_fe', 0) ) : ob_start(); // seoconf ?>
	<fieldset class="panelform params_set">
		<legend>
			<?php echo JText::_( 'FLEXI_SEO' ); ?>
		</legend>
		
		<?php foreach ($this->form->getFieldset('params-seoconf') as $field) : ?>
		<div class="fcclear"></div>
		<?php echo $field->label; ?>
		<div class="container_fcfield">
			<?php echo $field->input;?>
		</div>
		<?php endforeach; ?>	
	</fieldset>
<?php $captured['seoconf'] = ob_get_clean(); endif;



if ( $typeid && $this->params->get('usedisplaydetails_fe') ) : ob_start(); ?>
<fieldset class="panelform params_set">
	<legend>
		<?php echo JText::_( 'FLEXI_DISPLAYING' ); ?>
	</legend>
	
	<?php foreach ($fieldSets as $name => $fieldSet) : ?>
		<?php
		$fieldsetname = str_replace("params-", "", $name);
		if ( $fieldsetname=='basic') {
			if ( $this->params->get('usedisplaydetails_fe') < 1 ) continue;
		} else if ( $fieldsetname=='advanced') {
			if ( $this->params->get('usedisplaydetails_fe') < 2 ) continue;
		} else {
			continue;
		}
		$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_'.$name.'_FIELDSET_LABEL';
		?>
				
		<fieldset class="flexi_params panelform">
			<!--legend><?php echo JText::_($label); ?></legend-->
					
			<?php foreach ($this->form->getFieldset($name) as $field) : ?>
				<?php if ( $this->params->get('allowdisablingcomments_fe') && $fieldsetname=='advanced' && $field->__get('fieldname')=='comments')  continue; ?>
				<div class="fcclear"></div>
				<?php echo $field->label; ?>
				<div class="container_fcfield">
					<?php echo $field->input;?>
				</div>
			<?php endforeach; ?>
		</fieldset>
	<?php endforeach; ?>
	
</fieldset>
<?php $captured['display_params'] = ob_get_clean(); endif;




if ( $typeid && $this->perms['cantemplates'] && $this->params->get('selecttheme_fe') ) : ?>

	<?php if ( $this->params->get('selecttheme_fe') >= 1 ) : ob_start(); ?>
		<?php foreach($this->form->getFieldset('themes') as $field) : ?>
			<div class="fcclear"></div>
			<?php if ($field->hidden) : ?>
				<span style="display:none !important;">
					<?php echo $field->input; ?>
				</span>
			<?php else : ?>
				<?php echo $field->label; ?>
				<div class="container_fcfield">
					<?php echo $field->input;?>
				</div>
			<?php endif; ?>
		<?php endforeach; ?>
		
		<div class="fcclear"></div>
		<blockquote id='__content_type_default_layout__'>
			<?php echo JText::sprintf( 'FLEXI_USING_CONTENT_TYPE_LAYOUT', $this->tparams->get('ilayout') ); ?>
		</blockquote>
	
	<?php $captured['layout_selection'] = ob_get_clean(); endif;
	
	
	
	if ( $this->params->get('selecttheme_fe') >= 2 ) : ob_start();
		echo JHtml::_('sliders.start','theme-sliders-'.$this->form->getValue("id"), array('useCookie'=>1));
		echo '<h3 class="themes-title">' . JText::_( 'FLEXI_PARAMETERS_LAYOUT_EXPLANATION' ) . '</h3>';
		$groupname = 'attribs';  // Field Group name this is for name of <fields name="..." >
			
		foreach ($this->tmpls as $tmpl) :
			$fieldSets = $tmpl->params->getFieldsets($groupname);
			foreach ($fieldSets as $fsname => $fieldSet) :
				$label = !empty($fieldSet->label) ? $fieldSet->label : JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name;
				echo JHtml::_('sliders.panel',JText::_($label), $tmpl->name.'-'.$fsname.'-options');
				if (isset($fieldSet->description) && trim($fieldSet->description)) :
					echo '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>';
				endif;
				?>
				<fieldset class="panelform">
					<?php foreach ($tmpl->params->getFieldset($fsname) as $field) :
						$fieldname =  $field->__get('fieldname');
						$value = $tmpl->params->getValue($fieldname, $groupname, $this->item->itemparams->get($fieldname));
						echo $tmpl->params->getLabel($fieldname, $groupname);
						echo
							str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_', 
								str_replace('[attribs]', '[layouts]['.$tmpl->name.']',
									$tmpl->params->getInput($fieldname, $groupname, $value)
								)
							);
					endforeach; ?>
				</fieldset>
			<?php endforeach; ?>
		<?php endforeach; ?>
				
		<?php echo JHtml::_('sliders.end');
	$captured['layout_params'] = ob_get_clean(); endif; ?>
	
<?php endif; // end of template: layout_selection, layout_params




ob_start();
if ($this->fields && $typeid) :
	
	$this->document->addScriptDeclaration("
		jQuery(document).ready(function() {
			jQuery('#jform_type_id').change(function() {
				if (jQuery('#jform_type_id').val() != '".$typeid."')
					jQuery('#fc-change-warning').css('display', 'block');
				else
					jQuery('#fc-change-warning').css('display', 'none');
			});
		});
	");
	?>
	
	<div class="fc_edit_container_full">
		
		<?php
		$hidden = array('fcloadmodule', 'fcpagenav', 'toolbar');
		$noplugin = sprintf( $alert_box, '', 'warning', 'fc-nobgimage', JText::_( 'FLEXI_PLEASE_PUBLISH_PLUGIN' ) );
		$row_k = 0;
		foreach ($this->fields as $field_name => $field)
		{
			// SKIP frontend hidden fields from this listing
			if ( $field->iscore &&  isset($tab_fields['fman'][ $field->field_type ]) ) {
				if ( !isset($captured[ $field->field_type ]) ) continue;
				echo $captured[ $field->field_type ];
				echo "\n<div class='fcclear'></div>\n";
				continue;
			} else if (
				($field->iscore && $field->field_type!='maintext')  ||
				$field->parameters->get('frontend_hidden')  ||
				(in_array($field->field_type, $hidden) && empty($field->html)) ||
				in_array($field->formhidden, array(1,3))
			) continue;
			
			if ( $field->field_type=='maintext' && isset($all_tab_fields['maintext']) ) {
				ob_start();
			}
			
			// check to SKIP (hide) field e.g. description field ('maintext'), alias field etc
			if ( $this->tparams->get('hide_'.$field->field_type) ) continue;
			
			$not_in_tabs = "";
			if ($field->field_type=='groupmarker') {
				echo $field->html;
				continue;
			} else if ($field->field_type=='coreprops') {
				$props_type = $field->parameters->get('props_type');
				if ( isset($tab_fields['fman'][$props_type]) ) {
					if ( !isset($captured[ $props_type ]) ) continue;
					echo $captured[ $props_type ];
					echo "\n<div class='fcclear'></div>\n";
				}
				continue;
			}
			
			// Decide label classes, tooltip, etc
			$lbl_class = 'flexi_label';
			$lbl_title = '';
			// field has tooltip
			$edithelp = $field->edithelp ? $field->edithelp : 1;
			if ( $field->description && ($edithelp==1 || $edithelp==2) ) {
				 $lbl_class .= ' hasTip'.($edithelp==2 ? ' fc_tooltip_icon_fe' : '');
				 $lbl_title = '::'.htmlspecialchars($field->description, ENT_COMPAT, 'UTF-8');
			}
			// field is required
			$required = $field->parameters->get('required', 0 );
			if ($required)  $lbl_class .= ' required';
			
			// Some fields may force a container width ?
			$row_k = 1 - $row_k;
			$width = $field->parameters->get('container_width', '' );
			$width = !$width ? '' : 'width:' .$width. ($width != (int)$width ? 'px' : '');
			$container_class = "fcfield_row".$row_k." container_fcfield container_fcfield_id_".$field->id." container_fcfield_name_".$field->name;
			?>
			
			<div class='fcclear'></div>
			<label for="<?php echo (FLEXI_J16GE ? 'custom_' : '').$field->name;?>" for_bck="<?php echo (FLEXI_J16GE ? 'custom_' : '').$field->name;?>" class="<?php echo $lbl_class;?>" title="<?php echo $lbl_title;?>" >
				<?php echo $field->label; ?>
			</label>
			
			<div style="<?php echo $width; ?>;" class="<?php echo $container_class; ?>" id="container_fcfield_<?php echo $field->id; ?>">
				<?php echo ($field->description && $edithelp==3)  ?  sprintf( $alert_box, '', 'info', 'fc-nobgimage', $field->description )  :  ''; ?>
				
			<?php // CASE 1: CORE 'description' FIELD with multi-tabbed editing of joomfish (J1.5) or falang (J2.5+)
				if ($field->field_type=='maintext' && isset($this->item->item_translations) ) : ?>
				
				<!-- tabber start -->
				<div class="fctabber" style=''>
					<div class="tabbertab" style="padding: 0px;" >
						<h3 class="tabberheading"> <?php echo '- '.$this->itemlang->name.' -'; // $t->name; ?> </h3>
						<?php
							$field_tab_labels = & $field->tab_labels;
							$field_html       = & $field->html;
							echo !is_array($field_html) ? $field_html : flexicontent_html::createFieldTabber( $field_html, $field_tab_labels, "");
						?>
					</div>
					<?php foreach ($this->item->item_translations as $t): ?>
						<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*') : ?>
							<div class="tabbertab" style="padding: 0px;" >
								<h3 class="tabberheading"> <?php echo $t->name; // $t->shortcode; ?> </h3>
								<?php
								$field_tab_labels = & $t->fields->text->tab_labels;
								$field_html       = & $t->fields->text->html;
								echo !is_array($field_html) ? $field_html : flexicontent_html::createFieldTabber( $field_html, $field_tab_labels, "");
								?>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<!-- tabber end -->
				
			<?php elseif ( !is_array($field->html) ) : /* CASE 2: NORMAL FIELD non-tabbed */ ?>
				
				<?php echo isset($field->html) ? $field->html : $noplugin; ?>
				
			<?php else : /* MULTI-TABBED FIELD e.g textarea, description */ ?>
				
				<!-- tabber start -->
				<div class="fctabber">
				<?php foreach ($field->html as $i => $fldhtml): ?>
					<?php
						// Hide field when it has no label, and skip creating tab
						$not_in_tabs .= !isset($field->tab_labels[$i]) ? "<div style='display:none!important'>".$field->html[$i]."</div>" : "";
						if (!isset($field->tab_labels[$i]))	continue;
					?>
							
					<div class="tabbertab">
						<h3 class="tabberheading"> <?php echo $field->tab_labels[$i]; // Current TAB LABEL ?> </h3>
						<?php
							echo $not_in_tabs;      // Output hidden fields (no tab created), by placing them inside the next appearing tab
							$not_in_tabs = "";      // Clear the hidden fields variable
							echo $field->html[$i];  // Current TAB CONTENTS
						?>
					</div>
							
				<?php endforeach; ?>
				</div>
				<!-- tabber end -->
				<?php echo $not_in_tabs;      // Output ENDING hidden fields, by placing them outside the tabbing area ?>
						
			<?php endif; /* END MULTI-TABBED FIELD */ ?>
			
			</div>
			
		<?php
			if ( $field->field_type=='maintext' && isset($all_tab_fields['maintext']) ) {
				$captured['maintext'] = ob_get_clean();
			}
		}
		?>
		
	</div>

<?php else : /* NO TYPE SELECTED */ ?>

	<?php if ( $isnew ) : // new item, since administrator did not limit this, display message (user allowed to select item type) ?>
		<input name="jform[type_id_not_set]" value="1" type="hidden" />
		<?php echo sprintf( $alert_box, '', 'note', '', JText::_( 'FLEXI_CHOOSE_ITEM_TYPE' ) ); ?>
	<?php else : // existing item that has no custom fields, warn the user ?>
		<?php echo sprintf( $alert_box, '', 'warning', '', JText::_( 'FLEXI_NO_FIELDS_TO_TYPE' ) ); ?>
	<?php	endif; ?>
	
<?php	endif;
$captured['fields_manager'] = ob_get_clean();




// *******************************************
// Find fields not congfigured to be displayed
// *******************************************
$duplicate_display = array();
$_tmp = $tab_fields;
foreach($_tmp as $tabname => $fieldnames) {
	//echo "$tabname <br/>  %% ";
	//print_r($fieldnames); echo "<br/>";
	foreach($fieldnames as $fn => $i) {
		if ( isset($shown[$fn]) ) {
			//echo " -- $fn <br/>";
			$duplicate_display[$fn] = 1;
			unset( $tab_fields[$tabname][$fn] );
			continue;
		}
		$shown[$fn] = 1;
		if ( !isset($captured[$fn]) ) {
			unset( $tab_fields[$tabname][$fn] );
			continue;
		}
	}
}



// **********************
// CONFIGURATION WARNINGS
// **********************
if ( count($duplicate_display) ) :
	$msg = JText::sprintf( 'FLEXI_FORM_FIELDS_DISPLAYED_TWICE', "<b>".implode(', ', array_keys($duplicate_display))."</b>");
	echo sprintf( $alert_box, '', 'error', '', $msg );
endif;

if ( count($coreprop_missing) ) :
	$msg = JText::sprintf( 'FLEXI_FORM_PLACER_FIELDS_MISSING', "<b>".implode(', ', array_keys($coreprop_missing))."</b>");
	echo sprintf( $alert_box, '', 'error', '', $msg );
endif;



// ************
// ABOVE TABSET
// ************
if ( count($tab_fields['above']) ) : ?>
<div class="fc_edit_container_full">
	
	<?php foreach($tab_fields['above'] as $fn => $i) : ?>
		<div class="fcclear"></div>
		<?php echo $captured[$fn]; unset($captured[$fn]); ?>
	<?php endforeach; ?>
	
</div>
<?php endif;



// *****************
// MAIN TABSET START
// *****************
$tabSetCnt++;
$tabCnt[$tabSetCnt] = 0;
?>

<!-- tabber start -->
<div class='fctabber fields_tabset' id='fcform_tabset_<?php echo $tabSetCnt; ?>' >
	



<?php	
// *********
// BASIC TAB
// *********
if ( count($tab_fields['tab01']) ) :
	$tab_lbl = isset($tab_titles['tab01']) ? $tab_titles['tab01'] : JText::_( 'FLEXI_BASIC' );
	?>
	<div class='tabbertab' id='fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>' >
		<h3 class="tabberheading"> <?php echo $tab_lbl; ?> </h3>
		
		<?php foreach($tab_fields['tab01'] as $fn => $i) : ?>
			<div class="fcclear"></div>
			<?php echo $captured[$fn]; unset($captured[$fn]); ?>
		<?php endforeach; ?>
		
	</div>
<?php endif;


// ***************
// DESCRIPTION TAB
// ***************
if ( count($tab_fields['tab02']) ) :
	$tab_lbl = isset($tab_titles['tab02']) ? $tab_titles['tab02'] : JText::_( 'FLEXI_DESCRIPTION' );
	?>
	<div class='tabbertab' id='fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>' >
		<h3 class="tabberheading"> <?php echo $tab_lbl; ?> </h3>
		
		<?php foreach($tab_fields['tab02'] as $fn => $i) : ?>
			<div class="fcclear"></div>
			<?php echo $captured[$fn]; unset($captured[$fn]); ?>
		<?php endforeach; ?>
		
	</div>
<?php endif;



// *****************
// CUSTOM FIELDS TAB
// *****************
if ( count($tab_fields['tab03']) ) :
	$tab_lbl = isset($tab_titles['tab03']) ? $tab_titles['tab03'] : JText::_( 'FLEXI_FIELDS' );
	?>
	<div class='tabbertab' id='fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>' >
		<h3 class="tabberheading"> <?php echo $tab_lbl; ?> </h3>
		
		<?php foreach($tab_fields['tab03'] as $fn => $i) : ?>
			<div class="fcclear"></div>
			<?php echo $captured[$fn]; unset($captured[$fn]); ?>
		<?php endforeach; ?>
		
	</div>
<?php endif;



if ($typeid) : // hide items parameters (standard, extended, template) if content type is not selected ?>

	<?php
	
	// **************
	// PUBLISHING TAB
	// **************
	// J2.5 requires Edit State privilege while J1.5 requires Edit privilege
	$publication_priv = FLEXI_J16GE ? 'canpublish' : 'canedit';
	if ( count($tab_fields['tab04']) ) : ?>
		<?php $tab_lbl = isset($tab_titles['tab04']) ? $tab_titles['tab04'] : JText::_( 'FLEXI_PUBLISHING' ); ?>
		<div class='tabbertab' id='fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>' >
			<h3 class="tabberheading"> <?php echo $tab_lbl; ?> </h3>
			
			<fieldset class="flexi_params fc_edit_container_full">
			<?php foreach($tab_fields['tab04'] as $fn => $i) : ?>
				<div class="fcclear"></div>
				<?php echo $captured[$fn]; unset($captured[$fn]); ?>
			<?php endforeach; ?>
			</fieldset>
			
		</div> <!-- end tab -->

	<?php endif;
	
	
	
	// **************
	// META / SEO TAB
	// **************
	if ( count($tab_fields['tab05']) ) : ?>
		<?php $tab_lbl = isset($tab_titles['tab05']) ? $tab_titles['tab05'] : JText::_( 'FLEXI_META_SEO' ); ?>
		<div class='tabbertab' id='fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>' >
			<h3 class="tabberheading"> <?php echo $tab_lbl; ?> </h3>
	
			<?php foreach($tab_fields['tab05'] as $fn => $i) : ?>
				<div class="fcclear"></div>
				<?php echo $captured[$fn]; unset($captured[$fn]); ?>
			<?php endforeach; ?>
			
		</div> <!-- end tab -->
	<?php endif;
	
	
	

	// *************************
	// DISPLAYING PARAMETERS TAB
	// *************************
	if ( count($tab_fields['tab06']) ) : ?>
		<?php $tab_lbl = isset($tab_titles['tab06']) ? $tab_titles['tab06'] : JText::_( 'FLEXI_DISPLAYING' ); ?>
		<div class='tabbertab' id='fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>' >
			<h3 class="tabberheading"> <?php echo $tab_lbl; ?> </h3>
	
			<?php foreach($tab_fields['tab06'] as $fn => $i) : ?>
				<div class="fcclear"></div>
				<?php echo $captured[$fn]; unset($captured[$fn]); ?>
			<?php endforeach; ?>
			
		</div>
	<?php endif;



	// *********************
	// JOOMLA IMAGE/URLS TAB
	// *********************
	if (JComponentHelper::getParams('com_content')->get('show_urls_images_frontend', 0) ) : ?>
		<div class='tabbertab' id='fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>' >
			<h3 class="tabberheading"> <?php echo JText::_('Compatibility'); ?> </h3>
			
			<?php
			$fields_grps_compatibility = array('images', 'urls');
			foreach ($fields_grps_compatibility as $name => $fields_grp_name) :
			?>
			
			<fieldset class="flexi_params fc_edit_container_full">
				<?php foreach ($this->form->getGroup($fields_grp_name) as $field) : ?>
					<div class="fcclear"></div>
					<?php if ($field->hidden): ?>
						<span style="visibility:hidden !important;">
							<?php echo $field->input; ?>
						</span>
					<?php else: ?>
						<?php echo $field->label; ?>
						<div class="container_fcfield">
							<?php echo $field->input;?>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</fieldset>
			
			<?php endforeach; ?>
			
		</div>
	<?php endif;



	// ************
	// TEMPLATE TAB
	// ************
	if ( count($tab_fields['tab07']) ) : ?>
		<?php $tab_lbl = isset($tab_titles['tab07']) ? $tab_titles['tab07'] : JText::_( 'FLEXI_TEMPLATE' ); ?>
		<div class='tabbertab' id='fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>' >
			<h3 class="tabberheading"> <?php echo $tab_lbl; ?> </h3>
			
			<fieldset class="flexi_params fc_edit_container_full">
				
				<?php foreach($tab_fields['tab07'] as $fn => $i) : ?>
					<div class="fcclear"></div>
					<?php echo $captured[$fn]; unset($captured[$fn]); ?>
				<?php endforeach; ?>

			</fieldset>
			
		</div> <!-- end tab -->
		
	<?php endif; ?>
	
<?php	endif; // end of existing item ?>


<?php
// ***************
// MAIN TABSET END
// ***************
?>
</div> <!-- end of tab set -->
	
	
<?php
// ************
// BELOW TABSET
// ************
if ( count($tab_fields['below']) || count($captured) ) : ?>
<div class="fc_edit_container_full">
	
	<?php foreach($tab_fields['below'] as $fn => $i) : ?>
		<div class="fcclear"></div>
		<?php echo $captured[$fn]; unset($captured[$fn]); ?>
	<?php endforeach; ?>
	
	<?php foreach($captured as $fn => $i) : ?>
		<div class="fcclear"></div>
		<?php echo $captured[$fn]; unset($captured[$fn]); ?>
	<?php endforeach; ?>
	
</div>
<?php endif;

	
	
// **************
// REMAINING FORM
// **************
?>
		
		<br class="clear" />
		<?php echo JHTML::_( 'form.token' ); ?>
		<input type="hidden" name="task" id="task" value="" />
		<input type="hidden" name="option" value="com_flexicontent" />
		<input type="hidden" name="referer" value="<?php echo $referer; ?>" />
		<?php if ( $isnew && $typeid ) : ?>
			<input type="hidden" name="jform[type_id]" value="<?php echo $typeid; ?>" />
		<?php endif;?>
		<?php echo $this->form->getInput('id');?>
		
		<?php if (!$this->perms['canright']) : ?>
			<input type="hidden" id="jformrules" name="jform[rules]" value="0" />
		<?php endif; ?>
		<?php if ( $isnew ) echo $this->submitConf; ?>
		
		<input type="hidden" name="unique_tmp_itemid" value="<?php echo JRequest::getVar( 'unique_tmp_itemid' );?>" />

	</form>
</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
