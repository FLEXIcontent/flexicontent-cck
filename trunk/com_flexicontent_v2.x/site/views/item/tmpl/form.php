<?php
/**
 * @version 1.5 stable $Id: form.php 1229 2012-04-02 17:34:51Z ggppdk $
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

// Added to allow the user to choose some of the pre-selected categories
$cid = $this->params->get("cid");
$isNew = ! JRequest::getInt('id', 0);
$maincatid = $this->params->get("maincatid");
$postcats = $this->params->get("postcats", 0);
$overridecatperms = $this->params->get("overridecatperms", 1);
$typeid = getValueFCitem($this->item, 'id') ? getValueFCitem($this->item, 'type_id')	 :  JRequest::getInt('typeid') ;
$return = JRequest::getString('return', '', 'get');
if ($return) {
	$referer = base64_decode( $return );
} else {
	$referer = str_replace(array('"', '<', '>', "'"), '', @$_SERVER['HTTP_REFERER']);
}

// DO NOT override user's  permission for submitting to multiple categories
if (!$this->perms['multicat']) {
	if ($postcats==2) $postcats = 1;
}

// OVERRIDE item categories, using the ones specified specified by the MENU item, instead of categories that user has CREATE (=add) Permission
if ($cid && $overridecatperms && $isNew) :
	global $globalcats;
	$cids = !is_array($cid) ? explode(",", $cid) : $cid;
	if (!$maincatid) $maincatid=$cids[0];  // If main category not specified then use the first in list
	if (!in_array($maincatid, $cids)) $cids[] = $maincatid;
	$cids_kv 	= array();
	$options 	= array();
	foreach ($cids as $cat) {
		$cids_kv[$cat] = $globalcats[$cat]->title;
	}
	
	switch($postcats) {
		case 0:  // no categories selection, submit to a MENU SPECIFIED categories list
		default:
			$in_single_cat = ( count($cids)==1 );
			$fixedcats = implode(', ', $cids_kv);
			foreach ($cids_kv as $k => $v) {
				$fixedcats .= '<input type="hidden" name="jform[cid][]" value="'.$k.'" />';
			}
			$fixedmaincat = $globalcats[$maincatid]->title;
			$fixedmaincat .= '<input type="hidden" name="jform[catid]" value="'.$maincatid.'" />';
			break;
		case 1:  // submit to a single category, selecting from a MENU SPECIFIED categories subset
			$in_single_cat = true;
			$options[] = JHTML::_( 'select.option', '', '-- '.JText::_( 'FLEXI_SELECT_CAT' ).' --' );
			foreach ($cids_kv as $k => $v) {
				$options[] = JHTML::_('select.option', $k, $v );
			}
			$fixedcats = '';
			$fixedmaincat = JHTML::_('select.genericlist', $options, 'jform[catid]', ' class="required" ', 'value', 'text', $maincatid, 'jform_catid' );
			break;
		case 2:  // submit to multiple categories, selecting from a MENU SPECIFIED categories subset
			$in_single_cat = false;
			foreach ($cids_kv as $k => $v) {
				$options[] = JHTML::_('select.option', $k, $v );
			}
			$fixedcats = JHTML::_('select.genericlist', $options, 'jform[cid][]', 'multiple="multiple" size="6"', 'value', 'text', '', 'jform_cid' );
			array_unshift($options, JHTML::_( 'select.option', '', '-- '.JText::_( 'FLEXI_SELECT_CAT' ).' --' ) );
			$fixedmaincat = JHTML::_('select.genericlist', $options, 'jform[catid]', ' class="required" ', 'value', 'text', $maincatid, 'jform_catid' );
			break;
	}
endif;

if(!JPluginHelper::isEnabled('system', 'jquerysupport')) {
	$this->document->addScript('administrator/components/com_flexicontent/assets/js/jquery-1.7.1.min.js');
	$this->document->addCustomTag('<script>jQuery.noConflict();</script>');    // ALREADY include in above file, but done again
}
JHTML::_('behavior.mootools');

// add extra css for the edit form
if ($this->params->get('form_extra_css')) {
	$this->document->addStyleDeclaration($this->params->get('form_extra_css'));
}
$this->document->addStyleSheet('administrator/components/com_flexicontent/assets/css/flexicontentbackend.css');
$this->document->addScript( JURI::base().'administrator/components/com_flexicontent/assets/js/itemscreen.js' );
$this->document->addScript( JURI::base().'administrator/components/com_flexicontent/assets/js/admin.js' );
$this->document->addScript( JURI::base().'administrator/components/com_flexicontent/assets/js/validate.js' );
$this->document->addScript( JURI::base().'administrator/components/com_flexicontent/assets/js/tabber-minimized.js');
$this->document->addStyleSheet('administrator/components/com_flexicontent/assets/css/tabber.css');

if ( $this->perms['cantags'] && $this->params->get('usetags_fe', 1)==1 ) {
	$this->document->addScript('administrator/components/com_flexicontent/assets/jquery-autocomplete/jquery.bgiframe.min.js');
	$this->document->addScript('administrator/components/com_flexicontent/assets/jquery-autocomplete/jquery.ajaxQueue.js');
	$this->document->addScript('administrator/components/com_flexicontent/assets/jquery-autocomplete/jquery.autocomplete.min.js');
	$this->document->addScript('administrator/components/com_flexicontent/assets/js/jquery.pager.js');
	
	$this->document->addStyleSheet('administrator/components/com_flexicontent/assets/jquery-autocomplete/jquery.autocomplete.css');
	$this->document->addStyleSheet('administrator/components/com_flexicontent/assets/css/Pager.css');
	$this->document->addScriptDeclaration("
		jQuery(document).ready(function () {
			jQuery(\"#input-tags\").autocomplete(\"".JURI::base()."index.php?option=com_flexicontent&view=item&task=viewtags&tmpl=component&".JUtility::getToken()."=1\", {
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
window.addEvent( "domready", function() {
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
	obj = $('ultagbox');
	obj.innerHTML+="<li class=\"tagitem\"><span>"+name+"</span><input type='hidden' name='jform[tag][]' value='"+id+"' /><a href=\"javascript:;\"  class=\"deletetag\" onclick=\"javascript:deleteTag(this);\" title=\"<?php echo JText::_( 'FLEXI_DELETE_TAG' ); ?>\"></a></li>";
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
	tag.addtag( id, tagname, 'index.php?option=com_flexicontent&task=addtag&format=raw&<?php echo JUtility::getToken();?>=1');
}

function deleteTag(obj) {
	parent = jQuery(jQuery(obj).getParent());
	jQuery(parent).remove();
}

</script>

<div id="flexicontent" class="adminForm flexi_edit" style="font-size:90%;">

    <?php if ($this->params->def( 'show_page_title', 1 )) : ?>
    <h1 class="componentheading">
        <?php echo $this->params->get('page_title'); ?>
    </h1>
    <?php endif; ?>

	<?php
	$autopublished = $this->params->get('autopublished', 0);  // Menu Item Parameter
	$canpublish = $this->perms['canpublish'];
	$autoapprove = 1; //$this->params->get('auto_approve', 0);  // THIS SHOULD ONLY BE USED in BACKEND ?? It may be confusing to the frontend user
	//echo "Item Permissions:<br>\n<pre>"; print_r($this->perms); echo "</pre>";
	//echo "Auto-Publish Parameter: $autopublished<br />";
	//echo "Auto-Approve Parameter: $autoapprove<br />";
	?>

	<form action="<?php echo $this->action ?>" method="post" name="adminForm" id="adminForm" class="form-validate" enctype="multipart/form-data">
		<div class="flexi_buttons" style="font-size:90%;">
			
			<button class="button" type="button" onclick="return Joomla.submitbutton('apply');">
				<span class="fcbutton_apply"><?php echo JText::_( getValueFCitem($this->item, 'id') ? 'FLEXI_SAVE' : ($typeid ? 'FLEXI_ADD' : 'FLEXI_APPLY_TYPE' ) ) ?></span>
			</button>
			
		<?php if ( $typeid ) : ?>
		
			<button class="button" type="button" onclick="return Joomla.submitbutton('save');">
				<span class="fcbutton_save"><?php echo JText::_( getValueFCitem($this->item, 'id') ? 'FLEXI_SAVE_A_RETURN' : 'FLEXI_ADD_A_RETURN' ) ?></span>
			</button>
			<button class="button" type="button" onclick="return Joomla.submitbutton('save_a_preview');">
				<span class="fcbutton_preview_save"><?php echo JText::_( getValueFCitem($this->item, 'id') ? 'FLEXI_SAVE_A_PREVIEW' : 'FLEXI_ADD_A_PREVIEW' ) ?></span>
			</button>
			<?php
				$params = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=100%,height=100%,directories=no,location=no';
				$link   = JRoute::_(FlexicontentHelperRoute::getItemRoute(getValueFCitem($this->item, 'id').':'.getValueFCitem($this->item, 'alias'), getValueFCitem($this->item, 'catid')).'&preview=1');
			?>
			
			<?php if ( getValueFCitem($this->item, 'id') ) : ?>
			<button class="button" type="button" onclick="window.open('<?php echo $link; ?>','preview2','<?php echo $params; ?>'); return false;">
				<span class="fcbutton_preview"><?php echo JText::_( 'FLEXI_PREVIEW_LATEST' ) ?></span>
			</button>
			<?php endif; ?>
			
		<?php endif; ?>
			
			<button class="button" type="button" onclick="return Joomla.submitbutton('cancel')">
				<span class="fcbutton_cancel"><?php echo JText::_( 'FLEXI_CANCEL' ) ?></span>
			</button>
			
		</div>
         
		<br class="clear" />
		<?php
			$approval_msg = JText::_( getValueFCitem($this->item, 'id')==0 ? 'FLEXI_REQUIRES_DOCUMENT_APPROVAL' : 'FLEXI_REQUIRES_VERSION_REVIEWAL') ;
			if (!$canpublish)  echo '<div style="text-align:right; width:100%; padding:0px; clear:both;">(*) '.$approval_msg.'</div>';
		?>
		
	<table class="admintable" cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td class="key">
				<?php
					$field = @$this->fields['title'];
					$field_tooltip = @$field->description ? 'class="hasTip" title="'.$field->label.'::'.$field->description.'"' : 'class=""';
				?>
				<label id="jform_title-lbl" for="jform_title" <?php echo $field_tooltip; ?> >
					<?php echo @$field->label ? $field->label : $this->item->getLabel('title'); ?>
				</label>
			</td>
			<td>
				<?php echo $this->item->getInput('title');?>
			</td>
		</tr>
		
	<?php if ($this->params->get('usealias_fe', 1)) : ?>
		<tr>
			<td class="key">
				<?php echo $this->item->getLabel('alias');?>
			</td>
			<td>
				<?php echo $this->item->getInput('alias');?>
			</td>
		</tr>
	<?php endif; ?>
	
	<?php if ($typeid==0) : ?>
		<tr>
			<td class="key">
				<label id="jform_type_id-lbl" for="jform_type_id" >
					<?php echo JText::_( 'FLEXI_TYPE' ); ?>
				</label>
			</td>
			<td>
				<?php echo $this->lists['type']; ?>
			</td>
		</tr>
	<?php endif; ?>

	
	<?php if ($cid && $overridecatperms && $isNew) : /* MENU SPECIFIED categories subset (instead of categories with CREATE perm) */ ?>
		<tr>
			<td class="key">
				<label id="jform_catid-lbl" for="jform_catid">
					<?php echo JText::_( $in_single_cat ? 'FLEXICONTENT_CATEGORY' : 'FLEXI_PRIMARY_CATEGORY' );  /* when submitting to single category, call this field just 'CATEGORY' instead of 'PRIMARY CATEGORY' */ ?>
				</label>
			</td>
			<td>
				<?php echo $fixedmaincat; ?>
			</td>
		</tr>
		<?php if ($postcats!=1 && !$in_single_cat) : /* hide when submiting to single category, since we will only show primary category field */ ?>
		<tr>
			<td class="key">
				<label id="jform_cid-lbl" for="jform_cid">
					<?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' );?>
					<?php if ($postcats==2) : /* add "ctrl-click" tip when selecting multiple categories */ ?>
						<span class="editlinktip hasTip" title="<?php echo JText::_ ( 'FLEXI_NOTES' ); ?>::<?php echo JText::_ ( 'FLEXI_CATEGORIES_NOTES' );?>">
							<?php echo JHTML::image ( 'components/com_flexicontent/assets/images/icon-16-hint.png', JText::_ ( 'FLEXI_NOTES' ) ); ?>
						</span>
					<?php endif; ?>
				</label>
			</td>
			<td>
				<?php echo $fixedcats; ?>
			</td>
		</tr>
		<?php endif; ?>
	<?php else : ?>
		<tr>
			<td class="key">
				<label id="jform_catid-lbl" for="jform_catid">
					<?php echo JText::_( (!$this->perms['multicat']) ? 'FLEXICONTENT_CATEGORY' : 'FLEXI_PRIMARY_CATEGORY' );  /* if no multi category allowed for user, then call it just 'CATEGORY' instead of 'PRIMARY CATEGORY' */ ?>
				</label>
			</td>
			<td>
				<?php echo $this->lists['catid']; ?>
			</td>
		</tr>
		<?php if ($this->perms['multicat']) : ?>
		<tr>
			<td class="key">
				<label id="jform_cid-lbl" for="jform_cid">
					<?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' );?>
					<span class="editlinktip hasTip" title="<?php echo JText::_ ( 'FLEXI_NOTES' ); ?>::<?php echo JText::_ ( 'FLEXI_CATEGORIES_NOTES' );?>">
						<?php echo JHTML::image ( 'components/com_flexicontent/assets/images/icon-16-hint.png', JText::_ ( 'FLEXI_NOTES' ) ); ?>
					</span>
				</label>
			</td>
			<td>
				<?php echo $this->lists['cid']; ?>
			</td>
		</tr>
		<?php endif; ?>
	<?php endif; ?>


	<?php if (!$autopublished && $canpublish) : // autopublished disabled, display state selection field to the user that can publish ?>
	
		<tr>
			<td class="key">
				<?php
					$field = @$this->fields['state'];
					$field_tooltip = @$field->description ? 'class="hasTip" title="'.$field->label.'::'.$field->description.'"' : 'class=""';
				?>
				<label id="jform_state-lbl" for="jform_state" <?php echo $field_tooltip; ?> >
					<?php echo @$field->label ? $field->label : $this->item->getLabel('state'); ?>
				</label>
			</td>
			<td>
	  		<?php echo $this->item->getInput('state'); ?>
	  		<?php	if ($autoapprove) : ?>
	  			<input type="hidden" id="vstate" name="jform[vstate]" value="2" />
	  		<?php	endif;?>
			</td>
		</tr>
		
		<?php	if (!$autoapprove) :	?>
		<tr>
			<td class="key">
				<?php echo JText::_( 'FLEXI_APPROVE_VERSION' );?>
			</td>
			<td>
				<?php echo $this->lists['vstate']; ?>
			</td>
		</tr>
		<?php	endif; ?>
		
	<?php elseif (!$autopublished && !$canpublish) : ?>
	
		<tr>
			<td class="key">
				<?php
					$field = @$this->fields['state'];
					$field_tooltip = @$field->description ? 'class="hasTip" title="'.$field->label.'::'.$field->description.'"' : 'class=""';
				?>
				<label id="jform_state-lbl" for="jform_state" <?php echo $field_tooltip; ?> >
					<?php echo @$field->label ? $field->label : $this->item->getLabel('state'); ?>
				</label>
			</td>
			<td>
	  		<?php //echo JText::_( 'FLEXI_NEEDS_APPROVAL' );?>
	  		<?php echo 'You cannot set state of this item, it will be reviewed by administrator'; ?>
				<input type="hidden" id="state" name="jform[state]" value="<?php echo getValueFCitem($this->item, 'state', -4);?>" />
				<input type="hidden" id="vstate" name="jform[vstate]" value="1" />
			</td>
		</tr>
	
	<?php endif; ?>
	
		<?php if (FLEXI_FISH || FLEXI_J16GE) : ?>
		<tr>
			<td class="key">
				<?php echo $this->item->getLabel('language'); ?>
			</td>
			<td>
				<?php echo $this->lists['languages']; ?>
				<?php echo $this->item->getInput('lang_parent_id'); ?>
			</td>
		</tr>
		<?php endif; ?>
		<?php
		if ($this->perms['canright']) :
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
		<tr>
			<td colspan="2">
				<fieldset class="flexiaccess">
					<legend><?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT' ); ?></legend>
					<table id="tabacces" class="admintable" width="100%">
						<tr>
							<td>
								<div id="accessrules"><?php echo $this->item->getInput('rules'); ?></div>
							</td>
						</tr>
					</table>
					<div id="notabacces">
					<?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT_DESC' ); ?>
					</div>
				</fieldset>
			</td>
		</tr>
		<?php endif; ?>

	<?php if ($typeid && ($this->perms['cantags'] || count(@$this->usedtags)) ) : ?>
		<?php $display_tags = $this->params->get('usetags_fe', 1)==0 ? 'style="display:none;"' : ''; ?>
		
		<tr <?php echo $display_tags; ?> >
			<td class="key">
				<?php
					$field = @$this->fields['tags'];
					$field_tooltip = @$field->description ? 'class="hasTip" title="'.$field->label.'::'.$field->description.'"' : 'class=""';
				?>
				<label id="jform_tags-lbl" for="jform_tags" <?php echo $field_tooltip; ?> >
					<?php echo @$field->label ? $field->label : JText::_( 'FLEXI_TAGS' ); ?>
				</label>
			</td>
			<td>
				<div class="qf_tagbox" id="qf_tagbox">
					<ul id="ultagbox">
					<?php
						foreach($this->usedtags as $tag) {
								if ( $this->perms['cantags'] && $this->params->get('usetags_fe', 1)==1 ) {
									echo '<li class="tagitem"><span>'.$tag->name.'</span>';
									echo '<input type="hidden" name="jform[tag][]" value="'.$tag->id.'" /><a href="javascript:;" onclick="javascript:deleteTag(this);" class="deletetag" align="right" title="'.JText::_('FLEXI_DELETE_TAG').'"></a></li>';
								} else {
									echo '<li class="tagitem" style="background-image:url()!important;"><span>'.$tag->name.'</span>';
									echo '<input type="hidden" name="jform[tag][]" value="'.$tag->id.'" />&nbsp;</li>';
							}
						}
					?>
					</ul>
					<br class="clear" />
				</div>
			</td>
		</tr>
		<?php if ( $this->perms['cantags'] && $this->params->get('usetags_fe', 1)==1 ) : ?>
		<tr>
			<td class="key">
				<label for="input-tags"><?php echo JText::_( 'FLEXI_ADD_TAG' ); ?></label>
			</td>
			<td>
				<div id="tags">
					<input type="text" id="input-tags" name="tagname" tagid='0' tagname='' />
				</div>
			</td>
		</tr>
		<?php endif; ?>
	<?php endif; ?>
	
	</table>

<?php if ($this->fields) : ?>

	<?php
		$this->document->addScriptDeclaration("
		window.addEvent('domready', function() {
			$$('#type_id').addEvent('change', function(ev) {
				$('fc-change-error').setStyle('display', 'block');
				});
			});
		");
	?>

	<div id="fc-change-error" class="fc-error" style="display:none;"><?php echo JText::_( 'FLEXI_TAKE_CARE_CHANGING_FIELD_TYPE' ); ?></div>
	
	<fieldset>
		<legend>
			<?php
			$types = flexicontent_html::getTypesList();
			$typename = $types[getValueFCitem($this->item, 'type_id')]['name'];
			echo $typename ? JText::_( 'FLEXI_ITEM_TYPE' ) . ' : ' . $typename : JText::_( 'FLEXI_TYPE_NOT_DEFINED' ); ?>
		</legend>
		
		<table class="admintable" width="100%">
			<?php
			$hidden = array(
				'fcloadmodule',
				'fcpagenav',
				'toolbar'
			);
			
			foreach ($this->fields as $field) {
				
				// SKIP frontend hidden fields from this listing
				if ( ($field->iscore && $field->field_type!='maintext')  ||  $field->parameters->get('frontend_hidden')  ||  in_array($field->field_type, $hidden) ) continue;
				
				// check to SKIP (hide) field e.g. description field ('maintext'), alias field etc
				if ( $this->tparams->get('hide_'.$field->field_type) ) continue;
				
				// Create main text field, via calling the display function of the textarea field (will also check for tabs)
				if ($field->field_type == 'maintext')
				{
					// Create main text field, via calling the display function of the textarea field (will also check for tabs)
					$maintext = @$field->value[0];
					$maintext = html_entity_decode($maintext, ENT_QUOTES, 'UTF-8');
					$field->maintext = & $maintext;
					FLEXIUtilities::call_FC_Field_Func('textarea', 'onDisplayField', array(&$field, &$this->item) );
				}
				
				// -- Tooltip for the current field
				$field_tooltip = $field->description ? 'class="hasTip" title="'.$field->label.'::'.$field->description.'"' : '';
				?>
				
				<?php	if ( !is_array($field->html) ) : ?>
					<tr>
						<td class="key">
							<label for="<?php echo $field->name; ?>" <?php echo $field_tooltip; ?> >
								<?php echo $field->label; ?>
							</label>
						</td>
						<td>
							<?php
								$noplugin = '<div id="fc-change-error" class="fc-error">'. JText::_( 'FLEXI_PLEASE_PUBLISH_PLUGIN' ) .'</div>';
								if(isset($field->html)){
									echo $field->html;
								} else {
									echo $noplugin;
								}
							?>
						</td>
					</tr>
					
				<?php else : ?>
			
					<tr>
						<td colspan="2">
							
							<?php $not_in_tabs = ""; ?>
							
							<div class="fctabber">
							<?php foreach ($field->html as $i => $field_html): ?>
								<?php
								if (!isset($field->tab_labels[$i])) {
									if (isset($field->html[$i])) $not_in_tabs .= "<div style='display:none!important'>".$field->html[$i]."</div>";
									continue;
								}
								?>
								<div class="tabbertab">
									<h3>
										<?php echo $field->tab_labels[$i]; ?>
									</h3>
								<?php
									$noplugin = '<div id="fc-change-error" class="fc-error">'. JText::_( 'FLEXI_PLEASE_PUBLISH_PLUGIN' ) .'</div>';
									echo $not_in_tabs;
									$not_in_tabs = ""; // reset
									if(isset($field->html[$i])){
										echo $field->html[$i];
									} else {
										echo $noplugin;
									}
								?>
								</div>
							<?php endforeach; ?>
							</div>
							
							<?php echo $not_in_tabs; ?>
							
						</td>
					</tr>
					
				<?php endif; ?>
			
			<?php
			}
			?>
		</table>
	</fieldset>

<?php elseif (getValueFCitem($this->item, 'id') == 0) : // new item, since administrator did not limit this, display message (user allowed to select item type) ?>
		<div class="fc-info"><?php echo JText::_( 'FLEXI_CHOOSE_ITEM_TYPE' ); ?></div>
<?php else : // existing item that has no custom fields, warn the user ?>
		<div class="fc-error"><?php echo JText::_( 'FLEXI_NO_FIELDS_TO_TYPE' ); ?></div>
<?php	endif; ?>

	
<?php if ($typeid) : // hide items parameters (standard, extended, template) if content type is not selected ?>

	<?php echo JHtml::_('sliders.start','plugin-sliders-'.getValueFCitem($this->item, "id"), array('useCookie'=>1)); ?>
	
	<?php 
		if ( $this->params->get('usemetadata_fe', 1) ) {
			echo JHtml::_('sliders.panel',JText::_('FLEXI_METADATA_INFORMATION'), "metadata-page");
	?>
		<fieldset class="panelform">
			<ul class="adminformlist">
				<li>
					<?php echo $this->item->getLabel('metadesc'); ?>
					<?php echo $this->item->getInput('metadesc'); ?>
				</li>
				<li>
					<?php echo $this->item->getLabel('metakey'); ?>
					<?php echo $this->item->getInput('metakey'); ?>
				</li>
				
		<?php if ($this->params->get('usemetadata_fe', 1) == 2 ) : ?>
		
			<?php foreach($this->item->getGroup('metadata') as $field): ?>
				<?php if ($field->hidden): ?>
					<?php echo $field->input; ?>
				<?php else: ?>
					<li>
						<?php echo $field->label; ?>
						<?php echo $field->input; ?>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
			
		<?php endif; ?>
		
		</fieldset>
	<?php } ?>
	
	<?php if ($this->perms['canpublish'] && $this->params->get('usepublicationdetails_fe', 1)) : ?>
	
		<?php echo JHtml::_('sliders.panel',JText::_('FLEXI_PUBLICATION_DETAILS'), 'details-options'); ?>
		<fieldset class="panelform">
		<ul class="adminformlist">
		<?php if ($this->perms['isSuperAdmin'] && $this->params->get('usepublicationdetails_fe', 1) == 2 ) : ?>
			<li><?php echo $this->item->getLabel('access');?>
			<?php echo $this->item->getInput('access');?></li>
			<li><?php echo $this->item->getLabel('created_by');?>
			<?php echo $this->item->getInput('created_by');?></li>
			<li><?php echo $this->item->getLabel('created_by_alias');?>
			<?php echo $this->item->getInput('created_by_alias');?></li>
			<li><?php echo $this->item->getLabel('created');?>
			<?php echo $this->item->getInput('created');?></li>
		<?php endif; ?>
			<li><?php echo $this->item->getLabel('publish_up');?>
			<?php echo $this->item->getInput('publish_up');?></li>
			<li><?php echo $this->item->getLabel('publish_down');?>
			<?php echo $this->item->getInput('publish_down');?></li>
		</ul>
		</fieldset>

	<?php endif; ?>
	
	<?php
	$useitemparams_fe = $this->params->get('useitemparams_fe');
	if ( empty($useitemparams_fe) ) {
		$useitemparams_fe = array();
	} else if ( !is_array($useitemparams_fe) ) {
		$useitemparams_fe = explode("|", $useitemparams_fe);
	}
	
	$fieldSets = $this->item->getFieldsets('attribs');
	foreach ($fieldSets as $name => $fieldSet) :
		$fieldsetname = str_replace("params-", "", $name);
		if ( !in_array($fieldsetname, $useitemparams_fe) ) continue;
		if ( $name=='themes' ) continue;
		
		$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_'.$name.'_FIELDSET_LABEL';
		echo JHtml::_('sliders.panel', JText::_('FLEXI_PARAMETERS') .": ". JText::_($label), $name.'-options');
	?>
		<fieldset class="panelform">
		<?php
			foreach ($this->item->getFieldset($name) as $field) :
				echo $field->label;
				echo $field->input;
			endforeach;
		?>
		</fieldset>
	<?php endforeach; ?>

	<?php echo JHtml::_('sliders.end'); ?>
		
		
	<?php if ($this->perms['cantemplates'] && $this->params->get('selecttheme_fe')) : ?>
		
	<?php
		$type_default_layout = $this->tparams->get('ilayout');
		echo '<h3 class="themes-title">' . JText::_( 'FLEXI_PARAMETERS_LAYOUT_THEMES' ) . '</h3>';

		foreach ($this->item->getFieldset('themes') as $field) :
			if ($field->hidden) echo $field->input;
			else echo $field->label . $field->input;
		endforeach;
		
	?>
	
	<blockquote id='__content_type_default_layout__'>
		<?php echo JText::sprintf( 'FLEXI_USING_CONTENT_TYPE_LAYOUT', $type_default_layout ); ?>
	</blockquote>
	
	<?php
	
		if ( $this->params->get('selecttheme_fe') == 2 ) :
			echo JHtml::_('sliders.start','template-sliders-'.getValueFCitem($this->item, "id"), array('useCookie'=>1));
		
			foreach ($this->tmpls as $tmpl) :
				$title = JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name;
				echo JHtml::_('sliders.panel',JText::_($title),  $tmpl->name."-attribs-options");
				
				?> <fieldset class="panelform"> <?php
					foreach ($tmpl->params->getGroup('attribs') as $field) :
						echo $field->label;
						echo $field->input;
					endforeach;
				?> </fieldset> <?php
				
			endforeach;
		endif;
	?>
	
	<?php endif; ?>
	
	<?php echo JHtml::_('sliders.end'); ?>
	
<?php	endif; // end of existing item ?>

		<br class="clear" />
		<?php echo JHTML::_( 'form.token' ); ?>
		<input type="hidden" name="task" id="task" value="" />
		<input type="hidden" name="option" value="com_flexicontent" />
		<input type="hidden" name="referer" value="<?php echo $referer; ?>" />
		<?php if ( getValueFCitem($this->item, 'id')==0 && $typeid ) : ?>
			<input type="hidden" name="jform[type_id]" value="<?php echo $typeid; ?>" />
		<?php endif;?>
		
		<?php echo $this->item->getInput('id');?>
		
		<?php if ($autopublished) : // autopublish enabled ?>
			<input type="hidden" id="state" name="jform[state]" value="<?php echo $autopublished;?>" />
			<input type="hidden" id="vstate" name="jform[vstate]" value="2" />
		<?php	endif; ?>
		
		<?php if (!$this->perms['canright']) : ?>
				<input type="hidden" id="jformrules" name="jform[rules][]" value="" />
		<?php endif; ?>
	</form>
</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
