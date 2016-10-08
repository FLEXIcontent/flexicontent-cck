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
$disable_langs = $this->params->get('disable_languages_fe', array());

// Parameter configured to be displayed
$fieldSets = $this->form->getFieldsets('attribs');
		
// J2.5+ requires Edit State privilege while J1.5 requires Edit privilege
$publication_priv = FLEXI_J16GE ? 'canpublish' : 'canedit';

$task_items = 'task=';
$tags_task  = 'task=';

// For tabsets/tabs ids (focusing, etc)
$tabSetCnt = -1;
$tabSetMax = -1;
$tabCnt = array();
$tabSetStack = array();

$secondary_displayed =
  ($this->menuCats  && $this->menuCats->cid) ||   // New Content  -with-  Menu Override, check if secondary categories were enabled in menu
  (!$this->menuCats && $this->lists['cid']);      // New Content but  -without-  Menu override ... OR Existing Content, check if secondary are permitted  OR already set
$cats_canselect_sec =
	($this->menuCats && $this->menuCats->cancid) ||
	(!$this->menuCats && $this->perms['multicat'] && $this->perms['canchange_seccat']) ;
$usetags_fe = $this->params->get('usetags_fe', 1);
$tags_displayed = $typeid && ( ($this->perms['cantags'] && $usetags_fe) || (count(@$this->usedtagsdata) && $usetags_fe==2) ) ;

// Create reusable html code
$infoimage = $this->params->get('use_font_icons', 1) ? '<i class="icon-comment" style="color:darkgray"></i>' : JHTML::image ( 'administrator/components/com_flexicontent/assets/images/comment.png', JText::_( 'FLEXI_NOTES' ) );
$close_btn = FLEXI_J30GE ? '<a class="close" data-dismiss="alert">&#215;</a>' : '<a class="fc-close" onclick="this.parentNode.parentNode.removeChild(this.parentNode);">&#215;</a>';
$alert_box = FLEXI_J30GE ? '<div %s class="alert alert-%s %s">'.$close_btn.'%s</div>' : '<div %s class="fc-mssg fc-%s %s">'.$close_btn.'%s</div>';
$btn_class = FLEXI_J30GE ? 'btn' : 'fc_button';
$tip_class = FLEXI_J30GE ? 'hasTooltip' : 'hasTip';

// Calculate refer parameter for returning to this page when user ends editing/submitting
$return = JRequest::getString('return', '', 'get');
$referer = $return ? base64_decode( $return ) : @ $_SERVER['HTTP_REFERER'];
$referer_encoded = htmlspecialchars($referer, ENT_COMPAT, 'UTF-8');

// Print message about zero allowed categories
if ( !$this->lists['catid'] && !$this->menuCats ) {
	echo sprintf( $alert_box, '', 'warning', '', JText::_("FLEXI_CANNOT_SUBMIT_IN_TYPE_ALLOWED_CATS") );
	return;
}


// Placement configuration
$via_core_field   = $this->placementConf['via_core_field'];
$via_core_prop    = $this->placementConf['via_core_prop'];
$placeable_fields = $this->placementConf['placeable_fields'];
$tab_fields       = $this->placementConf['tab_fields'];
$tab_titles       = $this->placementConf['tab_titles'];
$tab_icocss       = $this->placementConf['tab_icocss'];
$all_tab_fields   = $this->placementConf['all_tab_fields'];
$coreprop_missing = $this->placementConf['coreprop_missing'];


// add extra css/js for the edit form
if ($this->params->get('form_extra_css'))    $this->document->addStyleDeclaration($this->params->get('form_extra_css'));
if ($this->params->get('form_extra_css_fe')) $this->document->addStyleDeclaration($this->params->get('form_extra_css_fe'));
if ($this->params->get('form_extra_js'))     $this->document->addScriptDeclaration($this->params->get('form_extra_js'));
if ($this->params->get('form_extra_js_fe'))  $this->document->addScriptDeclaration($this->params->get('form_extra_js_fe'));

// Load JS tabber lib
$this->document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js', FLEXI_VHASH);
$this->document->addStyleSheetVersion(JURI::root(true).'/components/com_flexicontent/assets/css/tabber.css', FLEXI_VHASH);
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs

if ( $this->perms['cantags'] && $this->params->get('usetags_fe', 1)==1 )
{
	//$this->document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/librairies/jquery-autocomplete/jquery.bgiframe.min.js', FLEXI_VHASH);
	//$this->document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/librairies/jquery-autocomplete/jquery.ajaxQueue.js', FLEXI_VHASH);
	//$this->document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/librairies/jquery-autocomplete/jquery.autocomplete.min.js', FLEXI_VHASH);
	$this->document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/jquery.pager.js', FLEXI_VHASH);     // e.g. pagination for item versions
	$this->document->addScriptVersion(JURI::root(true).'/components/com_flexicontent/assets/js/jquery.autogrow.js', FLEXI_VHASH);  // e.g. autogrow version comment textarea

	//$this->document->addStyleSheetVersion(JURI::root(true).'/components/com_flexicontent/librairies/jquery-autocomplete/jquery.autocomplete.css', FLEXI_VHASH);

	JText::script("FLEXI_DELETE_TAG", true);
	JText::script("FLEXI_ENTER_TAG", true);

	$this->document->addScriptDeclaration("
		jQuery(document).ready(function(){
			
			jQuery('.deletetag').click(function(e){
				jQuery(this).parent().remove();
				return false;
			});
			
			var tagInput = jQuery('#input-tags');
			
			tagInput.keydown(function(event)
			{
				if( (event.keyCode==13) )
				{
					var el = jQuery(event.target);
					if (el.val()=='') return false; // No tag to assign / create
					
					var selection_isactive = jQuery('.ui-autocomplete .ui-state-focus').length != 0;
					if (selection_isactive) return false; // Enter pressed, while autocomplete item is focused, autocomplete \'select\' event handler will handle this
					
					var data_id   = el.data('tagid');
					var data_name = el.data('tagname');
					//window.console.log( 'User input: '+el.val() + ' data-tagid: ' + data_id + ' data-tagname: \"'+ data_name + '\"');
					
					if (el.val() == data_name && data_id!='' && data_id!='0') {
						//window.console.log( 'Assigning found tag: (' + data_id + ', \"' + data_name + '\")');
						addToList(data_id, data_name);
						el.autocomplete('close');
					}
					else {
						//window.console.log( 'Retrieving (create-if-missing) tag: \"' + el.val() + '\"');
						addtag(0, el.val());
						el.autocomplete('close');
					}
					
					el.val('');  //clear existing value
					return false;
				}
			});
			
			jQuery.ui.autocomplete( {
				source: function( request, response ) {
					var el   = jQuery(this.element);
					var term = request.term;
					//window.console.log( 'Getting tags for \"' + term + '\" ...');
					jQuery.ajax({
						url: '".JURI::base(true)."/index.php?option=com_flexicontent&".$task_items."viewtags&format=raw&".JSession::getFormToken()."=1',
						dataType: 'json',
						data: {
							q: request.term
						},
						success: function( data ) {
							//window.console.log( '... received tags for \"' + term + '\"');
							response( jQuery.map( data, function( item ) {
								if (el.val()==item.name) {
									//window.console.log( 'Found exact TAG match, (' + item.id + ', \"' + item.name + '\")');
									el.data('tagid',   item.id);
									el.data('tagname', item.name);
								}
								return { label: item.name, value: item.id };
							}));
						}
					});
				},
				delay: 200,
				minLength: 1,
				focus: function ( event, ui ) {
					//window.console.log( (ui.item  ?  'current ID: ' + ui.item.value + 'current Label: ' + ui.item.label :  'Nothing selected') );
					
					var el = jQuery(event.target);
					if (ui.item.value!='' && ui.item.value!='0')
					{
						el.val(ui.item.label);
					}
					el.data('tagid',   ui.item.value);
					el.data('tagname', ui.item.label);
					
					event.preventDefault();  // Prevent default behaviour of setting 'ui.item.value' into the input
				},
				select: function( event, ui ) {
					//window.console.log( 'Selected: ' + ui.item.label + ', input was \'' + this.value + '\'');
					
					var el = jQuery(event.target);
					if (ui.item.value!='' && ui.item.value!='0') {
						addToList(ui.item.value, ui.item.label);
						el.val('');  //clear existing value
					}
					
					event.preventDefault();  // Prevent default behaviour of setting 'ui.item.value' into the input and triggering change event
				},
				//change: function( event, ui ) { window.console.log( 'autocomplete change()' ); },
				//open: function() { window.console.log( 'autocomplete open()' ); },
				//close: function() { window.console.log( 'autocomplete close()' ); },
				//search: function() { window.console.log( 'autocomplete search()' ); }
			}, tagInput.get(0) );
			
		});


		function addToList(id, name)
		{
			// Prefer quick tag selector if it exists
			var cmtag = jQuery('#quick-tag-'+id);
			if (cmtag.length)
			{
				cmtag.attr('checked', 'checked').trigger('change');
			}
			else
			{
				var obj = jQuery('#ultagbox');
				if (obj.find('input[value=\"'+id+'\"]').length > 0) return;
				obj.append('<li class=\"tagitem\"><span>'+name+'</span><input type=\"hidden\" name=\"jform[tag][]\" value=\"'+id+'\" /><a href=\"javascript:;\" class=\"deletetag\" onclick=\"javascript:deleteTag(this);\" title=\"' + Joomla.JText._('FLEXI_DELETE_TAG') + '\"></a></li>');
			}
		}


		function addtag(id, tagname)
		{
			id = id==null ? 0 : id;

			if (tagname == '')
			{
				alert(\" + Joomla.JText._('FLEXI_ENTER_TAG') + \");
				return;
			}
			
			var tag = new itemscreen();
			tag.addtag( id, tagname, 'index.php?option=com_flexicontent&".$tags_task."addtag&format=raw&".JSession::getFormToken()."=1');
		}
		
		function deleteTag(obj)
		{
			var parent = obj.parentNode;
			parent.innerHTML = '';
			parent.parentNode.removeChild(parent);
		}
		
		
		jQuery(document).ready(function(){
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

	");
}


// *****************************************
// Capture JOOMLA INTRO/FULL IMAGES and URLS
// *****************************************
$FC_jfields_html = array();
$show_jui = JComponentHelper::getParams('com_content')->get('show_urls_images_frontend', 0);
if ( $this->params->get('use_jimages_fe', $show_jui) || $this->params->get('use_jurls_fe', $show_jui) ) :

	$fields_grps_compatibility = array();
	if ( $this->params->get('use_jimages_fe', $show_jui) )  $fields_grps_compatibility[] = 'images';
	if ( $this->params->get('use_jurls_fe', $show_jui) )    $fields_grps_compatibility[] = 'urls';
	foreach ($fields_grps_compatibility as $name => $fields_grp_name) :
		
		ob_start(); ?>
		<table class="fc-form-tbl fcinner fcfullwidth">
		<?php foreach ($this->form->getGroup($fields_grp_name) as $field) : ?>
			<?php if ($field->hidden): ?>
				<tr style="display: none;"><td><?php echo $field->input; ?></td></tr>
			<?php elseif (!$field->label): ?>
			<tr>
				<td colspan="2"><?php echo $field->input;?></td>
			</tr>
			<?php else: ?>
			<tr>
				<td class="key"><?php echo $field->label; ?></td>
				<td><?php echo $field->input;?></td>
			</tr>
			<?php endif;
		endforeach; ?>
		</table>
		<?php $FC_jfields_html[$fields_grp_name] = ob_get_clean();
		
	endforeach;
endif;



$page_classes  = 'flexi_edit flexicontent';
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
		
		<?php ob_start();  ?>
		
		<div id="flexi_form_submit_msg">
			<?php echo JText::_('FLEXI_FORM_IS_BEING_SUBMITTED'); ?>
		</div>
		<div id="flexi_form_submit_btns" class="flexi_buttons">
			
			<?php if ( (in_array( 'apply', $allowbuttons_fe) && $this->perms['canedit']) || !$typeid ) : ?>
				<button class="<?php echo $btn_class;?> btn-success" type="button" onclick="return flexi_submit('<?php echo !$typeid ? 'apply_type' : 'apply'; ?>', 'flexi_form_submit_btns', 'flexi_form_submit_msg');">
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
					$params = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,left=50,width=\'+((screen.width-100) > 1360 ? 1360 : (screen.width-100))+\',top=20,height=\'+((screen.width-160) > 100 ? 1000 : (screen.width-160))+\',directories=no,location=no';
					$link   = JRoute::_(FlexicontentHelperRoute::getItemRoute($this->item->id.':'.$this->item->alias, $this->item->catid, 0, $this->item).'&amp;preview=1');
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
		<?php $form_buttons_html = ob_get_clean(); ?>
		
		<?php if ( $this->params->get('buttons_placement_fe', 0)==0 ) : ?>
			<?php /* PLACE buttons at TOP of form*/ ?>
			<?php echo $form_buttons_html; ?>
		<?php endif; ?>
		
		<?php
			$submit_msg = $approval_msg = '';
			// A message about submitting new Content via configuration parameter
			if ( $isnew && $this->params->get('submit_message') ) {
				$submit_msg = sprintf( $alert_box, 'id="fc_submit_msg"', 'note', 'fc-nobgimage', JText::_($this->params->get('submit_message')) );
			}
			
			// Autopublishing new item regardless of publish privilege, use a menu item specific
			// message if this is set, or notify user of autopublishing with a default message
			if ( $isnew && $this->params->get('autopublished', 0) ) {
				$approval_msg = $this->params->get('autopublished_message') ? $this->params->get('autopublished_message') :  JText::_( 'FLEXI_CONTENT_WILL_BE_AUTOPUBLISHED' ) ;
				$approval_msg = str_replace('_PUBLISH_UP_DAYS_INTERVAL_', $this->params->get('autopublished_up_interval') / (24*60), $approval_msg);
				$approval_msg = str_replace('_PUBLISH_DOWN_DAYS_INTERVAL_', $this->params->get('autopublished_up_interval') / (24*60), $approval_msg);
				$approval_msg = sprintf( $alert_box, 'id="fc_approval_msg"', 'info', 'fc-nobgimage', $approval_msg );
			}
			else if ( $this->params->get('approval_warning_inform_fe', 1) ) {
				// Current user does not have general publish privilege, aka new/existing items will surely go through approval/reviewal process
				if ( !$this->perms['canpublish'] ) {
					if ($isnew) {
						$approval_msg = JText::_( 'FLEXI_REQUIRES_DOCUMENT_APPROVAL' ) ;
						$approval_msg = sprintf( $alert_box, 'id="fc_approval_msg"', 'note', 'fc-nobgimage', $approval_msg );
					} else if ( $this->params->get('use_versioning', 1) ) {
						$approval_msg = JText::_( 'FLEXI_REQUIRES_VERSION_REVIEWAL' ) ;
						$approval_msg = sprintf( $alert_box, 'id="fc_approval_msg"', 'note', 'fc-nobgimage', $approval_msg );
					} else {
						$approval_msg = JText::_( 'FLEXI_CHANGES_APPLIED_IMMEDIATELY' ) ;
						$approval_msg = sprintf( $alert_box, 'id="fc_approval_msg"', 'info', 'fc-nobgimage', $approval_msg );
					}
				}
				
				// Have general publish privilege but may not have privilege if item is assigned to specific category or is of a specific type
				else {
					if ($isnew) {
						$approval_msg = JText::_( 'FLEXI_MIGHT_REQUIRE_DOCUMENT_APPROVAL' ) ;
						$approval_msg = sprintf( $alert_box, 'id="fc_approval_msg"', 'note', 'fc-nobgimage', $approval_msg );
					} else if ( $this->params->get('use_versioning', 1) ) {
						$approval_msg = JText::_( 'FLEXI_MIGHT_REQUIRE_VERSION_REVIEWAL' ) ;
						$approval_msg = sprintf( $alert_box, 'id="fc_approval_msg"', 'note', 'fc-nobgimage', $approval_msg );
					} else {
						$approval_msg = JText::_( 'FLEXI_CHANGES_APPLIED_IMMEDIATELY' ) ;
						$approval_msg = sprintf( $alert_box, 'id="fc_approval_msg"', 'info', 'fc-nobgimage', $approval_msg );
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

<?php endif;



if ( !$this->params->get('auto_title', 0) || $this->params->get('usetitle_fe', 1)  ) :  ob_start();  // title ?>
	<?php
	$field = $this->fields['title'];
	$field_description = $field->description ? $field->description :
		JText::_(FLEXI_J16GE ? $this->form->getField('title')->description : 'TIPTITLEFIELD');
	$label_tooltip = 'class="'.$tip_class.' label" title="'.flexicontent_html::getToolTip(trim($field->label, ':'), $field_description, 0, 1).'"';
	?>
	<span class="label-fcouter" id="jform_title-lbl-outer">
		<label id="jform_title-lbl" for="jform_title" data-for="jform_title" <?php echo $label_tooltip; ?> >
			<?php echo $field->label; //JText::_( 'FLEXI_TITLE' ); ?>
		</label>
	</span>
	<?php /*echo $this->form->getLabel('title');*/ ?>
	
	<div class="container_fcfield container_fcfield_id_6 container_fcfield_name_title" id="container_fcfield_6">
		
	<?php if ( $this->params->get('auto_title', 0) ): ?>
		<?php echo '<span class="badge badge-info">'.($this->item->id ? $this->item->id : JText::_('FLEXI_AUTO')).'</span>'; ?>
	<?php elseif ( isset($this->item->item_translations) ) :?>
		
		<?php
		array_push($tabSetStack, $tabSetCnt);
		$tabSetCnt = ++$tabSetMax;
		$tabCnt[$tabSetCnt] = 0;
		?>
		<!-- tabber start -->
		<div class="fctabber tabber-inline s-gray tabber-lang" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
			<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
				<h3 class="tabberheading"> <?php echo '-'.$this->itemlang->name.'-'; // $t->name; ?> </h3>
				<?php echo $this->form->getInput('title');?>
			</div>
			<?php foreach ($this->item->item_translations as $t): ?>
				<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*' && !in_array($t->code, $disable_langs)) : ?>
					<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
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
		<?php $tabSetCnt = array_pop($tabSetStack); ?>
		
	<?php else : ?>
		<?php echo $this->form->getInput('title');?>
	<?php endif; ?>

	</div>
<?php $captured['title'] = ob_get_clean(); endif;



if ($this->params->get('usealias_fe', 1)) : ob_start();  // alias ?>
	<?php
	$field_description = JText::_(FLEXI_J16GE ? $this->form->getField('alias')->description : 'ALIASTIP');
	$label_tooltip = 'class="'.$tip_class.' label" title="'.flexicontent_html::getToolTip(trim(JText::_( 'FLEXI_ALIAS' ), ':'), $field_description, 0, 1).'"';
	?>
	<span class="label-fcouter" id="jform_alias-lbl-outer">
		<label id="jform_alias-lbl" for="jform_alias" data-for="jform_alias" <?php echo $label_tooltip; ?> >
			<?php echo JText::_( 'FLEXI_ALIAS' ); ?>
		</label>
	</span>
	
	<div class="container_fcfield container_fcfield_name_alias">
	<?php	if ( isset($this->item->item_translations) ) :?>
	
		<?php
		array_push($tabSetStack, $tabSetCnt);
		$tabSetCnt = ++$tabSetMax;
		$tabCnt[$tabSetCnt] = 0;
		?>
		<!-- tabber start -->
		<div class="fctabber tabber-inline s-gray tabber-lang" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
			<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
				<h3 class="tabberheading"> <?php echo '-'.$this->itemlang->name.'-'; // $t->name; ?> </h3>
				<?php echo $this->form->getInput('alias');?>
			</div>
			<?php foreach ($this->item->item_translations as $t): ?>
				<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*' && !in_array($t->code, $disable_langs)) : ?>
					<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
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
		<?php $tabSetCnt = array_pop($tabSetStack); ?>
		
	<?php else : ?>
		<?php echo $this->form->getInput('alias');?>
	<?php endif; ?>
	
	</div>
<?php $captured['alias'] = ob_get_clean(); endif;



if ($typeid==0) : ob_start();  // type ?>
	<?php
	$field = $this->fields['document_type'];
	$field_description = $field->description ? $field->description :
		JText::_(FLEXI_J16GE ? $this->form->getField('type_id')->description : 'FLEXI_TYPE_DESC');
	$label_tooltip = 'class="'.$tip_class.' label" title="'.flexicontent_html::getToolTip(trim(@$field->label ? $field->label : JText::_( 'FLEXI_TYPE' ), ':'), $field_description, 0, 1).'"';
	?>
	<span class="label-fcouter" id="jform_type_id-lbl-outer">
		<label id="jform_type_id-lbl" for="jform_type_id" data-for="jform_type_id" <?php echo $label_tooltip; ?> >
			<?php echo @$field->label ? $field->label : JText::_( 'FLEXI_TYPE' ); ?>
		</label>
	</span>
	
	<div class="container_fcfield container_fcfield_id_8 container_fcfield_name_type" id="container_fcfield_8">
		<?php echo $this->lists['type']; ?>
		<span class="<?php echo $tip_class; ?>" style="display:inline-block;" title="<?php echo flexicontent_html::getToolTip('FLEXI_NOTES', 'FLEXI_TYPE_CHANGE_WARNING', 1, 1); ?>">
			<?php echo $infoimage; ?>
		</span>
		<?php echo sprintf( $alert_box, 'id="fc-change-warning" style="display:none;"', 'warning', '', '<h4>'.JText::_( 'FLEXI_WARNING' ).'</h4> '.JText::_( 'FLEXI_TAKE_CARE_CHANGING_FIELD_TYPE' ) ); ?>
	</div>
<?php $captured['type'] = ob_get_clean(); endif;



if ( $isnew && $this->params->get('autopublished', 0) ) :  // Auto publish new item via menu override ?>

	<input type="hidden" id="jform_state" name="jform[state]" value="1" />
	<input type="hidden" id="jform_vstate" name="jform[vstate]" value="2" />

<?php else : ob_start();  // state (and vstate) ?>

	<?php
	$field = $this->fields['state'];
	$field_description = $field->description ? $field->description :
		JText::_(FLEXI_J16GE ? $this->form->getField('state')->description : 'FLEXI_STATE_DESC');
	$label_tooltip = 'class="'.$tip_class.' label" title="'.flexicontent_html::getToolTip(trim(@$field->label ? $field->label : JText::_( 'FLEXI_STATE' ), ':'), $field_description, 0, 1).'"';
	?>
	<span class="label-fcouter" id="jform_state-lbl-outer">
		<label id="jform_state-lbl" for="jform_state" data-for="jform_state" <?php echo $label_tooltip; ?> >
			<?php echo @$field->label ? $field->label : JText::_( 'FLEXI_STATE' ); ?>
		</label>
	</span>
	
	<?php if ( $this->perms['canpublish'] ) : // Display state selection field to the user that can publish ?>

		<div class="container_fcfield container_fcfield_id_10 container_fcfield_name_state fcdualline" id="container_fcfield_10" style="margin-right:4% !important;" >
			<?php echo $this->lists['state']; ?>
			<?php //echo $this->form->getInput('state'); ?>
			<span class="<?php echo $tip_class; ?>" style="display:inline-block;" title="<?php echo flexicontent_html::getToolTip('FLEXI_NOTES', 'FLEXI_STATE_CHANGE_WARNING', 1, 1); ?>">
				<?php echo $infoimage; ?>
			</span>
		</div>
		
		<?php	if ( $this->params->get('use_versioning', 1) && $this->params->get('allow_unapproved_latest_version', 0) ) : /* PARAMETER MISSING currently disabled */ ?>
			<?php
				//echo "<br/>".$this->form->getLabel('vstate') . $this->form->getInput('vstate');
				$label_tooltip = 'class="'.$tip_class.' label fcdualline" title="'.flexicontent_html::getToolTip('FLEXI_PUBLIC_DOCUMENT_CHANGES', 'FLEXI_PUBLIC_DOCUMENT_CHANGES_DESC', 1, 1).'"';
			?>
			<span class="label-fcouter" id="jform_vstate-lbl-outer">
				<label id="jform_vstate-lbl" data-for="jform_vstate" <?php echo $label_tooltip; ?> >
					<?php echo JText::_( 'FLEXI_PUBLIC_DOCUMENT_CHANGES' ); ?>
				</label>
			</span>
			<div class="container_fcfield container_fcfield_name_vstate fcdualline">
				<?php echo $this->lists['vstate']; ?>
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
	$label_tooltip = 'class="'.$tip_class.' label label-info" title="'.flexicontent_html::getToolTip('FLEXI_ALLOW_COMMENTS', 'FLEXI_ALLOW_COMMENTS_DESC', 1, 1).'"';
	?>
	<span class="label-fcouter" id="jform_attribs_comments-title-outer">
		<label id="jform_attribs_comments-title" <?php echo $label_tooltip; ?> >
			<?php echo JText::_( 'FLEXI_ALLOW_COMMENTS' );?>
		</label>
	</span>
	
	<div class="container_fcfield container_fcfield_name_comments">
		<?php echo $this->lists['disable_comments']; ?>
	</div>
<?php $captured['disable_comments'] = ob_get_clean(); endif;



if ( $typeid && $this->params->get('allow_subscribers_notify_fe', 0) && $this->subscribers) :  ob_start();  // notify_subscribers ?>
	<?php
	$label_tooltip = 'class="'.$tip_class.' label label-info" title="'.flexicontent_html::getToolTip('FLEXI_NOTIFY_FAVOURING_USERS', 'FLEXI_NOTIFY_NOTES', 1, 1).'"';
	?>
	<span class="label-fcouter" id="jform_notify-msg-outer">
		<label id="jform_notify-msg" <?php echo $label_tooltip; ?> >
			<?php echo JText::_( 'FLEXI_NOTIFY_FAVOURING_USERS' ); ?>
		</label>
	</span>
	
	<div class="container_fcfield container_fcfield_name_notify">
		<?php echo $this->lists['notify']; ?>
	</div>
<?php $captured['notify_subscribers'] = ob_get_clean(); endif;



if ( !$this->menuCats || $this->menuCats->cancatid) : ob_start();  // category ?>
	<span class="label-fcouter" id="jform_catid-lbl-outer">
		<label id="jform_catid-lbl" for="jform_catid" data-for="jform_catid" class="label">
			<?php echo JText::_( !$secondary_displayed || isset($all_tab_fields['category']) ? 'FLEXICONTENT_CATEGORY' : 'FLEXI_MAIN_CATEGORY' ); ?>
		</label>
	</span>
	
	<div class="container_fcfield container_fcfield_name_catid">
		<?php /* MENU SPECIFIED main category (new item) or main category according to perms */ ?>
		<?php echo $this->menuCats ? $this->menuCats->catid : $this->lists['catid']; ?>
		
		<?php /* Display secondary categories if permitted */ ?>
		<?php if ($cats_canselect_sec): ?>
		<span class="<?php echo $tip_class; ?>" style="display:inline-block;" title="<?php echo flexicontent_html::getToolTip('FLEXI_NOTES', 'FLEXI_CATEGORIES_NOTES', 1, 1); ?>">
			<?php echo $infoimage; ?>
		</span>
		<?php endif; ?>
	</div>
<?php
	$captured['category'] = ob_get_clean();
else:
	$captured['category'] = $this->menuCats->catid;
endif;



if ($this->params->get('uselang_fe', 1)) : ob_start();  // lang ?>
	<span class="label-fcouter" id="jform_language-lbl-outer">
		<?php echo str_replace('class="', 'class="label ', $this->form->getLabel('language')); ?>
	</span>
	
	<div class="container_fcfield container_fcfield_name_language">
		<?php if ( (in_array( 'mod_item_lang', $allowlangmods_fe) || $isnew) && in_array($this->params->get('uselang_fe', 1), array(1,3)) ) : ?>
			<?php echo $this->lists['languages']; ?>
		<?php else: ?>
			<?php echo $this->itemlang->image.' ['.$this->itemlang->name.']'; ?>
		<?php endif; ?>
	</div>
<?php
	$captured['lang'] = ob_get_clean();
else:
	$captured['lang'] = '';
endif;


if ($secondary_displayed || !empty($this->lists['featured_cid']) || !isset($all_tab_fields['category'])) : ob_start();  // categories ?>
	<fieldset class="basicfields_set" id="fcform_categories_container">
		<legend>
			<?php echo JText::_( 'FLEXI_CATEGORIES' ); ?>
		</legend>
		
		<?php if (!isset($all_tab_fields['category'])) { echo $captured['category']; unset($captured['category']); } ?>
		
		<?php if ($secondary_displayed) : /* MENU SPECIFIED categories subset (instead of categories with CREATE perm) */ ?>
			
			<div class="fcclear"></div>
			<span class="label-fcouter" id="jform_cid-lbl-outer">
				<label id="jform_cid-lbl" for="jform_cid" data-for="jform_cid" class="label">
					<?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' );?>
				</label>
			</span>
			<div class="container_fcfield container_fcfield_name_cid">
				<?php /* MENU SPECIFIED secondary categories (new item) or categories according to perms */ ?>
				<?php echo @$this->menuCats->cid ? $this->menuCats->cid : $this->lists['cid']; ?>
			</div>
			
		<?php endif; ?>

		<?php if ( !empty($this->lists['featured_cid']) ) : ?>
			<div class="fcclear"></div>
			<span class="label-fcouter" id="jform_featured_cid-lbl-outer">
				<label id="jform_featured_cid-lbl" for="jform_featured_cid" data-for="jform_featured_cid" class="label">
					<?php echo JText::_( 'FLEXI_FEATURED_CATEGORIES' ); ?>
				</label>
			</span>
			<div class="container_fcfield container_fcfield_name_featured_cid">
				<?php echo $this->lists['featured_cid']; ?>
			</div>
		<?php endif; ?>
	</fieldset>
<?php $captured['categories'] = ob_get_clean(); endif;



if ($tags_displayed) : ob_start();  // tags ?>
	<fieldset class="basicfields_set" id="fcform_tags_container">
		<legend>
			<?php echo JText::_( 'FLEXI_TAGS' ); ?>
		</legend>
		
		<?php
		$field = $this->fields['tags'];
		$label_tooltip = $field->description ? 'class="'.$tip_class.' label" title="'.flexicontent_html::getToolTip(trim($field->label, ':'), $field->description, 0, 1).'"':'class="label"';
		?>
		<span class="label-fcouter" id="jform_tag-lbl-outer">
			<label id="jform_tag-lbl" data-for="input-tags" <?php echo $label_tooltip; ?> >
				<?php echo $field->label; ?>
				<?php /*echo JText::_( 'FLEXI_TAGS' );*/ ?>
			</label>
		</span>
		<div class="container_fcfield container_fcfield_name_tags">

			<?php if ( $this->perms['cantags'] && $this->params->get('usetags_fe', 1)==1 ) : ?>
				<div class="fcclear"></div>
				<div id="tags">
					<input type="text" id="input-tags" name="tagname" class="fcfield_textval <?php echo $tip_class; ?>"
						placeholder="<?php echo JText::_($this->perms['cancreatetags'] ? 'FLEXI_TAG_SEARCH_EXISTING_CREATE_NEW' : 'FLEXI_TAG_SEARCH_EXISTING'); ?>" 
						title="<?php echo flexicontent_html::getToolTip( 'FLEXI_NOTES', ($this->perms['cancreatetags'] ? 'FLEXI_TAG_CAN_ASSIGN_CREATE' : 'FLEXI_TAG_CAN_ASSIGN_ONLY'), 1, 1);?>"
					/>
					<span id='input_new_tag' ></span>
				</div>
				<?php endif; ?>

				<div class="fc_tagbox" id="fc_tagbox">
					<ul id="ultagbox">
					<?php
						$common_tags_selected = array();
						foreach($this->usedtagsdata as $tag)
						{
							if ( $this->perms['cantags'] && $this->params->get('usetags_fe', 1)==1 )
							{
								if ( isset($this->quicktagsdata[$tag->id]) )
								{
									$common_tags_selected[$tag->id] = 1;
									continue;
								}
								echo '
								<li class="tagitem">
									<span>'.$tag->name.'</span>
									<input type="hidden" name="jform[tag][]" value="'.$tag->id.'" />
									<a href="javascript:;" class="deletetag" onclick="javascript:deleteTag(this);" title="'.JText::_('FLEXI_DELETE_TAG').'"></a>
								</li>';
							} else {
								echo '
								<li class="tagitem plain">
									<span>'.$tag->name.'</span>
									<input type="hidden" name="jform[tag][]" value="'.$tag->id.'" />
								</li>';
							}
						}
					?>
					</ul>

					<div class="fcclear"></div>

					<?php
					if ( $this->perms['cantags'] && $this->params->get('usetags_fe', 1)==1 && count($this->quicktagsdata) )
					{
						echo '<span class="tagicon '.$tip_class.'" title="'.JText::_('FLEXI_COMMON_TAGS').'"></span>';
						foreach ($this->quicktagsdata as $tag)
						{
							$_checked = isset($common_tags_selected[$tag->id]) ? ' checked="checked" ' : '';
							echo '
							<input type="checkbox" name="jform[tag][]" value="'.$tag->id.'" data-tagname="'.$tag->name.'" id="quick-tag-'.$tag->id.'" '.$_checked.' />
							<label for="quick-tag-'.$tag->id.'" class="tagitem">'.$tag->name.'</label>
							';
						}
					}
					?>
				</div>

		</div>
	</fieldset>
<?php $captured['tags'] = ob_get_clean(); endif;



if ( ( !isset($all_tab_fields['lang']) && $captured['lang'] )  ||  ( flexicontent_db::useAssociations() && in_array( 'mod_original_content_assoc', $allowlangmods_fe) && $this->params->get('uselang_fe', 1)==1 ) ) : ob_start(); // language ?>
	<fieldset class="basicfields_set" id="fcform_language_container">
		<legend>
			<?php echo !isset($all_tab_fields['lang']) ? JText::_( 'FLEXI_LANGUAGE' ) : JText::_( 'FLEXI_LANGUAGE' ) . ' '. JText::_( 'FLEXI_ASSOCIATIONS' ) ; ?>
		</legend>
		
		<?php if (!isset($all_tab_fields['lang'])) { echo $captured['lang']; unset($captured['lang']); } ?>
		
		<div class="fcclear"></div>

		<?php if ($this->item->language!='*'): ?>
			<?php echo $this->loadTemplate('associations'); ?>
		<?php else: ?>
			<?php echo JText::_( 'FLEXI_ASSOC_NOT_POSSIBLE' ); ?>
		<?php endif; ?>

	</fieldset>
<?php $captured['language'] = ob_get_clean(); endif;



if ( $this->perms['canright'] ) : ob_start(); // perms ?>
	<?php
	$this->document->addScriptDeclaration("
		jQuery(document).ready(function(){
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
	
	<fieldset id="flexiaccess" class="flexiaccess basicfields_set">
		<legend><?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT' ); ?></legend>
		<div id="tabacces">
			<div id="accessrules"><?php echo $this->form->getInput('rules'); ?></div>
		</div>
		<div id="notabacces">
		<?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT_DESC' ); ?>
		</div>
	</fieldset>
<?php $captured['perms'] = ob_get_clean(); endif;



if ($typeid && $this->params->get('usepublicationdetails_fe', 1)) : // timezone_info, publication_details ?>

	<?php ob_start(); ?>
		<?php
		// Dates displayed in the item form, are in user timezone for J2.5, and in site's default timezone for J1.5
		$site_zone = JFactory::getApplication()->getCfg('offset');
		$user_zone = JFactory::getUser()->getParam('timezone', $site_zone);
		
		$tz = new DateTimeZone( $user_zone );
		$tz_offset = $tz->getOffset(new JDate()) / 3600;
		$tz_info =  $tz_offset > 0 ? ' UTC +' . $tz_offset : ' UTC ' . $tz_offset;
		
		$tz_info .= ' ('.$user_zone.')';
		$msg = JText::sprintf( FLEXI_J16GE ? 'FLEXI_DATES_IN_USER_TIMEZONE_NOTE' : 'FLEXI_DATES_IN_SITE_TIMEZONE_NOTE', ' ', $tz_info );
		echo sprintf( $alert_box, '', 'info', 'fc-nobgimage', $msg );
		?>
	<?php $captured['timezone_info'] = ob_get_clean(); ?>
		
	<?php if ( $this->params->get('usepublicationdetails_fe', 1) == 2 ) : ob_start(); ?>
		<fieldset class="flexi_params panelform">
			<?php echo $this->form->getLabel('created_by'); ?>
			<div class="container_fcfield"><?php echo $this->perms['isSuperAdmin'] ? $this->form->getInput('created_by') : '<span class="fc-mssg-inline fc-success fc-nobgimage" style="margin:0;">'.$this->item->author.'&nbsp;</span>'; ?></div>
		</fieldset>
	<?php $captured['created_by'] = ob_get_clean(); endif; ?>
	
	<?php if ( $this->params->get('usepublicationdetails_fe', 1) == 2 ) : ob_start(); ?>
		<fieldset class="flexi_params panelform">
			<?php echo $this->form->getLabel('created'); ?>
			<div class="container_fcfield"><?php echo $this->perms['editcreationdate'] ? $this->form->getInput('created') : '<span class="fc-mssg-inline fc-success fc-nobgimage" style="margin:0;">'.$this->item->created.'&nbsp;</span>'; ?></div>
		</fieldset>
	<?php $captured['created'] = ob_get_clean(); endif; ?>
	
	<?php ob_start(); ?>
		<fieldset class="flexi_params panelform">
			<?php echo $this->form->getLabel('created_by_alias'); ?>
			<div class="container_fcfield"><?php echo $this->perms['canpublish'] ? $this->form->getInput('created_by_alias') : '<span class="fc-mssg-inline fc-success fc-nobgimage" style="margin:0;">'.$this->item->created_by_alias.'&nbsp;</span>'; ?></div>
		</fieldset>
	<?php $captured['created_by_alias'] = ob_get_clean(); ?>
	
	<?php ob_start(); ?>
		<fieldset class="flexi_params panelform">
			<?php echo $this->form->getLabel('publish_up'); ?>
			<div class="container_fcfield"><?php echo $this->perms['canpublish'] ? $this->form->getInput('publish_up') : '<span class="fc-mssg-inline fc-success fc-nobgimage" style="margin:0;">'.$this->item->publish_up.'&nbsp;</span>'; ?></div>
		</fieldset>
	<?php $captured['publish_up'] = ob_get_clean(); ?>
	
	<?php ob_start(); ?>
		<fieldset class="flexi_params panelform">
			<?php echo $this->form->getLabel('publish_down'); ?>
			<div class="container_fcfield"><?php echo $this->perms['canpublish'] ? $this->form->getInput('publish_down') : '<span class="fc-mssg-inline fc-success fc-nobgimage" style="margin:0;">'.$this->item->publish_down.'&nbsp;</span>'; ?></div>
		</fieldset>
	<?php $captured['publish_down'] = ob_get_clean(); ?>
	
	<?php ob_start(); ?>
		<fieldset class="flexi_params panelform">
			<?php echo $this->form->getLabel('access'); ?>
			<div class="container_fcfield"><?php echo $this->perms['canacclvl'] ? $this->form->getInput('access') : '<span class="fc-mssg-inline fc-success fc-nobgimage" style="margin:0;">'.$this->item->access_level.'&nbsp;</span>'; ?></div>
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
					
					<?php
					array_push($tabSetStack, $tabSetCnt);
					$tabSetCnt = ++$tabSetMax;
					$tabCnt[$tabSetCnt] = 0;
					?>
					<!-- tabber start -->
					<div class="fctabber tabber-inline s-gray tabber-lang" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
						<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
							<h3 class="tabberheading"> <?php echo '-'.$this->itemlang->name.'-'; // $t->name; ?> </h3>
							<?php echo $this->form->getInput('metadesc'); ?>
						</div>
						<?php foreach ($this->item->item_translations as $t): ?>
							<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*' && !in_array($t->code, $disable_langs)) : ?>
								<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
									<h3 class="tabberheading"> <?php echo $t->name; // $t->shortcode; ?> </h3>
									<?php
									$ff_id = 'jfdata_'.$t->shortcode.'_metadesc';
									$ff_name = 'jfdata['.$t->shortcode.'][metadesc]';
									?>
									<textarea id="<?php echo $ff_id; ?>" class="fcfield_textareaval" rows="3" cols="46" name="<?php echo $ff_name; ?>"><?php echo @$t->fields->metadesc->value; ?></textarea>
								</div>
							<?php endif; ?>
						<?php endforeach; ?>
					</div>
					<!-- tabber end -->
					<?php $tabSetCnt = array_pop($tabSetStack); ?>
					
				<?php else : ?>
					<?php echo $this->form->getInput('metadesc'); ?>
				<?php endif; ?>
				
			</div>
				
			<div class="fcclear"></div>
			<?php echo $this->form->getLabel('metakey'); ?>
			
			<div class="container_fcfield">
				<?php	if ( isset($this->item->item_translations) ) :?>
					
					<?php
					array_push($tabSetStack, $tabSetCnt);
					$tabSetCnt = ++$tabSetMax;
					$tabCnt[$tabSetCnt] = 0;
					?>
					<!-- tabber start -->
					<div class="fctabber tabber-inline s-gray tabber-lang" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
						<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
							<h3 class="tabberheading"> <?php echo '-'.$this->itemlang->name.'-'; // $t->name; ?> </h3>
							<?php echo $this->form->getInput('metakey'); ?>
						</div>
						<?php foreach ($this->item->item_translations as $t): ?>
							<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*' && !in_array($t->code, $disable_langs)) : ?>
								<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
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
					<?php $tabSetCnt = array_pop($tabSetStack); ?>
					
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


$has_custom_params = false;
foreach ($fieldSets as $name => $fieldSet) {
	if ($name=='themes' || $name=='params-basic' || $name=='params-advanced' || $name=='params-seoconf') continue;
	$has_custom_params = true;
	break;
}

if ( $typeid && $this->params->get('usedisplaydetails_fe') || $has_custom_params ) : ob_start(); ?>
<fieldset class="panelform params_set">
	<legend>
		<?php echo JText::_( 'FLEXI_DISPLAYING' ); ?>
	</legend>
	
	<?php foreach ($fieldSets as $name => $fieldSet) : ?>
		<?php
		if ( $name=='params-basic' ) {
			if ( $this->params->get('usedisplaydetails_fe') < 1 ) continue;
		} else if ( $name=='params-advanced' ) {
			if ( $this->params->get('usedisplaydetails_fe') < 2 ) continue;
		} else {
			if ($name=='themes' || $name=='params-seoconf') continue;
		}
		$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_'.$name.'_FIELDSET_LABEL';
		?>
				
		<fieldset class="flexi_params panelform">
			<!--legend><?php echo JText::_($label); ?></legend-->
					
			<?php foreach ($this->form->getFieldset($name) as $field) : ?>
				<?php if ( $this->params->get('allowdisablingcomments_fe') && $name=='params-advanced' && $field->fieldname=='comments')  continue; ?>
				<?php echo $field->label; ?>
				<div class="container_fcfield">
					<?php echo $field->input;?>
				</div>
				<div class="fcclear"></div>
			<?php endforeach; ?>
		</fieldset>
	<?php endforeach; ?>
	
</fieldset>
<?php $captured['display_params'] = ob_get_clean(); endif;




if ( $typeid && $this->params->get('selecttheme_fe') ) : ?>

		<?php if ( $this->params->get('selecttheme_fe') >= 1 ) : ob_start(); ?>
		
			<?php
			foreach ($this->form->getFieldset('themes') as $field):
				if (!$field->label || $field->hidden)
				{
					echo $field->input;
					continue;
				}
				elseif ($field->input)
				{
					$_depends = $field->getAttribute('depend_class');
					echo '
					<fieldset class="panelform'.($_depends ? ' '.$_depends : '').'" id="'.$field->id.'-container">
						<span class="label-fcouter">
							'.str_replace('class="', 'class="label label-fcinner ', $field->label).'
						</span>
						<div class="container_fcfield">
							'.$field->input.'
						</div>
					</fieldset>
					';
				}
			endforeach; ?>
			
			<div class="fcclear"></div>
			<div class="fc-success fc-mssg" style="font-size: 12px; margin: 8px 0 !important;" id="__content_type_default_layout__">
				<?php echo JText::_( 'FLEXI_USING_LAYOUT_DEFAULTS' ); ?>
			</div>
		
		<?php $captured['layout_selection'] = ob_get_clean(); endif;
		
		
		
		if ( $this->params->get('selecttheme_fe') >= 2 ) : ob_start(); ?>
		
			<div class="fc-sliders-plain">
				<?php
				echo JHtml::_('sliders.start','theme-sliders-'.$this->form->getValue("id"), array('useCookie'=>1));
				$groupname = 'attribs';  // Field Group name this is for name of <fields name="..." >
				$item_layout = $this->item->itemparams->get('ilayout');
				
				foreach ($this->tmpls as $tmpl) :
					
					$form_layout = $tmpl->params;
					$label = '<span class="btn"><i class="icon-edit"></i>'.JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name.'</span>';
					echo JHtml::_('sliders.panel', $label, $tmpl->name.'-'.$groupname.'-options');
					
					if ($tmpl->name != $item_layout) continue;
					
					$fieldSets = $form_layout->getFieldsets($groupname);
					foreach ($fieldSets as $fsname => $fieldSet) : ?>
						<fieldset class="panelform params_set">
						
						<?php
						if (isset($fieldSet->label) && trim($fieldSet->label)) :
							echo '<div style="margin:0 0 12px 0; font-size: 16px; background-color: #333; float:none;" class="fcsep_level0">'.JText::_($fieldSet->label).'</div>';
						endif;
						if (isset($fieldSet->description) && trim($fieldSet->description)) :
							echo '<div class="fc-mssg fc-info">'.JText::_($fieldSet->description).'</div>';
						endif;
						
						foreach ($form_layout->getFieldset($fsname) as $field) :
							
							if ($field->getAttribute('not_inherited')) continue;
							if ($field->getAttribute('cssprep')) continue;
							
							$fieldname = $field->fieldname;
							//$value = $form_layout->getValue($fieldname, $groupname, $this->item->itemparams->get($fieldname));
							
							$input_only = !$field->label || $field->hidden;
							echo
								($input_only ? '' :
								str_replace(' for="', ' data-for="',
									str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_',
										$form_layout->getLabel($fieldname, $groupname))).'
								<div class="container_fcfield">
								').
								
								str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_', 
									str_replace('[attribs]', '[layouts]['.$tmpl->name.']',
										$form_layout->getInput($fieldname, $groupname/*, $value*/)   // Value already set, no need to pass it
									)
								).
								
								($input_only ? '' : '
								</div>
								');
						endforeach; ?>
						
						</fieldset>
						
					<?php endforeach; //fieldSets ?>
				<?php endforeach; //tmpls ?>
				
				<?php echo JHtml::_('sliders.end'); ?>
			</div>
		<?php
		$captured['layout_params'] = ob_get_clean(); endif; ?>
	
<?php endif; // end of template: layout_selection, layout_params




ob_start();
if ($this->fields && $typeid) :
	
	$this->document->addScriptDeclaration("
		jQuery(document).ready(function(){
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
		$hide_ifempty_fields = array('fcloadmodule', 'fcpagenav', 'toolbar');
		$noplugin = '<div class="fc-mssg-inline fc-warning" style="margin:0 4px 6px 4px; max-width: unset;">'.JText::_( 'FLEXI_PLEASE_PUBLISH_THIS_PLUGIN' ).'</div>';
		$row_k = 0;
		foreach ($this->fields as $field_name => $field)
		{
			if ( $field->iscore &&  isset($tab_fields['fman'][ $field->field_type ]) )
			{
				// Print any CORE fields that are placed by field manager
				if ( isset($captured[ $field->field_type ]) ) {
					echo $captured[ $field->field_type ]; unset($captured[ $field->field_type ]);
					echo "\n".'<div class="fcclear"></div>'."\n";
				}
				continue;
			}
			
			else if (
				// SKIP frontend hidden fields 
				($field->iscore && $field->field_type!='maintext')   ||   $field->parameters->get('frontend_hidden')   ||   in_array($field->formhidden, array(1,3))   ||
				
				// Skip hide-if-empty fields from this listing
				( empty($field->html) && ($field->formhidden==4 || in_array($field->field_type, $hide_ifempty_fields)) )
			) continue;
			
			// check to SKIP (hide) field e.g. description field ('maintext' field type), alias field etc
			if ( $this->tparams->get('hide_'.$field->field_type) ) continue;
			
			$not_in_tabs = "";
			if ($field->field_type=='groupmarker') {
				echo $field->html;
				continue;
			} else if ($field->field_type=='coreprops') {
				$props_type = $field->parameters->get('props_type');
				if ( isset($tab_fields['fman'][$props_type]) ) {
					if ( !isset($captured[ $props_type ]) ) continue;
					echo $captured[ $props_type ]; unset($captured[ $props_type ]);
					echo "\n".'<div class="fcclear"></div>'."\n";
				}
				continue;
			}
			
			if ($field->field_type=='image' && $field->parameters->get('image_source')==-1)
			{
				$replace_txt = !empty($FC_jfields_html['images']) ? $FC_jfields_html['images'] : '<span class="alert alert-warning">'.JText::_('FLEXI_ENABLE_INTRO_FULL_IMAGES_IN_TYPE_CONFIGURATION').'</span>';
				unset($FC_jfields_html['images']);
				$field->html = str_replace('_INTRO_FULL_IMAGES_HTML_', $replace_txt, $field->html);
			}
			
			// Check if 'Description' field will NOT be placed via fields manager placement/ordering,
			// but instead it will be inside a custom TAB or inside the 'Description' TAB (default)
			if ( $field->field_type=='maintext' && isset($all_tab_fields['text']) )
			{
				ob_start();
			}
			
			// Decide label classes, tooltip, etc
			$lbl_class = 'label';
			$lbl_title = '';
			// field has tooltip
			$edithelp = $field->edithelp ? $field->edithelp : 1;
			if ( $field->description && ($edithelp==1 || $edithelp==2) ) {
				 $lbl_class .= ($edithelp==2 ? ' fc_tooltip_icon ' : ' ') .$tip_class;
				 $lbl_title = flexicontent_html::getToolTip(trim($field->label, ':'), $field->description, 0, 1);
			}
			// field is required
			$required = $field->parameters->get('required', 0 );
			if ($required)  $lbl_class .= ' required';
			
			// Some fields may force a container width ?
			$display_label_form = $field->parameters->get('display_label_form', 1);
			$row_k = 1 - $row_k;
			$full_width = $display_label_form==0 || $display_label_form==2 || $display_label_form==-1;
			$width = $field->parameters->get('container_width', ($full_width ? '100%!important;' : false) );
			$container_width = empty($width) ? '' : 'width:' .$width. ($width != (int)$width ? 'px!important;' : '');
			$container_class = "fcfield_row".$row_k." container_fcfield container_fcfield_id_".$field->id." container_fcfield_name_".$field->name;
			?>
			
			<div class="fcclear"></div>
			<span class="label-fcouter" id="label_outer_fcfield_<?php echo $field->id; ?>">
				<label id="label_fcfield_<?php echo $field->id; ?>" style="<?php echo $display_label_form < 1 ? 'display:none;' : '' ?>" data-for="<?php echo 'custom_'.$field->name;?>" class="<?php echo $lbl_class;?>" title="<?php echo $lbl_title;?>" >
					<?php echo $field->label; ?>
				</label>
			</span>
			<?php if($display_label_form==2):  ?>
				<div class="fcclear"></div>
			<?php endif; ?>
			
			<div style="<?php echo $container_width; ?>" class="<?php echo $container_class; ?>" id="container_fcfield_<?php echo $field->id; ?>">
				<?php echo ($field->description && $edithelp==3)  ?  sprintf( $alert_box, '', 'info', 'fc-nobgimage', $field->description )  :  ''; ?>
				
			<?php // CASE 1: CORE 'description' FIELD with multi-tabbed editing of joomfish (J1.5) or falang (J2.5+)
			if ($field->field_type=='maintext' && isset($this->item->item_translations) ) : ?>
				
				<?php
				array_push($tabSetStack, $tabSetCnt);
				$tabSetCnt = ++$tabSetMax;
				$tabCnt[$tabSetCnt] = 0;
				?>
				<!-- tabber start -->
				<div class="fctabber tabber-inline s-gray tabber-lang" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
					<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
						<h3 class="tabberheading"> <?php echo '- '.$this->itemlang->name.' -'; // $t->name; ?> </h3>
						<?php
							$field_tab_labels = & $field->tab_labels;
							$field_html       = & $field->html;
							echo !is_array($field_html) ? $field_html : flexicontent_html::createFieldTabber( $field_html, $field_tab_labels, "");
						?>
					</div>
					<?php foreach ($this->item->item_translations as $t): ?>
						<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*' && !in_array($t->code, $disable_langs)) : ?>
							<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
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
				<?php $tabSetCnt = array_pop($tabSetStack); ?>
				
			<?php elseif ( !isset($field->html) || !is_array($field->html) ) : /* CASE 2: NORMAL FIELD non-tabbed */ ?>
				
				<?php echo isset($field->html) ? $field->html : $noplugin; ?>
				
			<?php else : /* MULTI-TABBED FIELD e.g textarea, description */ ?>
				
				<?php
				array_push($tabSetStack, $tabSetCnt);
				$tabSetCnt = ++$tabSetMax;
				$tabCnt[$tabSetCnt] = 0;
				?>
				<!-- tabber start -->
				<div class="fctabber" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
				<?php foreach ($field->html as $i => $fldhtml): ?>
					<?php
						// Hide field when it has no label, and skip creating tab
						$not_in_tabs .= !isset($field->tab_labels[$i]) ? "<div style='display:none!important'>".$field->html[$i]."</div>" : "";
						if (!isset($field->tab_labels[$i]))	continue;
					?>
					
					<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
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
				<?php $tabSetCnt = array_pop($tabSetStack); ?>
				<?php echo $not_in_tabs;      // Output ENDING hidden fields, by placing them outside the tabbing area ?>
						
			<?php endif; /* END MULTI-TABBED FIELD */ ?>
			
			</div>
			
		<?php
			if ( $field->field_type=='maintext' && isset($all_tab_fields['text']) )
			{
				$captured['text'] = ob_get_clean();
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




// ***********************************************
// ANY field not found inside the 'captured' ARRAY,
// must be a field not configured to be displayed
// ***********************************************
$displayed_at_tab = array();
$_tmp = $tab_fields;
foreach($_tmp as $tabname => $fieldnames) {
	//echo "$tabname <br/>  %% ";
	//print_r($fieldnames); echo "<br/>";
	foreach($fieldnames as $fn => $i) {
		//echo " -- $fn <br/>";
		if (isset($captured[$fn])) {
			$displayed_at_tab[$fn][] = $tabname;
		}
		if ( isset($shown[$fn]) ) {
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
$msg = '';
foreach($displayed_at_tab as $fieldname => $_places) {
	if ( count($_places) > 1 ) $msg .= "<br/><b>".$fieldname."</b>" . " at [".implode(', ', $_places)."]";
}
if ($msg) {
	$msg = JText::sprintf( 'FLEXI_FORM_FIELDS_DISPLAYED_TWICE', $msg."<br/>");
	echo sprintf( $alert_box, '', 'error', '', $msg );
}

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
array_push($tabSetStack, $tabSetCnt);
$tabSetCnt = ++$tabSetMax;
$tabCnt[$tabSetCnt] = 0;
?>

<!-- tabber start -->
<div class="fctabber fields_tabset" id="fcform_tabset_<?php echo $tabSetCnt; ?>">


<?php
// ***************
// DESCRIPTION TAB
// ***************
if ( count($tab_fields['tab01']) ) :
	$tab_lbl = isset($tab_titles['tab01']) ? $tab_titles['tab01'] : JText::_( 'FLEXI_DESCRIPTION' );
	$tab_ico = isset($tab_icocss['tab01']) ? $tab_icocss['tab01'] : 'icon-file-2';
	?>
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $tab_ico; ?>">
		<h3 class="tabberheading"> <?php echo $tab_lbl; ?> </h3>
		
		<?php foreach($tab_fields['tab01'] as $fn => $i) : ?>
			<div class="fcclear"></div>
			<?php echo $captured[$fn]; unset($captured[$fn]); ?>
		<?php endforeach; ?>
		
	</div>
<?php endif;



// *********
// BASIC TAB
// *********
if ( count($tab_fields['tab02']) ) :
	$tab_lbl = isset($tab_titles['tab02']) ? $tab_titles['tab02'] : JText::_( 'FLEXI_BASIC' );
	$tab_ico = isset($tab_icocss['tab02']) ? $tab_icocss['tab02'] : 'icon-tree-2';
	?>
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $tab_ico; ?>">
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
	$tab_ico = isset($tab_icocss['tab03']) ? $tab_icocss['tab03'] : 'icon-signup';
	?>
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $tab_ico; ?>">
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
	if ( count($tab_fields['tab04']) ) : ?>
		<?php
		$tab_lbl = isset($tab_titles['tab04']) ? $tab_titles['tab04'] : JText::_( 'FLEXI_PUBLISHING' );
		$tab_ico = isset($tab_icocss['tab04']) ? $tab_icocss['tab04'] : 'icon-calendar';
		?>
		<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $tab_ico; ?>">
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
		<?php
		$tab_lbl = isset($tab_titles['tab05']) ? $tab_titles['tab05'] : JText::_( 'FLEXI_META_SEO' );
		$tab_ico = isset($tab_icocss['tab05']) ? $tab_icocss['tab05'] : 'icon-bookmark';
		?>
		<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $tab_ico; ?>" >
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
		<?php
		$tab_lbl = isset($tab_titles['tab06']) ? $tab_titles['tab06'] : JText::_( 'FLEXI_DISPLAYING' );
		$tab_ico = isset($tab_icocss['tab06']) ? $tab_icocss['tab06'] : 'icon-eye-open';
		?>
		<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $tab_ico; ?>">
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
	if ( count($FC_jfields_html) ) : ?>

		<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-joomla">
			<h3 class="tabberheading"> <?php echo JText::_('FLEXI_COMPATIBILITY'); ?> </h3>
			
			<?php foreach ($FC_jfields_html as $fields_grp_name => $_html) : ?>
			<fieldset class="flexi_params fc_tabset_inner">
				<div class="alert alert-info" style="width: 50%;"><?php echo JText::_('FLEXI_'.strtoupper($fields_grp_name).'_COMP'); ?></div>
				<?php echo $_html; ?>
			</fieldset>
			<?php endforeach; ?>
			
		</div>
	<?php endif;



	// ************
	// TEMPLATE TAB
	// ************
	if ( count($tab_fields['tab07']) ) : ?>
		<?php
		$tab_lbl = isset($tab_titles['tab07']) ? $tab_titles['tab07'] : JText::_( 'FLEXI_TEMPLATE' );
		$tab_ico = isset($tab_icocss['tab07']) ? $tab_icocss['tab07'] : 'icon-palette';
		?>
		<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="<?php echo $tab_ico; ?>">
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
<?php $tabSetCnt = array_pop($tabSetStack); ?>

	
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
	
	<?php /* ALSO print any fields that were not placed above, this list may contain fields zero-length HTML which is OK */ ?>
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
		<?php if ( $this->params->get('buttons_placement_fe', 0)==1 ) : ?>
			<?php /* PLACE buttons at BOTTOM of form*/ ?>
			<br class="clear" />
			<?php echo $form_buttons_html; ?>
		<?php endif; ?>
		
		<br class="clear" />
		<?php echo JHTML::_( 'form.token' ); ?>
		<input type="hidden" name="task" id="task" value="" />
		<input type="hidden" name="option" value="com_flexicontent" />
		<input type="hidden" name="referer" value="<?php echo $referer_encoded; ?>" />
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
	<div class="fcclear"></div>
</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
