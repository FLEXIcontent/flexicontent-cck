<?php
/**
 * @version 1.5 stable $Id: form.php 1246 2012-04-12 06:34:20Z ggppdk $
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
$itemlang = substr(getValueFCitem($this->item, 'language') ,0,2);
if (isset($this->row->item_translations)) foreach ($this->row->item_translations as $t) if ($t->shortcode==$itemlang) {$itemlangname = $t->name; break;}
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
$this->document->addStyleDeclaration(".fctabber{display:none;}");   // temporarily hide the tabbers until javascript runs, then the class will be changed to tabberlive

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
	var parent = obj.parentNode;
	parent.innerHTML = "";
	parent.parentNode.removeChild(parent);
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

	$allowbuttons_fe = $this->params->get('allowbuttons_fe');
	if ( empty($allowbuttons_fe) )						$allowbuttons_fe = array();
	else if ( ! is_array($allowbuttons_fe) )	$allowbuttons_fe = !FLEXI_J16GE ? array($allowbuttons_fe) : explode("|", $allowbuttons_fe);
	?>

	<form action="<?php echo $this->action ?>" method="post" name="adminForm" id="adminForm" class="form-validate" enctype="multipart/form-data">
		<div class="flexi_buttons" style="font-size:90%;">
			
		<?php if (in_array( 'apply', $allowbuttons_fe) ) : ?>
			<button class="button" type="button" onclick="return Joomla.submitbutton('apply');">
				<span class="fcbutton_apply"><?php echo JText::_( getValueFCitem($this->item, 'id') ? 'FLEXI_APPLY' : ($typeid ? 'FLEXI_ADD' : 'FLEXI_APPLY_TYPE' ) ) ?></span>
			</button>
		<?php endif; ?>
			
		<?php if ( $typeid ) : ?>
		
			<button class="button" type="button" onclick="return Joomla.submitbutton('save');">
				<span class="fcbutton_save"><?php echo JText::_( getValueFCitem($this->item, 'id') ? 'FLEXI_SAVE_A_RETURN' : 'FLEXI_ADD_A_RETURN' ) ?></span>
			</button>
			
		<?php if (in_array( 'save_preview', $allowbuttons_fe) ) : ?>
			<button class="button" type="button" onclick="return Joomla.submitbutton('save_a_preview');">
				<span class="fcbutton_preview_save"><?php echo JText::_( getValueFCitem($this->item, 'id') ? 'FLEXI_SAVE_A_PREVIEW' : 'FLEXI_ADD_A_PREVIEW' ) ?></span>
			</button>
		<?php endif; ?>

			<?php
				$params = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=100%,height=100%,directories=no,location=no';
				$link   = JRoute::_(FlexicontentHelperRoute::getItemRoute(getValueFCitem($this->item, 'id').':'.getValueFCitem($this->item, 'alias'), getValueFCitem($this->item, 'catid')).'&preview=1');
			?>
			
		<?php if (in_array( 'preview_latest', $allowbuttons_fe) ) : ?>
			<?php if ( getValueFCitem($this->item, 'id') ) : ?>
			<button class="button" type="button" onclick="window.open('<?php echo $link; ?>','preview2','<?php echo $params; ?>'); return false;">
				<span class="fcbutton_preview"><?php echo JText::_( $this->params->get('use_versioning', 1) ? 'FLEXI_PREVIEW_LATEST' :'FLEXI_PREVIEW' ) ?></span>
			</button>
			<?php endif; ?>
		<?php endif; ?>
			
		<?php endif; ?>
			
			<button class="button" type="button" onclick="return Joomla.submitbutton('cancel')">
				<span class="fcbutton_cancel"><?php echo JText::_( 'FLEXI_CANCEL' ) ?></span>
			</button>
			
		</div>
         
		<br class="clear" />
		<?php
			$approval_msg = JText::_( getValueFCitem($this->item, 'id')==0 ? 'FLEXI_REQUIRES_DOCUMENT_APPROVAL' : 'FLEXI_REQUIRES_VERSION_REVIEWAL') ;
			if ( !$canpublish && $this->params->get('use_versioning', 1) )  echo '<div style="text-align:right; width:100%; padding:0px; clear:both;">(*) '.$approval_msg.'</div>';
		?>
		
		<fieldset class="flexi_general">
			<legend><?php echo JText::_( 'FLEXI_GENERAL' ); ?></legend>
			<div class="flexi_formblock">
				<?php
					$field = @$this->fields['title'];
					$label_tooltip = @$field->description ? 'class="hasTip flexi_label" title="'.$field->label.'::'.$field->description.'"' : 'class="flexi_label"';
				?>
				<label id="jform_title-lbl" for="jform_title" <?php echo $label_tooltip; ?> >
					<?php echo @$field->label ? $field->label : $this->item->getLabel('title'); ?>
				</label>
				
			<?php	if ( isset($this->row->item_translations) ) :?>
			
				<!-- tabber start -->
				<div class="fctabber" style=''>
					<div class="tabbertab" style="padding: 0px;" >
						<h3> <?php echo '-'.$itemlangname.'-'; // $t->name; ?> </h3>
						<?php echo $this->item->getInput('title');?>
					</div>
					<?php foreach ($this->row->item_translations as $t): ?>
						<?php if ($itemlang!=$t->shortcode) : ?>
							<div class="tabbertab" style="padding: 0px;" >
								<h3> <?php echo $t->name; // $t->shortcode; ?> </h3>
								<?php
								$ff_id = 'jfdata_'.$t->shortcode.'_title';
								$ff_name = 'jfdata['.$t->shortcode.'][title]';
								?>
								<input class="inputbox" style='margin:0px;' type="text" id="<?php echo $ff_id; ?>" name="<?php echo $ff_name; ?>" value="<?php echo @$t->fields->title->value; ?>" size="50" maxlength="254" />
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<!-- tabber end -->
				
			<?php else : ?>
				<?php echo $this->item->getInput('title');?>
			<?php endif; ?>

			</div>
		
	<?php if ($this->params->get('usealias_fe', 1)) : ?>
					
			<div class="flexi_formblock">
				<span class="flexi_label">
					<?php echo $this->item->getLabel('alias');?>
				</span>
				
			<?php	if ( isset($this->row->item_translations) ) :?>
			
				<!-- tabber start -->
				<div class="fctabber" style=''>
					<div class="tabbertab" style="padding: 0px;" >
						<h3> <?php echo '-'.$itemlangname.'-'; // $t->name; ?> </h3>
						<?php echo $this->item->getInput('alias');?>
					</div>
					<?php foreach ($this->row->item_translations as $t): ?>
						<?php if ($itemlang!=$t->shortcode) : ?>
							<div class="tabbertab" style="padding: 0px;" >
								<h3> <?php echo $t->name; // $t->shortcode; ?> </h3>
								<?php
								$ff_id = 'jfdata_'.$t->shortcode.'_alias';
								$ff_name = 'jfdata['.$t->shortcode.'][alias]';
								?>
								<input class="inputbox" style='margin:0px;' type="text" id="<?php echo $ff_id; ?>" name="<?php echo $ff_name; ?>" value="<?php echo @$t->fields->alias->value; ?>" size="50" maxlength="254" />
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<!-- tabber end -->
				
			<?php else : ?>
				<?php echo $this->item->getInput('alias');?>
			<?php endif; ?>
			
			</div>
	
	<?php endif; ?>
	
	<?php if ($typeid==0) : ?>
	
			<div class="flexi_formblock">
				<label id="type_id-lbl" for="type_id" class="flexi_label" >
					<?php echo JText::_( 'FLEXI_TYPE' ); ?>
				</label>
				<?php echo $this->lists['type']; ?>
			</div>
			
	<?php endif; ?>

	
	<?php if ($cid && $overridecatperms && $isNew) : /* MENU SPECIFIED categories subset (instead of categories with CREATE perm) */ ?>
			<div class="flexi_formblock">
				<label id="jform_catid-lbl" for="jform_catid" class="flexi_label">
					<?php echo JText::_( $in_single_cat ? 'FLEXICONTENT_CATEGORY' : 'FLEXI_PRIMARY_CATEGORY' );  /* when submitting to single category, call this field just 'CATEGORY' instead of 'PRIMARY CATEGORY' */ ?>
				</label>
				<?php echo $fixedmaincat; ?>
			</div>
		<?php if ($postcats!=1 && !$in_single_cat) : /* hide when submiting to single category, since we will only show primary category field */ ?>
			<div class="flexi_formblock">
				<label id="jform_cid-lbl" for="jform_cid" class="flexi_label">
					<?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' );?>
					<?php if ($postcats==2) : /* add "ctrl-click" tip when selecting multiple categories */ ?>
						<span class="editlinktip hasTip" title="<?php echo JText::_ ( 'FLEXI_NOTES' ); ?>::<?php echo JText::_ ( 'FLEXI_CATEGORIES_NOTES' );?>">
							<?php echo JHTML::image ( 'components/com_flexicontent/assets/images/icon-16-hint.png', JText::_ ( 'FLEXI_NOTES' ) ); ?>
						</span>
					<?php endif; ?>
				</label>
				<?php echo $fixedcats; ?>
			</div>
		<?php endif; ?>
	<?php else : ?>
			<div class="flexi_formblock">
				<label id="jform_catid-lbl" for="jform_catid" class="flexi_label">
					<?php echo JText::_( (!$this->perms['multicat']) ? 'FLEXICONTENT_CATEGORY' : 'FLEXI_PRIMARY_CATEGORY' );  /* if no multi category allowed for user, then call it just 'CATEGORY' instead of 'PRIMARY CATEGORY' */ ?>
				</label>
				<?php echo $this->lists['catid']; ?>
			</div>
		<?php if ($this->perms['multicat']) : ?>
			<div class="flexi_formblock">
				<label id="jform_cid-lbl" for="jform_cid" class="flexi_label">
					<?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' );?>
					<span class="editlinktip hasTip" title="<?php echo JText::_ ( 'FLEXI_NOTES' ); ?>::<?php echo JText::_ ( 'FLEXI_CATEGORIES_NOTES' );?>">
						<?php echo JHTML::image ( 'components/com_flexicontent/assets/images/icon-16-hint.png', JText::_ ( 'FLEXI_NOTES' ) ); ?>
					</span>
				</label>
				<?php echo $this->lists['cid']; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>


	<?php if (!$autopublished && $canpublish) : // autopublished disabled, display state selection field to the user that can publish ?>
	
			<div class="flexi_formblock">
				<?php
					$field = @$this->fields['state'];
					$label_tooltip = @$field->description ? 'class="hasTip flexi_label" title="'.$field->label.'::'.$field->description.'"' : 'class="flexi_label"';
				?>
				<label id="jform_state-lbl" for="jform_state" <?php echo $label_tooltip; ?> >
					<?php echo @$field->label ? $field->label : $this->item->getLabel('state'); ?>
				</label>
				<?php echo $this->item->getInput('state'); ?>
	  		<?php	if ($autoapprove) : ?>
	  			<input type="hidden" id="vstate" name="jform[vstate]" value="2" />
	  		<?php	endif;?>
			</div>
		
		<?php	if (!$autoapprove) :	?>
			<div class="flexi_formblock">
				<label for="vstate" class="flexi_label">
				<?php echo JText::_( 'FLEXI_APPROVE_VERSION' );?>
				</label>
				<?php echo $this->lists['vstate']; ?>
			</div>
		<?php	endif; ?>
		
	<?php elseif (!$autopublished && !$canpublish) : ?>
			<?php
				// Enable approval if versioning disabled, this make sense,
				// since if use can edit item THEN item should be updated !!!
				$item_vstate = $this->params->get('use_versioning', 1) ? 1 : 2;
			?>
			
			<div class="flexi_formblock">
				<?php
					$field = @$this->fields['state'];
					$label_tooltip = @$field->description ? 'class="hasTip flexi_label" title="'.$field->label.'::'.$field->description.'"' : 'class="flexi_label"';
				?>
				<label id="jform_state-lbl" for="jform_state" <?php echo $label_tooltip; ?> >
					<?php echo @$field->label ? $field->label : $this->item->getLabel('state'); ?>
				</label>
	  		<?php 
	  			//echo JText::_( 'FLEXI_NEEDS_APPROVAL' );
	  			if ( !$this->item )
	  				echo 'You cannot publish/unpublish this item, it will be reviewed by administrator';
	  			else
	  				echo 'You cannot publish/unpublish of this item';
					// Enable approval if versioning disabled, this make sense,
					// since if use can edit item THEN item should be updated !!!
					$item_vstate = $this->params->get('use_versioning', 1) ? 1 : 2;
	  		?>
				<input type="hidden" id="state" name="jform[state]" value="<?php echo $this->item->id ? $this->item->state : -4; ?>" />
				<input type="hidden" id="vstate" name="jform[vstate]" value="<?php echo $item_vstate; ?>" />
			</div>
	
	<?php endif; ?>
	
		<?php if (FLEXI_FISH || FLEXI_J16GE) : ?>
			<div class="flexi_formblock">
				<span class="flexi_label">
					<?php echo $this->item->getLabel('language'); ?>
				</span>
				<?php echo $this->lists['languages']; ?>
			</div>
			
			<!--div class="flexi_formblock">
				<span class="flexi_label">
					<?php echo $this->item->getLabel('lang_parent_id'); ?>
				</span>
				<?php echo $this->item->getInput('lang_parent_id'); ?>
			</div-->
		<?php endif; ?>
		</fieldset>
		
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
		<?php endif; ?>

	<?php if ($typeid && ($this->perms['cantags'] || count(@$this->usedtagsdata)) ) : ?>
		<?php $display_tags = $this->params->get('usetags_fe', 1)==0 ? 'style="display:none;"' : ''; ?>
		
		<fieldset class="flexi_tags" <?php echo $display_tags ?> >
			<?php
				$field = @$this->fields['tags'];
				$label_tooltip = @$field->description ? 'class="hasTip" title="'.$field->label.'::'.$field->description.'"' : 'class=""';
			?>
			<legend <?php echo $label_tooltip; ?> ><?php echo JText::_( 'FLEXI_TAGS' ); ?></legend>
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
					<br class="clear" />
				</div>
		<?php if ( $this->perms['cantags'] && $this->params->get('usetags_fe', 1)==1 ) : ?>
				<div id="tags">
					<label for="input-tags"><?php echo JText::_( 'FLEXI_ADD_TAG' ); ?>
					<input type="text" id="input-tags" name="tagname" tagid='0' tagname='' /><span id='input_new_tag'></span>
					</label>
				</div>
		<?php endif; ?>
		</fieldset>
		
	<?php endif; ?>

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
		
		<table class="admintable" width="100%" style="border-width:0px!important;" >
			<?php
			$hidden = array(
				'fcloadmodule',
				'fcpagenav',
				'toolbar'
			);
			
			$noplugin = '<div id="fc-change-error" class="fc-error">'. JText::_( 'FLEXI_PLEASE_PUBLISH_PLUGIN' ) .'</div>';
			foreach ($this->fields as $field) {
				
				// SKIP frontend hidden fields from this listing
				if ( ($field->iscore && $field->field_type!='maintext')  ||  $field->parameters->get('frontend_hidden')  ||  in_array($field->field_type, $hidden) ) continue;
				
				// check to SKIP (hide) field e.g. description field ('maintext'), alias field etc
				if ( $this->tparams->get('hide_'.$field->field_type) ) continue;
				
				// -- Tooltip for the current field label
				$label_tooltip = $field->description ? 'class="flexi_label hasTip" title="'.$field->label.'::'.$field->description.'"' : ' class="flexi_label" ';
				$label_style = ""; //( $field->field_type == 'maintext' || $field->field_type == 'textarea' ) ? " style='clear:both; float:none;' " : "";
				$not_in_tabs = "";
				?>
				
			<tr>
				<td class="fcfield-row" style='padding:0px 2px 0px 2px; border: 0px solid lightgray;'>
					
					<label for="<?php echo $field->name; ?>" <?php echo $label_tooltip . $label_style; ?> >
						<?php echo $field->label; ?>
					</label>
					
					<div style='float:left!important; padding:0px!important; margin:0px!important; '>
					
				<?php	if ($field->field_type=='maintext' && isset($this->row->item_translations) ) : ?>
					
					<!-- tabber start -->
					<div class="fctabber" style=''>
						<div class="tabbertab" style="padding: 0px;" >
							<h3> <?php echo '- '.$itemlangname.' -'; // $t->name; ?> </h3>
							<?php
								$field_tab_labels = & $field->tab_labels;
								$field_html       = & $field->html;
								echo !is_array($field_html) ? $field_html : flexicontent_html::createFieldTabber( $field_html, $field_tab_labels, "");
							?>
						</div>
						<?php foreach ($this->row->item_translations as $t): ?>
							<?php if ($itemlang!=$t->shortcode) : ?>
								<div class="tabbertab" style="padding: 0px;" >
									<h3> <?php echo $t->name; // $t->shortcode; ?> </h3>
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
				
				<?php else : ?>
			
					<?php	if ( !is_array($field->html) ) : ?>
					
						<?php echo isset($field->html) ? $field->html : $noplugin; ?>
					
					<?php else : ?>
					
						<!-- tabber start -->
						<div class="fctabber">
						<?php foreach ($field->html as $i => $fldhtml): ?>
							<?php
								// Hide field when it has no label, and skip creating tab
								$not_in_tabs .= !isset($field->tab_labels[$i]) ? "<div style='display:none!important'>".$field->html[$i]."</div>" : "";
								if (!isset($field->tab_labels[$i]))	continue;
							?>
							
							<div class="tabbertab">
								<h3> <?php echo $field->tab_labels[$i]; // Current TAB LABEL ?> </h3>
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
						
					<?php endif; ?>
					
				<?php endif; ?>
				
					</div>
			
				</td>
			</tr>
				
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
		if ( $this->params->get('usemetadata_fe', 1) ) {
			echo JHtml::_('sliders.panel',JText::_('FLEXI_METADATA_INFORMATION'), "metadata-page");
			
			//echo $this->form->getLabel('metadesc');
			//echo $this->form->getInput('metadesc');
			//echo $this->form->getLabel('metakey');
			//echo $this->form->getInput('metakey');
	?>
		<fieldset class="panelform">
			<ul class="adminformlist">
				<li>
					<?php echo $this->item->getLabel('metadesc'); ?>

			<?php	if ( isset($this->row->item_translations) ) :?>
				
				<!-- tabber start -->
				<div class="fctabber" style='display:inline-block;'>
					<div class="tabbertab" style="padding: 0px;" >
						<h3> <?php echo '-'.$itemlangname.'-'; // $t->name; ?> </h3>
						<?php echo $this->item->getInput('metadesc'); ?>
					</div>
					<?php foreach ($this->row->item_translations as $t): ?>
						<?php if ($itemlang!=$t->shortcode) : ?>
							<div class="tabbertab" style="padding: 0px;" >
								<h3> <?php echo $t->name; // $t->shortcode; ?> </h3>
								<?php
								$ff_id = 'jfdata_'.$t->shortcode.'_metadesc';
								$ff_name = 'jfdata['.$t->shortcode.'][metadesc]';
								?>
								<textarea id="<?php echo $ff_id; ?>" class="inputbox" rows="3" cols="50" name="<?php echo $ff_name; ?>"><?php echo @$t->fields->metadesc->value; ?></textarea>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<!-- tabber end -->
			
			<?php else : ?>
				<?php echo $this->item->getInput('metadesc'); ?>
			<?php endif; ?>
			
				</li>
				<li>
					<?php echo $this->item->getLabel('metakey'); ?>
			<?php	if ( isset($this->row->item_translations) ) :?>
			
				<!-- tabber start -->
				<div class="fctabber" style='display:inline-block;'>
					<div class="tabbertab" style="padding: 0px;" >
						<h3> <?php echo '-'.$itemlangname.'-'; // $t->name; ?> </h3>
						<?php echo $this->item->getInput('metakey'); ?>
					</div>
					<?php foreach ($this->row->item_translations as $t): ?>
						<?php if ($itemlang!=$t->shortcode) : ?>
							<div class="tabbertab" style="padding: 0px;" >
								<h3> <?php echo $t->name; // $t->shortcode; ?> </h3>
								<?php
								$ff_id = 'jfdata_'.$t->shortcode.'_metakey';
								$ff_name = 'jfdata['.$t->shortcode.'][metakey]';
								?>
								<textarea id="<?php echo $ff_id; ?>" class="inputbox" rows="3" cols="50" name="<?php echo $ff_name; ?>"><?php echo @$t->fields->metakey->value; ?></textarea>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<!-- tabber end -->
			
			<?php else : ?>
				<?php echo $this->item->getInput('metakey'); ?>
			<?php endif; ?>

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
