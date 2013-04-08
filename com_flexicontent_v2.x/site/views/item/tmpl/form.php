<?php
/**
 * @version 1.5 stable $Id: form.php 1267 2012-05-07 10:55:21Z ggppdk $
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

// Create some variables
$isnew = !$this->item->id;
$typeid = !$isnew ? $this->item->type_id : JRequest::getInt('typeid') ;

// Create info images
$infoimage = JHTML::image ( 'components/com_flexicontent/assets/images/icon-16-hint.png', JText::_( 'FLEXI_NOTES' ) );

// Calculate refer parameter for returning to this page when user ends editing/submitting
$return = JRequest::getString('return', '', 'get');
if ($return) {
	$referer = base64_decode( $return );
} else {
	$referer = str_replace(array('"', '<', '>', "'"), '', @$_SERVER['HTTP_REFERER']);
}

FLEXI_J30GE ? JHtml::_('behavior.framework') : JHTML::_('behavior.mootools');
flexicontent_html::loadJQuery();

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
			jQuery(\"#input-tags\").autocomplete(\"".JURI::base()."index.php?option=com_flexicontent&view=item&task=viewtags&tmpl=component&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1\", {
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

	<?php if ($this->params->def( 'show_page_title', 1 )) : ?>
	<h1 class="componentheading">
		<?php echo $this->params->get('page_title'); ?>
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
		<div class="flexi_buttons" style="font-size:90%;">
			
		<?php if ( $this->perms['canedit'] || in_array( 'apply', $allowbuttons_fe) ) : ?>
			<button class="fc_button" type="button" onclick="return Joomla.submitbutton('apply');">
				<span class="fcbutton_apply"><?php echo JText::_( !$isnew ? 'FLEXI_APPLY' : ($typeid ? 'FLEXI_ADD' : 'FLEXI_APPLY_TYPE' ) ) ?></span>
			</button>
		<?php endif; ?>
			
		<?php if ( $typeid ) : ?>
		
			<button class="fc_button" type="button" onclick="return Joomla.submitbutton('save');">
				<span class="fcbutton_save"><?php echo JText::_( !$isnew ? 'FLEXI_SAVE_A_RETURN' : 'FLEXI_ADD_A_RETURN' ) ?></span>
			</button>
			
		<?php if (in_array( 'save_preview', $allowbuttons_fe) ) : ?>
			<button class="fc_button" type="button" onclick="return Joomla.submitbutton('save_a_preview');">
				<span class="fcbutton_preview_save"><?php echo JText::_( !$isnew ? 'FLEXI_SAVE_A_PREVIEW' : 'FLEXI_ADD_A_PREVIEW' ) ?></span>
			</button>
		<?php endif; ?>

			<?php
				$params = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=100%,height=100%,directories=no,location=no';
				$link   = JRoute::_(FlexicontentHelperRoute::getItemRoute($this->item->id.':'.$this->item->alias, $this->item->catid).'&preview=1');
			?>
			
		<?php if (in_array( 'preview_latest', $allowbuttons_fe) ) : ?>
			<?php if ( !$isnew ) : ?>
			<button class="fc_button" type="button" onclick="window.open('<?php echo $link; ?>','preview2','<?php echo $params; ?>'); return false;">
				<span class="fcbutton_preview"><?php echo JText::_( $this->params->get('use_versioning', 1) ? 'FLEXI_PREVIEW_LATEST' :'FLEXI_PREVIEW' ) ?></span>
			</button>
			<?php endif; ?>
		<?php endif; ?>
			
		<?php endif; ?>
			
			<button class="fc_button" type="button" onclick="return Joomla.submitbutton('cancel')">
				<span class="fcbutton_cancel"><?php echo JText::_( 'FLEXI_CANCEL' ) ?></span>
			</button>
			
		</div>
         
		<br class="clear" />
		<?php
			$approval_msg = JText::_( $isnew ? 'FLEXI_REQUIRES_DOCUMENT_APPROVAL' : 'FLEXI_REQUIRES_VERSION_REVIEWAL') ;
			if ( !$this->perms['canpublish'] && $this->params->get('use_versioning', 1) )  echo '<div style="text-align:right; width:100%; padding:0px; clear:both;">(*) '.$approval_msg.'</div>';
		?>
		
		<fieldset class="flexi_general">
			<legend><?php echo JText::_( 'FLEXI_GENERAL' ); ?></legend>
			<div class="flexi_formblock">
				<?php
					$field = @$this->fields['title'];
					$label_tooltip = @$field->description ? 'class="hasTip flexi_label" title="'.htmlspecialchars($field->label, ENT_COMPAT, 'UTF-8').'::'.htmlspecialchars($field->description, ENT_COMPAT, 'UTF-8').'"' : 'class="flexi_label"';
				?>
				<label id="jform_title-lbl" for="jform_title" <?php echo $label_tooltip; ?> >
					<?php echo @$field->label ? $field->label : $this->form->getLabel('title'); ?>
				</label>
				
			<?php	if ( isset($this->item->item_translations) ) :?>
			
				<!-- tabber start -->
				<div class="fctabber" style=''>
					<div class="tabbertab" style="padding: 0px;" >
						<h3> <?php echo '-'.$this->itemlang->name.'-'; // $t->name; ?> </h3>
						<?php echo $this->form->getInput('title');?>
					</div>
					<?php foreach ($this->item->item_translations as $t): ?>
						<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*') : ?>
							<div class="tabbertab" style="padding: 0px;" >
								<h3> <?php echo $t->name; // $t->shortcode; ?> </h3>
								<?php
								$ff_id = 'jfdata_'.$t->shortcode.'_title';
								$ff_name = 'jfdata['.$t->shortcode.'][title]';
								?>
								<input class="inputbox fc_form_title" style='margin:0px;' type="text" id="<?php echo $ff_id; ?>" name="<?php echo $ff_name; ?>" value="<?php echo @$t->fields->title->value; ?>" size="50" maxlength="254" />
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<!-- tabber end -->
				
			<?php else : ?>
				<?php echo $this->form->getInput('title');?>
			<?php endif; ?>

			</div>
		
	<?php if ($this->params->get('usealias_fe', 1)) : ?>
					
			<div class="flexi_formblock">
				<span class="flexi_label">
					<?php echo $this->form->getLabel('alias');?>
				</span>
				
			<?php	if ( isset($this->item->item_translations) ) :?>
			
				<!-- tabber start -->
				<div class="fctabber" style=''>
					<div class="tabbertab" style="padding: 0px;" >
						<h3> <?php echo '-'.$this->itemlang->name.'-'; // $t->name; ?> </h3>
						<?php echo $this->form->getInput('alias');?>
					</div>
					<?php foreach ($this->item->item_translations as $t): ?>
						<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*') : ?>
							<div class="tabbertab" style="padding: 0px;" >
								<h3> <?php echo $t->name; // $t->shortcode; ?> </h3>
								<?php
								$ff_id = 'jfdata_'.$t->shortcode.'_alias';
								$ff_name = 'jfdata['.$t->shortcode.'][alias]';
								?>
								<input class="inputbox fc_form_alias" style='margin:0px;' type="text" id="<?php echo $ff_id; ?>" name="<?php echo $ff_name; ?>" value="<?php echo @$t->fields->alias->value; ?>" size="50" maxlength="254" />
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<!-- tabber end -->
				
			<?php else : ?>
				<?php echo $this->form->getInput('alias');?>
			<?php endif; ?>
			
			</div>
	
	<?php endif; ?>
	
	<?php if ($typeid==0) : ?>
	
		<div class="flexi_formblock">
			<label id="type_id-lbl" for="jform_type_id" class="flexi_label" >
				<?php echo JText::_( 'FLEXI_TYPE' ); ?>
			</label>
		<?php echo $this->lists['type']; ?>
		</div>
			
	<?php endif; ?>

	
	<?php if ($isnew && $this->menuCats) : /* MENU SPECIFIED categories subset (instead of categories with CREATE perm) */ ?>
			<div class="flexi_formblock">
				<label id="jform_catid-lbl" for="jform_catid" class="flexi_label">
					<?php echo JText::_( !$this->menuCats->cid ? 'FLEXICONTENT_CATEGORY' : 'FLEXI_PRIMARY_CATEGORY' );  /* when submitting to single category, call this field just 'CATEGORY' instead of 'PRIMARY CATEGORY' */ ?>
				</label>
				<?php echo $this->menuCats->catid; ?>
			</div>
		<?php if ( $this->menuCats->cid ) : /* Check if multiple-categories field was created (it is not when submiting to single category) */ ?>
			<div class="flexi_formblock">
				<label id="jform_cid-lbl" for="jform_cid" class="flexi_label">
					<?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' );?>
					<span class="editlinktip hasTip" title="<?php echo htmlspecialchars(JText::_ ( 'FLEXI_NOTES' ), ENT_COMPAT, 'UTF-8'); ?>::<?php echo htmlspecialchars(JText::_ ( 'FLEXI_CATEGORIES_NOTES' ), ENT_COMPAT, 'UTF-8');?>">
						<?php echo $infoimage; ?>
					</span>
				</label>
				<?php echo $this->menuCats->cid; ?>
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
					<span class="editlinktip hasTip" title="<?php echo htmlspecialchars(JText::_ ( 'FLEXI_NOTES' ), ENT_COMPAT, 'UTF-8'); ?>::<?php echo htmlspecialchars(JText::_ ( 'FLEXI_CATEGORIES_NOTES' ), ENT_COMPAT, 'UTF-8');?>">
						<?php echo $infoimage; ?>
					</span>
				</label>
				<?php echo $this->lists['cid']; ?>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( $isnew && $this->params->get('autopublished', 0) ) :  // Auto publish new item via menu override ?>
	
		<input type="hidden" id="state" name="jform[state]" value="1" />
		<input type="hidden" id="vstate" name="jform[vstate]" value="2" />

	<?php elseif ( $this->perms['canpublish'] ) : // Display state selection field to the user that can publish ?>
	
			<div class="flexi_formblock">
				<?php
					$field = @$this->fields['state'];
					$label_tooltip = @$field->description ? 'class="hasTip flexi_label" title="'.htmlspecialchars($field->label, ENT_COMPAT, 'UTF-8').'::'.htmlspecialchars($field->description, ENT_COMPAT, 'UTF-8').'"' : 'class="flexi_label"';
				?>
				<label id="jform_state-lbl" for="jform_state" <?php echo $label_tooltip; ?> >
					<?php echo @$field->label ? $field->label : $this->form->getLabel('state'); ?>
				</label>
				<?php echo $this->form->getInput('state'); ?>
			</div>
		
		<?php	if (  $this->params->get('use_versioning', 1) && $this->params->get('allow_unapproved_latest_version', 0)  ) : ?>
			<div class="flexi_formblock">
				<label for="vstate" class="flexi_label">
				<?php echo JText::_( 'FLEXI_APPROVE_VERSION' );?>
				</label>
				<?php echo $this->form->getInput('vstate'); ?>
			</div>
		<?php	else : ?>
	  	<input type="hidden" id="vstate" name="jform[vstate]" value="2" />
		<?php	endif; ?>
		
	<?php else :  // Display message to user that he/she can not publish ?>
			<div class="flexi_formblock">
				<?php
					$field = @$this->fields['state'];
					$label_tooltip = @$field->description ? 'class="hasTip flexi_label" title="'.htmlspecialchars($field->label, ENT_COMPAT, 'UTF-8').'::'.htmlspecialchars($field->description, ENT_COMPAT, 'UTF-8').'"' : 'class="flexi_label"';
				?>
				<label id="jform_state-lbl" for="jform_state" <?php echo $label_tooltip; ?> >
					<?php echo @$field->label ? $field->label : $this->form->getLabel('state'); ?>
				</label>
	  		<?php 
	  			echo JText::_( 'FLEXI_NEEDS_APPROVAL' );
					// Enable approval if versioning disabled, this make sense since if use can edit item THEN item should be updated !!!
					$item_vstate = $this->params->get('use_versioning', 1) ? 1 : 2;
	  		?>
				<input type="hidden" id="state" name="jform[state]" value="<?php echo !$isnew ? $this->item->state : -4; ?>" />
				<input type="hidden" id="vstate" name="jform[vstate]" value="<?php echo $item_vstate; ?>" />
			</div>
			
	<?php endif; ?>
	
		<?php if (FLEXI_FISH || FLEXI_J16GE) : ?>
		
			<div class="flexi_formblock">
				<span class="flexi_label">
					<?php echo $this->form->getLabel('language'); ?>
				</span>
				<?php if ( in_array( 'mod_item_lang', $allowlangmods_fe) || $isnew ) : ?>
					<?php echo $this->lists['languages']; ?>
				<?php else: ?>
					<?php echo $this->itemlang->image.' ['.$this->itemlang->name.']'; ?>
				<?php endif; ?>
			</div>
			
			<?php if ( $this->params->get('enable_translation_groups') ) : ?>
			<div class="flexi_formblock">
				<label id="jform_lang_parent_id-lbl" for="jform_lang_parent_id" class="flexi_label" >
					<?php echo JText::_( 'FLEXI_ORIGINAL_CONTENT_ITEM' );?>
					<span class="editlinktip hasTip" title="::<?php echo htmlspecialchars(JText::_ ( 'FLEXI_ORIGINAL_CONTENT_ITEM_DESC' ), ENT_COMPAT, 'UTF-8');?>">
						<?php echo JHTML::image ( 'components/com_flexicontent/assets/images/icon-16-hint.png', JText::_ ( 'FLEXI_ORIGINAL_CONTENT_ITEM' ) ); ?>
					</span>
				</label>
				
				<?php if ( !$isnew  && (substr(flexicontent_html::getSiteDefaultLang(), 0,2) == substr($this->item->language, 0,2) || $this->item->language=='*') ) : ?>
					<br/><?php echo JText::_( $this->item->language=='*' ? 'FLEXI_ORIGINAL_CONTENT_ALL_LANGS' : 'FLEXI_ORIGINAL_TRANSLATION_CONTENT' );?>
					<input type="hidden" name="jform[lang_parent_id]" id="jform_lang_parent_id" value="<?php echo $this->item->id; ?>" />
				<?php else : ?>
					<?php
					if ( in_array( 'mod_item_lang', $allowlangmods_fe) || $isnew || $this->item->id==$this->item->lang_parent_id) {
						$app = JFactory::getApplication();
						$option = JRequest::getVar('option');
						$app->setUserState( $option.'.itemelement.langparent_item', 1 );
						$app->setUserState( $option.'.itemelement.type_id', $this->item->type_id);
						$app->setUserState( $option.'.itemelement.created_by', $this->item->created_by);
						echo '<small>'.JText::_( 'FLEXI_ORIGINAL_CONTENT_IGNORED_IF_DEFAULT_LANG' ).'</small><br>';
						echo $this->form->getInput('lang_parent_id');
					} else {
						echo JText::_( 'FLEXI_ORIGINAL_CONTENT_ALREADY_SET' );
					}
					?>
				<?php endif; ?>
			</div>
			
			<div class="flexi_formblock">
				<label id="jform_lang_parent_id-lbl" for="jform_lang_parent_id" class="flexi_label" >
					<?php echo JText::_( 'FLEXI_ASSOC_TRANSLATIONS' );?>
				</label>
				<?php
				if ( !empty($this->lang_assocs) ) {

					$row_modified = 0;
					foreach($this->lang_assocs as $assoc_item) {
						if ($assoc_item->id == $this->item->lang_parent_id) {
							$row_modified = strtotime($assoc_item->modified);
							if (!$row_modified)  $row_modified = strtotime($assoc_item->created);
						}
					}
					
					foreach($this->lang_assocs as $assoc_item) {
						if ($assoc_item->id==$this->item->id) continue;
						
						$_link  = 'index.php?option=com_flexicontent&view='.FLEXI_ITEMVIEW.'&task=edit&id='. $assoc_item->id;
						$_title = htmlspecialchars(JText::_ ( 'FLEXI_EDIT_ASSOC_TRANSLATION' ), ENT_COMPAT, 'UTF-8').':: ['. $assoc_item->lang .'] '. htmlspecialchars($assoc_item->title, ENT_COMPAT, 'UTF-8');
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
			
			<?php endif; ?>
			
			<?php if ( $this->params->get('allowdisablingcomments_fe') ) : ?>
			<div class="flexi_formblock">
				<label id="jform_attribs_comments-title" class="flexi_label hasTip" title="<?php echo htmlspecialchars(JText::_ ( 'FLEXI_ALLOW_COMMENTS' ), ENT_COMPAT, 'UTF-8');?>::<?php echo htmlspecialchars(JText::_ ( 'FLEXI_ALLOW_COMMENTS_DESC' ), ENT_COMPAT, 'UTF-8');?>" >
					<?php echo JText::_( 'FLEXI_ALLOW_COMMENTS' );?>
				</label>
				<?php echo $this->lists['disable_comments']; ?>
			</div>
			<?php endif; ?>

			<?php if ( $this->params->get('allow_subscribers_notify_fe', 0) && $this->subscribers) : ?>
			<div class="flexi_formblock">
				<label id="jform_notify-lbl" for="jform_notify" class="flexi_label">
					<?php echo JText::_( 'FLEXI_NOTIFY_FAVOURING_USERS' ).':'; ?>
					<span class="editlinktip hasTip" title="<?php echo htmlspecialchars(JText::_ ( 'FLEXI_NOTES' ), ENT_COMPAT, 'UTF-8'); ?>::<?php echo htmlspecialchars(JText::_ ( 'FLEXI_NOTIFY_NOTES' ), ENT_COMPAT, 'UTF-8');?>">
						<?php echo $infoimage; ?>
					</span>
				</label>
					
				<input type="checkbox" name="jform[notify]" id="jform_notify" />
				(<?php echo $this->subscribers . ' ' . (($this->subscribers > 1) ? JText::_( 'FLEXI_SUBSCRIBERS' ) : JText::_( 'FLEXI_SUBSCRIBER' )); ?>)
			</div>
			<?php endif; ?>
			
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
					<table id="tabacces" class="admintable" width="100%" style="*position: relative;">
						<tr>
							<td>
								<div id="accessrules"><?php echo $this->form->getInput('rules'); ?></div>
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
				$label_tooltip = @$field->description ? 'class="hasTip" title="'.htmlspecialchars($field->label, ENT_COMPAT, 'UTF-8').'::'.htmlspecialchars($field->description, ENT_COMPAT, 'UTF-8').'"' : 'class=""';
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

<?php if ($this->fields && $this->item->type_id) : ?>

	<?php
		$this->document->addScriptDeclaration("
		window.addEvent('domready', function() {
			$$('#jformtype_id').addEvent('change', function(ev) {
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
			$typename = @$types[$this->item->type_id]['name'];
			echo $typename ? JText::_( 'FLEXI_ITEM_TYPE' ) . ' : ' . $typename : JText::_( 'FLEXI_TYPE_NOT_DEFINED' ); ?>
		</legend>
		
		<!--table class="admintable" width="100%" style="border-width:0px!important;" -->
			<?php
			$hidden = array('fcloadmodule', 'fcpagenav', 'toolbar');
			
			$noplugin = '<div id="fc-change-error" class="fc-error">'. JText::_( 'FLEXI_PLEASE_PUBLISH_PLUGIN' ) .'</div>';
			$row_k = 0;
			foreach ($this->fields as $field)
			{
				// SKIP frontend hidden fields from this listing
				if (
					($field->iscore && $field->field_type!='maintext')  ||
				  $field->parameters->get('frontend_hidden')  ||
				  (in_array($field->field_type, $hidden) && empty($field->html)) ||
				  in_array($field->formhidden, array(1,3))
				 ) continue;
				
				// check to SKIP (hide) field e.g. description field ('maintext'), alias field etc
				if ( $this->tparams->get('hide_'.$field->field_type) ) continue;
				
				// -- Tooltip for the current field label
				$edithelp = $field->edithelp ? $field->edithelp : 1;
				$label_tooltip = ( $field->description && ($edithelp==1 || $edithelp==2) ) ?
					' class="flexi_label hasTip '.($edithelp==2 ? ' fc_tooltip_icon_fe ' : '').'" title="'.htmlspecialchars($field->label, ENT_COMPAT, 'UTF-8').'::'.htmlspecialchars($field->description, ENT_COMPAT, 'UTF-8').'" ' :
					' class="flexi_label" ';
				$label_style = ""; //( $field->field_type == 'maintext' || $field->field_type == 'textarea' ) ? " style='clear:both; float:none;' " : "";
				$not_in_tabs = "";
				
				if ($field->field_type=='groupmarker') :
					echo $field->html;
					continue;
				endif;
				
				$row_k = 1 - $row_k;
				$width = $field->parameters->get('container_width', '' );
				if ($width)  $width = 'width:' .$width. ($width != (int)$width ? 'px' : '');
			?>
				
			<!--tr-->
				<!--td id="fcfield-row_<?php echo $field->id; ?> class="fcfield-row" style='padding:0px 2px 0px 2px; border: 0px solid lightgray;'-->
					<div class='clear' style='display:block; float:left; clear:both!important'></div>
					
					<label for="<?php echo (FLEXI_J16GE ? 'custom_' : '').$field->name; ?>" <?php echo $label_tooltip . $label_style; ?> >
						<?php echo $field->label; ?>
					</label>
					
					<div style="float:left!important; padding:0px!important; margin:0px!important; <?php echo $width; ?>;"
						class="fcfield_row<?php echo $row_k;?> container_fcfield
						container_fcfield_id_<?php echo $field->id;?> container_fcfield_name_<?php echo $field->name;?>"						
					>
						<?php echo ($field->description && $edithelp==3) ? '<div class="fc_mini_note_box">'.$field->description.'</div>' : ''; ?>
					
				<?php	if ($field->field_type=='maintext' && isset($this->item->item_translations) ) : ?>
					
					<!-- tabber start -->
					<div class="fctabber" style=''>
						<div class="tabbertab" style="padding: 0px;" >
							<h3> <?php echo '- '.$this->itemlang->name.' -'; // $t->name; ?> </h3>
							<?php
								$field_tab_labels = & $field->tab_labels;
								$field_html       = & $field->html;
								echo !is_array($field_html) ? $field_html : flexicontent_html::createFieldTabber( $field_html, $field_tab_labels, "");
							?>
						</div>
						<?php foreach ($this->item->item_translations as $t): ?>
							<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*') : ?>
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
			
				<!--/td-->
			<!--/tr-->
				
			<?php
			}
			?>
		<!--/table-->
	</fieldset>

<?php elseif ( $isnew ) : // new item, since administrator did not limit this, display message (user allowed to select item type) ?>
		<input name="jform[type_id_not_set]" value="1" type="hidden" />
		<div class="fc-info"><?php echo JText::_( 'FLEXI_CHOOSE_ITEM_TYPE' ); ?></div>
<?php else : // existing item that has no custom fields, warn the user ?>
		<div class="fc-error"><?php echo JText::_( 'FLEXI_NO_FIELDS_TO_TYPE' ); ?></div>
<?php	endif; ?>

	
<?php if ($typeid) : // hide items parameters (standard, extended, template) if content type is not selected ?>

	<?php echo JHtml::_('sliders.start','plugin-sliders-'.$this->item->id, array('useCookie'=>1)); ?>
	
	<?php
	// J2.5 requires Edit State privilege while J1.5 requires Edit privilege
	$publication_priv = FLEXI_J16GE ? 'canpublish' : 'canedit';
	?>
	
	<?php if ($this->perms[$publication_priv] && $this->params->get('usepublicationdetails_fe', 1)) : ?>
	
		<?php
			
			$title = JText::_( 'FLEXI_PUBLICATION_DETAILS' );
			echo JHtml::_('sliders.panel', $title, 'details-options');
		?>
		<fieldset class="panelform">
			<div class='fc_mini_note_box'>
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
				echo JText::sprintf( FLEXI_J16GE ? 'FLEXI_DATES_IN_USER_TIMEZONE_NOTE' : 'FLEXI_DATES_IN_SITE_TIMEZONE_NOTE', '<br>', $tz_info );
			?>
			</div>
		<ul class="adminformlist">
		<?php if ($this->perms['isSuperAdmin'] && $this->params->get('usepublicationdetails_fe', 1) == 2 ) : ?>
			<li><?php echo $this->form->getLabel('created_by')     . $this->form->getInput('created_by'); ?></li>
		<?php endif; ?>
		<?php if ($this->perms['editcreationdate'] && $this->params->get('usepublicationdetails_fe', 1) == 2 ) : ?>
			<li><?php echo $this->form->getLabel('created')        . $this->form->getInput('created');?></li>
		<?php endif; ?>
			<li><?php echo $this->form->getLabel('created_by_alias') . $this->form->getInput('created_by_alias');?></li>
			<li><?php echo $this->form->getLabel('publish_up')       . $this->form->getInput('publish_up');?></li>
			<li><?php echo $this->form->getLabel('publish_down')     . $this->form->getInput('publish_down');?></li>
			<li><?php echo $this->form->getLabel('access')           . $this->form->getInput('access');?></li>
		</ul>
		</fieldset>

	<?php endif; ?>
	
	
	<?php if ( $this->params->get('usemetadata_fe', 1) ) { ?>
	<?php
		$title = JText::_( 'FLEXI_METADATA_INFORMATION' );
		echo JHtml::_('sliders.panel', $title, "metadata-page");
	?>
	<fieldset class="panelform">
		<ul class="adminformlist">
			<li>
				<?php echo $this->form->getLabel('metadesc'); ?>
			
			<?php	if ( isset($this->item->item_translations) ) :?>
				
				<!-- tabber start -->
				<div class="fctabber" style='display:inline-block;'>
					<div class="tabbertab" style="padding: 0px;" >
						<h3> <?php echo '-'.$this->itemlang->name.'-'; // $t->name; ?> </h3>
						<?php echo $this->form->getInput('metadesc'); ?>
					</div>
					<?php foreach ($this->item->item_translations as $t): ?>
						<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*') : ?>
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
				<?php echo $this->form->getInput('metadesc'); ?>
			<?php endif; ?>
			
			</li>
			
			<li>
				<?php echo $this->form->getLabel('metakey'); ?>
			
			<?php	if ( isset($this->item->item_translations) ) :?>
			
				<!-- tabber start -->
				<div class="fctabber" style='display:inline-block;'>
					<div class="tabbertab" style="padding: 0px;" >
						<h3> <?php echo '-'.$this->itemlang->name.'-'; // $t->name; ?> </h3>
						<?php echo $this->form->getInput('metakey'); ?>
					</div>
					<?php foreach ($this->item->item_translations as $t): ?>
						<?php if ($this->itemlang->shortcode!=$t->shortcode && $t->shortcode!='*') : ?>
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
				<?php echo $this->form->getInput('metakey'); ?>
			<?php endif; ?>
			
			</li>
			
		<?php if ($this->params->get('usemetadata_fe', 1) == 2 ) : ?>
		
			<?php foreach($this->form->getGroup('metadata') as $field): ?>
				<?php if ($field->hidden): ?>
					<li style='display:none !important;'>
						<?php echo $field->input; ?>
					</li>
				<?php else: ?>
					<li>
						<?php echo $field->label; ?>
						<?php echo $field->input; ?>
					</li>
				<?php endif; ?>
			<?php endforeach; ?>
			
		<?php endif; ?>
		</ul>
	</fieldset>
	<?php } ?>
	
	
	<?php
		$useitemparams_fe = $this->params->get('useitemparams_fe');
		if ( empty($useitemparams_fe) ) {
			$useitemparams_fe = array();
		} else if ( !is_array($useitemparams_fe) ) {
			$useitemparams_fe = explode("|", $useitemparams_fe);
		}
		
	$fieldSets = $this->form->getFieldsets('attribs');
	foreach ($fieldSets as $name => $fieldSet) :
		$fieldsetname = str_replace("params-", "", $name);
		if ( !in_array($fieldsetname, $useitemparams_fe) ) continue;
		if ( $name=='themes' ) continue;
		
		$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_'.$name.'_FIELDSET_LABEL';
		echo JHtml::_('sliders.panel', JText::_('FLEXI_PARAMETERS') .": ". JText::_($label), $name.'-options');
		
		if ( $this->params->get('allowdisablingcomments_fe') && $fieldsetname=='advanced') {
			echo "This panel should be disabled, because a simple <b>comments disable parameter</b> is used instead";
			continue;
		}
	?>
		<fieldset class="panelform">
		<?php
			foreach ($this->form->getFieldset($name) as $field) :
				echo $field->label;
				echo $field->input;
			endforeach;
		?>
		</fieldset>
	<?php endforeach; ?>
	
	<?php
		// we need to close sliders to place some parameters outside sliders
		echo JHtml::_('sliders.end');
	?>
	
	<?php if ($this->perms['cantemplates'] && $this->params->get('selecttheme_fe')) : ?>
		
	<?php
		$type_default_layout = $this->tparams->get('ilayout');
		echo '<h3 class="themes-title">' . JText::_( 'FLEXI_PARAMETERS_LAYOUT_THEMES' ) . '</h3>';

		foreach ($this->form->getFieldset('themes') as $field) :
			if ($field->hidden) echo $field->input;
			else echo $field->label . $field->input;
			?><div class="clear"></div><?php
		endforeach;
		
	?>
	
	<blockquote id='__content_type_default_layout__'>
		<?php echo JText::sprintf( 'FLEXI_USING_CONTENT_TYPE_LAYOUT', $type_default_layout ); ?>
	</blockquote>
	
	<?php
		if ( $this->params->get('selecttheme_fe') == 2 ) :
			echo JHtml::_('sliders.start','template-sliders-'.$this->item->id, array('useCookie'=>1));
			foreach ($this->tmpls as $tmpl) :
				$title = JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name;
				echo JHtml::_('sliders.panel', $title,  $tmpl->name."-attribs-options");
				
				?> <fieldset class="panelform"> <?php
					foreach ($tmpl->params->getGroup('attribs') as $field) :
						echo $field->label;
						echo $field->input;
					endforeach;
				?> </fieldset> <?php
			endforeach;
			echo !FLEXI_J16GE ? $this->pane->endPane() : JHtml::_('sliders.end');
		endif;
	?>
	
	<?php endif; // end cantemplate and selecttheme_fe ?>

	
<?php	endif; // end of existing item ?>

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
			<input type="hidden" id="jformrules" name="jform[rules][]" value="" />
		<?php endif; ?>
		<?php if ( $isnew ) echo $this->submitConf; ?>
		
		<input type="hidden" name="unique_tmp_itemid" value="<?php echo JRequest::getVar( 'unique_tmp_itemid' );?>" />

	</form>
</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
