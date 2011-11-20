<?php
/**
 * @version 1.5 stable $Id: form.php 353 2010-06-29 11:54:33Z emmanuel.danan $
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
$cparams =& JComponentHelper::getParams( 'com_flexicontent' );

// Added to allow the user to choose some of the pre-selected categories
$cids = $this->params->get("cid");  // categories FIELD is READONLY ?
$postcats = $this->params->get("postcats", 0);

if ($cids) :
	global $globalcats;
	//$cids 		= explode(",", $cids);
	$cids_kv 	= array();
	$options 	= array();
	foreach ($cids as $cat) {
		$cids_kv[$cat] = $globalcats[$cat]->title;
	}
	
	switch($postcats) {
		case 0:
		default:
			$fixedcats = implode(', ', $cids_kv);
			foreach ($cids_kv as $k => $v) {
				$fixedcats .= '<input type="hidden" name="jform[cid][]" value="'.$k.'" />';
			}
			break;
		case 1:
			foreach ($cids_kv as $k => $v) {
				$options[] = JHTML::_('select.option', $k, $v );
			}
			$fixedcats = JHTML::_('select.genericlist', $options, 'jform[cid][]', '', 'value', 'text', '' );
			break;
		case 2:
			foreach ($cids_kv as $k => $v) {
				$options[] = JHTML::_('select.option', $k, $v );
			}
			$fixedcats = JHTML::_('select.genericlist', $options, 'jform[cid][]', 'multiple="multiple" size="6"', 'value', 'text', '' );
			break;
	}
endif;

if(!JPluginHelper::isEnabled('system', 'jquerysupport')) {
	JHTML::_('behavior.mootools');
	$this->document->addScript('administrator/components/com_flexicontent/assets/js/jquery-1.4.4.min.js');
	$this->document->addCustomTag('<script>jQuery.noConflict();</script>');    // ALREADY include in above file, but done again
}
// add extra css for the edit form
if ($this->params->get('form_extra_css')) {
	$this->document->addStyleDeclaration($this->params->get('form_extra_css'));
}
$this->document->addStyleSheet('administrator/components/com_flexicontent/assets/css/flexicontentbackend.css');
$this->document->addScript( JURI::base().'administrator/components/com_flexicontent/assets/js/itemscreen.js' );
$this->document->addScript( JURI::base().'administrator/components/com_flexicontent/assets/js/admin.js' );
$this->document->addScript( JURI::base().'administrator/components/com_flexicontent/assets/js/validate.js' );

if ($this->perms['canusetags']) {
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

function submitbutton( pressbutton ) {
	if (pressbutton == 'cancel') {
		submitform( pressbutton );
		//return false;
	}
	var form = document.adminForm;
	var validator = document.formvalidator;
	var title = form.jform_title.value;
	title.replace(/\s/g,'');
	if ( title.length==0 ) {
		//alert("<?php echo JText::_( 'FLEXI_ADD_TITLE', true ); ?>");
		validator.handleResponse(false,form.jform_title);
		var invalid = $$('.invalid');
		new Fx.Scroll(window).toElement(invalid[0]);
		invalid[0].focus();
		//form.title.focus();
		return false;
	}<?php if(!$cids) {?> else if ( form.jformcid.selectedIndex == -1 ) {   // categories FIELD is READONLY ?
		//alert("<?php echo JText::_( 'FLEXI_SELECT_CATEGORY', true ); ?>");
		validator.handleResponse(false,form.jformcid);
		var invalid = $$('.invalid');
		new Fx.Scroll(window).toElement(invalid[0]);
		invalid[0].focus();
		return false;
	} <?php } ?>else {
	<?php if (!$this->tparams->get('hide_html', 0) && !$this->tparams->get('hide_maintext')) {$editor = & JFactory::getEditor();echo $editor->save( 'jform_text' );} ?>
	submitform(pressbutton);
	//return true;
	}
	return false;
}

function deleteTag(obj) {
	parent = $($(obj).getParent());
	jQuery(parent).remove();
}
</script>

<div id="flexicontent" class="adminForm flexi_edit">

    <?php if ($this->params->def( 'show_page_title', 1 )) : ?>
    <h1 class="componentheading">
        <?php echo $this->params->get('page_title'); ?>
    </h1>
    <?php endif; ?>

	<form action="<?php echo $this->action ?>" method="post" name="adminForm" enctype="multipart/form-data">
		<div class="flexi_buttons">
            <button type="submit" class="button" onclick="return submitbutton('save')">
        	    <?php echo JText::_( 'FLEXI_SAVE' ) ?>
        	</button>
        	<button type="reset" class="button" onclick="submitbutton('cancel')">
        	    <?php echo JText::_( 'FLEXI_CANCEL' ) ?>
        	</button>
        </div>
         
        <br class="clear" />
	
        <table cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td>
				<?php echo $this->item->getLabel('title');?>
			</td>
			<td>
				<?php echo $this->item->getInput('title');?>
				<!-- <input type="text" name="jform[title]" id="jform_title" value="<?php echo $this->escape($this->item->getValue('title')); ?>" class="inputbox required" size="55" maxlength="254" /> -->
			</td>
		</tr>
		<tr>
			<td>
				<?php echo $this->item->getLabel('alias');?>
			</td>
			<td>
				<?php echo $this->item->getInput('alias');?>
			</td>
		</tr>
		
<?php if ($cids) : ?>
		<tr>
			<td>
				<label for="cid" class="flexi_label">
					<?php echo JText::_( 'FLEXI_CATEGORIES' ).':';?>
				</label>
			</td>
			<td>
				<?php echo $fixedcats; ?>
			</td>
		</tr>
<?php else : ?>
		<tr>
			<td>
				<label for="cid" class="flexi_label">
					<?php echo JText::_( 'FLEXI_CATEGORIES' ).':';?>
					<?php if ($this->perms['multicat']) : ?>
					<span class="editlinktip hasTip" title="<?php echo JText::_ ( 'FLEXI_NOTES' ); ?>::<?php echo JText::_ ( 'FLEXI_CATEGORIES_NOTES' );?>">
						<?php echo JHTML::image ( 'components/com_flexicontent/assets/images/icon-16-hint.png', JText::_ ( 'FLEXI_NOTES' ) ); ?>
					</span>
					<?php endif; ?>
				</label>
			</td>
			<td>
          		<?php echo $this->lists['cid']; ?>
			</td>
		</tr>
<?php endif; ?>

<?php

$autopublished = $this->params->get('autopublished', 0);  // Menu Item Parameter
$canpublish = $this->perms['canpublish'];
$autoapprove = $cparams->get('auto_approve', 0);
//echo "Item Permissions:<br>\n<pre>"; print_r($this->perms); echo "</pre>";
//echo "Auto-Publish Parameter: $autopublished<br />";
//echo "Auto-Approve Parameter: $autoapprove<br />";
?>
	<?php if (!$autopublished && $canpublish) : ?>
	
		<tr>
			<td>
				<?php echo $this->item->getLabel('state').':';?>
			</td>
			<td>
	  		<?php echo $this->item->getInput('state');//echo $this->lists['state']; ?>
	  		<?php	if ($cparams->get('auto_approve', 0)) : ?>
	  			<input type="hidden" id="vstate" name="jform[vstate]" value="2" />
	  		<?php	endif;?>
			</td>
		</tr>
		
		<?php	if (!$cparams->get('auto_approve', 0)) :	?>
		<tr>
			<td>
	 			<label for="vstate" class="flexi_label">
				<?php echo JText::_( 'FLEXI_APPROVE_VERSION' ).':';?>
				</label>
			</td>
			<td>
	  		<?php echo $this->lists['vstate']; ?>
			</td>
		</tr>
		<?php	endif; ?>
		
	<?php elseif (!$autopublished && !$canpublish) : ?>
	
		<tr>
			<td>
				<?php echo $this->item->getLabel('state').':';?>
			</td>
			<td>
	  		<?php //echo JText::_( 'FLEXI_NEEDS_APPROVAL' ).':';?>
	  		<?php echo 'You cannot set state of this item, it will be reviewed by administrator'; ?>
				<input type="hidden" id="state" name="jform[state]" value="<?php echo $this->item->getValue('state', -4);?>" />
				<input type="hidden" id="vstate" name="jform[vstate]" value="1" />
			</td>
		</tr>
		
	<?php endif; ?>
	
		<tr>
			<td>
		  		<?php echo $this->item->getLabel('language'); ?>
			</td>
			<td>
		 		<?php echo $this->item->getInput('language');?>
			</td>
		</tr>
		<?php 
		if ($this->perms['canconfig']) :
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
        	</tr>
		<?php endif; ?>
		<tr>
			<td>
				<label><?php echo JText::_( 'FLEXI_TAGS' ); ?></label>
			</td>
			<td>
				<div class="qf_tagbox" id="qf_tagbox">
				<ul id="ultagbox">
				<?php
					foreach($this->usedtags as $tag) {
						if ($this->perms['canusetags']) {
							echo '<li class="tagitem"><span>'.$tag->name.'</span>';
							echo '<input type="hidden" name="jform[tag][]" value="'.$tag->id.'" /><a href="javascript:;" onclick="javascript:deleteTag(this);" class="deletetag" align="right" title="'.JText::_('FLEXI_DELETE_TAG').'"></a></li>';
						} else {
							echo '<li class="tagitem"><span>'.$tag->name.'</span>';
							echo '<input type="hidden" name="jform[tag][]" value="'.$tag->id.'" /><a href="javascript:;" class="deletetag" align="right"></a></li>';
						}
					}
					?>
					</ul>
					<br class="clear" />
				</div>
			</td>
		</tr>
		<?php if ($this->perms['canusetags']) : ?>
		<tr>
			<td>
				<label for="input-tags"><?php echo JText::_( 'FLEXI_ADD_TAG' ); ?></label>
			</td>
			<td>
				<div id="tags">
					<input type="text" id="input-tags" name="tagname" tagid='0' tagname='' />
				</div>
			</td>
		</tr>
		<?php endif; ?>
	</table>
	<?php
	if ($this->fields) {
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
			$typename = $types[$this->item->getValue('type_id')]['name'];
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
				// used to hide the core fields and the hidden fields from this listing
				if 	(
						(!$field->iscore || ($field->field_type == 'maintext' && (!$this->tparams->get('hide_maintext')))) 
						&& 
						(!$field->parameters->get('backend_hidden') && !in_array($field->field_type, $hidden)) 
					) 
				{
				// set the specific label for the maintext field
					if ($field->field_type == 'maintext')
					{
						$field->label = $this->tparams->get('maintext_label', $field->label);
						$field->description = $this->tparams->get('maintext_desc', $field->description);
						//$maintext = ($this->version!=$this->item->version)?@$field->value[0]:$this->item->text;
						//$maintext = $this->item->getValue('text');
						$maintext = @$field->value[0];
						if ($this->tparams->get('hide_html', 0))
						{
							$field->html = '<textarea name="jform[text]" rows="20" cols="75">'.$maintext.'</textarea>';
						} else {
							$height = $this->tparams->get('height', 400);
							$editor = & JFactory::getEditor();
							$field->html = $editor->display( 'jform[text]', $maintext, '100%', $height, '75', '20', array('pagebreak'), 'jform_text' ) ;
						}
					}
			?>
			<tr>
				<td class="key">
					<label for="<?php echo $field->name; ?>" class="hasTip" title="<?php echo $field->label; ?>::<?php echo $field->description; ?>">
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
			<?php
				}
			}
			?>
		</table>
	</fieldset>
	<?php
	} else if ($this->item->getValue('id') == 0) {
	?>
		<div class="fc-info"><?php echo JText::_( 'FLEXI_CHOOSE_ITEM_TYPE' ); ?></div>
	<?php
	} else {
	?>
		<div class="fc-error"><?php echo JText::_( 'FLEXI_NO_FIELDS_TO_TYPE' ); ?></div>
	<?php
	}
	?>

	<?php if ($this->perms['canparams'] && $this->params->get('usemetadata', 1)) { ?>
	<?php echo JHtml::_('sliders.start','plugin-sliders-'.$this->item->getValue("id"), array('useCookie'=>1)); ?>
	<?php
		echo JHtml::_('sliders.panel',JText::_('FLEXI_METADATA_INFORMATION'), "metadata-page");
		//echo JHtml::_('sliders.panel',JText::_('FLEXI_PARAMETERS_STANDARD'), "params-page");
		?>
		<fieldset class="panelform">
			<table>
			<tr>
				<td>
				<?php echo $this->item->getLabel('metadesc'); ?>
				</td>
				<td>
				<?php echo $this->item->getInput('metadesc'); ?>
				</td>
			</tr>
			<tr>
				<td>
				<?php echo $this->item->getLabel('metakey'); ?>
				</td>
				<td>
				<?php echo $this->item->getInput('metakey'); ?>
				</td>
			</tr>
			<?php foreach($this->item->getGroup('metadata') as $field): ?>
				<tr>
				<?php if ($field->hidden): ?>
					<td colspan="2">
					<?php echo $field->input; ?>
					</td>
				<?php else: ?>
					<td>
					<?php echo $field->label; ?>
					</td>
					<td>
					<?php echo $field->input; ?>
					</td>
				<?php endif; ?>
				</tr>
			<?php endforeach; ?>
			</table>
		</fieldset>
		<?php echo JHtml::_('sliders.end'); ?>
	<?php }else{?>
		<input type="hidden" name="jform[metadata][metadesc]" value="<?php echo @$this->item->getValue('metadesc'); ?>" />
		<input type="hidden" name="jform[metadata][metakey]" value="<?php echo @$this->item->getValue('metakey'); ?>" />
		<?php foreach($this->item->getGroup('metadata') as $field): ?>
			<input type="hidden" name="<?php echo $field->name;?>" value="<?php echo @$field->value; ?>" />
		<?php endforeach; ?>
	<?php } ?>
	<?php if($this->perms['canparams']) {?>
		<?php echo JHtml::_('sliders.start','plugin-sliders-'.$this->item->getValue("id"), array('useCookie'=>1)); ?>
		<?php
		echo JHtml::_('sliders.panel',JText::_('FLEXI_DETAILS'), 'details-options');
		?>
		<fieldset class="panelform">
		<ul class="adminformlist">
			<li><?php echo $this->item->getLabel('access');?>
			<?php echo $this->item->getInput('access');?></li>
			<li><?php echo $this->item->getLabel('created_by');?>
			<?php echo $this->item->getInput('created_by');?></li>
			<li><?php echo $this->item->getLabel('created_by_alias');?>
			<?php echo $this->item->getInput('created_by_alias');?></li>
			<li><?php echo $this->item->getLabel('created');?>
			<?php echo $this->item->getInput('created');?></li>
			<li><?php echo $this->item->getLabel('publish_up');?>
			<?php echo $this->item->getInput('publish_up');?></li>
			<li><?php echo $this->item->getLabel('publish_down');?>
			<?php echo $this->item->getInput('publish_down');?></li>
		</ul>
		</fieldset>

		<?php
		/* echo JHtml::_('sliders.panel',JText::_('FLEXI_METADATA_INFORMATION'), "metadata-page");
		//echo JHtml::_('sliders.panel',JText::_('FLEXI_PARAMETERS_STANDARD'), "params-page");
		?>
		<fieldset class="panelform">
			<?php echo $this->item->getLabel('metadesc'); ?>
			<?php echo $this->item->getInput('metadesc'); ?>

			<?php echo $this->item->getLabel('metakey'); ?>
			<?php echo $this->item->getInput('metakey'); ?>
			<?php foreach($this->item->getGroup('metadata') as $field): ?>
				<?php if ($field->hidden): ?>
					<?php echo $field->input; ?>
				<?php else: ?>
					<?php echo $field->label; ?>
					<?php echo $field->input; ?>
				<?php endif; ?>
			<?php endforeach; ?>
		</fieldset>
		<?php */?>
		<?php
		$fieldSets = $this->item->getFieldsets('attribs');
		foreach ($fieldSets as $name => $fieldSet) :
			$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_'.$name.'_FIELDSET_LABEL';
			echo JHtml::_('sliders.panel',JText::_($label), $name.'-options');
			?>
			<fieldset class="panelform">
				<?php foreach ($this->item->getFieldset($name) as $field) : ?>
					<?php echo $field->label; ?>
					<?php echo $field->input; ?>
				<?php endforeach; ?>
			</fieldset>
		<?php endforeach;
		?>

		<?php
		echo '<h3 class="themes-title">' . JText::_( 'FLEXI_PARAMETERS_THEMES' ) . '</h3>';
		foreach ($this->tmpls as $tmpl) {
			$title = JText::_( 'FLEXI_PARAMETERS_SPECIFIC' ) . ' : ' . $tmpl->name;
			echo JHtml::_('sliders.panel',JText::_($title),  $tmpl->name."-attribs-options");
			?>
			<fieldset class="panelform">
				<?php foreach ($tmpl->params->getGroup('attribs') as $field) : ?>
					<?php echo $field->label; ?>
					<?php echo $field->input; ?>
				<?php endforeach; ?>
			</fieldset>
			<?php
		}
		?>
		<?php echo JHtml::_('sliders.end'); ?>
	<?php } ?>

		<br class="clear" />
		<?php echo JHTML::_( 'form.token' ); ?>
		<input type="hidden" name="return" value="<?php echo @$this->return_page;?>" />
		<input type="hidden" name="task" id="task" value="" />
		<input type="hidden" name="option" value="com_flexicontent" />
		<input type="hidden" name="referer" value="<?php echo str_replace(array('"', '<', '>', "'"), '', @$_SERVER['HTTP_REFERER']); ?>" />
		<?php echo $this->item->getInput('id');?>
		<input type="hidden" name="jform[type_id]" value="<?php echo $this->item->getValue('type_id'); ?>" />
		<?php
			if ($autopublished) :
		?>
				<input type="hidden" id="state" name="jform[state]" value="<?php echo $autopublished;?>" />
				<input type="hidden" id="vstate" name="jform[vstate]" value="2" />
		<?php
			endif;
			if (!$this->perms['canconfig']) {
		?>
				<input type="hidden" id="jformrules" name="jform[rules][]" value="" />
		<?php
			}
		?>
	</form>
</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
