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

// Added to allow the user to choose some of the pre-selected categories
$cid = $this->params->get("cid");
$maincatid = $this->params->get("maincatid");
$postcats = $this->params->get("postcats", 0);
// Check user permission for submitting to multiple categories
if (!$this->perms['multicat']) {
	if ($postcats==2) $postcats = 1;
}

if ($cid) :
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
				$fixedcats .= '<input type="hidden" name="cid[]" value="'.$k.'" />';
			}
			$fixedmaincat = $globalcats[$maincatid]->title;
			$fixedmaincat .= '<input type="hidden" name="catid" value="'.$maincatid.'" />';
			break;
		case 1:  // submit to a single category, selecting from a MENU SPECIFIED categories subset
			$in_single_cat = true;
			$options[] = JHTML::_( 'select.option', '', '-- '.JText::_( 'FLEXI_SELECT_CAT' ).' --' );
			foreach ($cids_kv as $k => $v) {
				$options[] = JHTML::_('select.option', $k, $v );
			}
			$fixedcats = '';
			$fixedmaincat = JHTML::_('select.genericlist', $options, 'catid', ' class="required" ', 'value', 'text', $maincatid );
			break;
		case 2:  // submit to multiple categories, selecting from a MENU SPECIFIED categories subset
			$in_single_cat = false;
			foreach ($cids_kv as $k => $v) {
				$options[] = JHTML::_('select.option', $k, $v );
			}
			$fixedcats = JHTML::_('select.genericlist', $options, 'cid[]', 'multiple="multiple" size="6"', 'value', 'text', '' );
			array_unshift($options, JHTML::_( 'select.option', '', '-- '.JText::_( 'FLEXI_SELECT_CAT' ).' --' ) );
			$fixedmaincat = JHTML::_('select.genericlist', $options, 'catid', ' class="required" ', 'value', 'text', $maincatid );
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
$this->document->addScript( JURI::base().'administrator/components/com_flexicontent/assets/js/tabber-minimized.js');
$this->document->addStyleSheet('administrator/components/com_flexicontent/assets/css/tabber.css');

if (@$this->fields['tags'] && $this->perms['cantags']) {
	$this->document->addScript('administrator/components/com_flexicontent/assets/jquery-autocomplete/jquery.bgiframe.min.js');
	$this->document->addScript('administrator/components/com_flexicontent/assets/jquery-autocomplete/jquery.ajaxQueue.js');
	$this->document->addScript('administrator/components/com_flexicontent/assets/jquery-autocomplete/jquery.autocomplete.min.js');
	$this->document->addScript('administrator/components/com_flexicontent/assets/js/jquery.pager.js');
	
	$this->document->addStyleSheet('administrator/components/com_flexicontent/assets/jquery-autocomplete/jquery.autocomplete.css');
	$this->document->addStyleSheet('administrator/components/com_flexicontent/assets/css/Pager.css');
	$this->document->addScriptDeclaration("
		jQuery(document).ready(function () {
			jQuery(\"#input-tags\").autocomplete(\"".JURI::base()."index.php?option=com_flexicontent&view=items&task=viewtags&format=raw&".JUtility::getToken()."=1\", {
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
Window.onDomReady(function(){
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
	obj.innerHTML+="<li class=\"tagitem\"><span>"+name+"</span><input type='hidden' name='tag[]' value='"+id+"' /><a href=\"javascript:;\"  class=\"deletetag\" onclick=\"javascript:deleteTag(this);\" title=\"<?php echo JText::_( 'FLEXI_DELETE_TAG' ); ?>\"></a></li>";
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

<div id="flexicontent" class="adminForm flexi_edit">

    <?php if ($this->params->def( 'show_page_title', 1 )) : ?>
    <h1 class="componentheading">
        <?php echo $this->params->get('page_title'); ?>
    </h1>
    <?php endif; ?>

	<form action="<?php echo $this->action ?>" method="post" name="adminForm" id="adminForm" class="form-validate" enctype="multipart/form-data">
		<div class="flexi_buttons">
			<input type="button" class="button" onclick="javascript:submitbutton('save')" value="<?php echo JText::_( 'FLEXI_SAVE' ) ?>" />
			<button type="reset" class="button" onclick="javascript:submitbutton('cancel')">
				<?php echo JText::_( 'FLEXI_CANCEL' ) ?>
			</button>
		</div>
         
		<br class="clear" />
		
		<fieldset class="flexi_general">
			<legend><?php echo JText::_( 'FLEXI_GENERAL' ); ?></legend>
			<div class="flexi_formblock">
				<?php
					$field = $this->fields['title'];
					$field_tooltip = $field->description ? 'class="hasTip flexi_label" title="'.$field->label.'::'.$field->description.'"' : 'class="flexi_label"';
				?>
				<label for="title" <?php echo $field_tooltip; ?> >
					<?php echo $field->label.':'; ?>
					<?php /*echo JText::_( 'FLEXI_TITLE' ).':';*/ ?>
				</label>
				<input class="inputbox required" type="text" id="title" name="title" value="<?php echo $this->escape($this->item->title); ?>" size="65" maxlength="254" />
			</div>
			<?php /*
			<div class="flexi_formblock">
				<label for="alias" class="flexi_label" >
					<?php echo JText::_( 'FLEXI_ALIAS' ).':'; ?>
				</label>
				<input class="inputbox" type="text" id="alias" name="alias" value="<?php echo $this->item->alias; ?>" size="65" maxlength="254" />
			</div>
			*/ ?>
	<?php if ($cid) : /* MENU SPECIFIED categories subset */ ?>
		<?php if ($postcats!=1 && !$in_single_cat) : /* hide when submiting to single category, since we will only show primary category field */ ?>
			<div class="flexi_formblock">
				<label for="cid" class="flexi_label">
					<?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' ).':';?>
					<?php if ($postcats==2) : /* add "ctrl-click" tip when selecting multiple categories */ ?>
						<span class="editlinktip hasTip" title="<?php echo JText::_ ( 'FLEXI_NOTES' ); ?>::<?php echo JText::_ ( 'FLEXI_CATEGORIES_NOTES' );?>">
							<?php echo JHTML::image ( 'components/com_flexicontent/assets/images/icon-16-hint.png', JText::_ ( 'FLEXI_NOTES' ) ); ?>
						</span>
					<?php endif; ?>
				</label>
				<?php echo $fixedcats; ?>
			</div>
		<?php endif; ?>
		<div class="flexi_formblock">
			<label for="catid" class="flexi_label">
				<?php echo JText::_( $in_single_cat ? 'FLEXICONTENT_CATEGORY' : 'FLEXI_PRIMARY_CATEGORY' ).':';  /* when submitting to single category, call this field just 'CATEGORY' instead of 'PRIMARY CATEGORY' */ ?>
			</label>
			<?php echo $fixedmaincat; ?>
		</div>
	<?php else : ?>
		<?php if ($this->perms['multicat']) : ?>
			<div class="flexi_formblock">
				<label for="cid" class="flexi_label">
					<?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' ).':';?>
					<span class="editlinktip hasTip" title="<?php echo JText::_ ( 'FLEXI_NOTES' ); ?>::<?php echo JText::_ ( 'FLEXI_CATEGORIES_NOTES' );?>">
						<?php echo JHTML::image ( 'components/com_flexicontent/assets/images/icon-16-hint.png', JText::_ ( 'FLEXI_NOTES' ) ); ?>
					</span>
				</label>
				<?php echo $this->lists['cid']; ?>
			</div>
		<?php endif; ?>
		
			<div class="flexi_formblock">
				<label for="catid" class="flexi_label">
					<?php echo JText::_( (!$this->perms['multicat']) ? 'FLEXICONTENT_CATEGORY' : 'FLEXI_PRIMARY_CATEGORY' ).':';  /* if no multi category allowed for user, then call it just 'CATEGORY' instead of 'PRIMARY CATEGORY' */ ?>
				</label>
				<?php echo $this->lists['catid']; ?>
			</div>
	<?php endif; ?>

			<?php
			if ($autopublished = $this->params->get('autopublished', 0)) : 
			?>
				<input type="hidden" id="state" name="state" value="<?php echo $autopublished;?>" />
				<input type="hidden" id="vstate" name="vstate" value="2" />
			<?php 
			elseif ($this->perms['canpublish']) :
			?>
			<div class="flexi_formblock">
				<?php
					$field = $this->fields['state'];
					$field_tooltip = $field->description ? 'class="hasTip flexi_label" title="'.$field->label.'::'.$field->description.'"' : 'class="flexi_label"';
				?>
				<label for="title" <?php echo $field_tooltip; ?> >
					<?php echo $field->label.':'; ?>
					<?php /*echo JText::_( 'FLEXI_STATE' ).':';*/ ?>
				</label>
				<?php echo $this->lists['state']; ?>
			</div>
				<?php
				if (!$this->params->get('auto_approve', 1)) :
				?>
			<div class="flexi_formblock">
				<label for="vstate" class="flexi_label">
				<?php echo JText::_( 'FLEXI_APPROVE_VERSION' ).':';?>
				</label>
				<?php echo $this->lists['vstate']; ?>
			</div>
				<?php
				else :
				?>
				<input type="hidden" id="vstate" name="vstate" value="2" />
				<?php
				endif;
				?>
			<?php 
			else :
			?>
			<input type="hidden" id="state" name="state" value="<?php echo isset($this->item->state) ? $this->item->state : -4;?>" />
			<?php 
			endif;
			?>
		<?php if (FLEXI_FISH || FLEXI_J16GE) : ?>
			<div class="flexi_formblock">
				<label for="languages" class="flexi_label">
				<?php echo JText::_( 'FLEXI_LANGUAGE' ).':';?>
				</label>
				<?php echo $this->lists['languages']; ?>
			</div>
		<?php endif; ?>
		</fieldset>
		
		<?php
		if (FLEXI_ACCESS && $this->perms['canright']) :
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
						<div id="access"><?php echo $this->lists['access']; ?></div>
					</td>
				</tr>
			</table>
			<div id="notabacces">
			<?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT_DESC' ); ?>
			</div>
		</fieldset>
		<?php endif; ?>
<?php if(@$this->fields['tags']) {?>
	<fieldset class="flexi_tags">
		<legend><?php echo JText::_( 'FLEXI_TAGS' ); ?></legend>
				<div class="qf_tagbox" id="qf_tagbox">
					<ul id="ultagbox">
					<?php
						foreach( $this->tags as $tag ) {
							if(in_array($tag->id, $this->usedtags)) {
								if ($this->perms['cantags']) {
									echo '<li class="tagitem"><span>'.$tag->name.'</span>';
									echo '<input type="hidden" name="tag[]" value="'.$tag->id.'" /><a href="javascript:;" onclick="javascript:deleteTag(this);" class="deletetag" align="right" title="'.JText::_('FLEXI_DELETE_TAG').'"></a></li>';
								} else {
									echo '<li class="tagitem"><span>'.$tag->name.'</span>';
									echo '<input type="hidden" name="tag[]" value="'.$tag->id.'" /><a href="javascript:;" class="deletetag" align="right"></a></li>';
								}
							}
						}
					?>
					</ul>
					<br class="clear" />
				</div>
		<?php if ($this->perms['cantags']) : ?>
		<div id="tags">
		<label for="input-tags"><?php echo JText::_( 'FLEXI_ADD_TAG' ); ?>
			<input type="text" id="input-tags" name="tagname" tagid='0' tagname='' />
		</label>
		</div>
		<?php endif; ?>
	</fieldset>

<?php }
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
			$this->item->typename = $types[$this->item->type_id]['name'];
			echo $this->item->typename ? JText::_( 'FLEXI_ITEM_TYPE' ) . ' : ' . $this->item->typename : JText::_( 'FLEXI_TYPE_NOT_DEFINED' ); ?>
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
					$maintext = $this->item->text;
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
	<?php
	} else if ($this->item->id == 0) {
	?>
		<div class="fc-info"><?php echo JText::_( 'FLEXI_CHOOSE_ITEM_TYPE' ); ?></div>
	<?php
	} else {
	?>
		<div class="fc-error"><?php echo JText::_( 'FLEXI_NO_FIELDS_TO_TYPE' ); ?></div>
	<?php
	}
	?>

	<?php
		echo "<br/ >";
		echo $this->pane->startPane( 'det-pane' );
		if($this->params->get('usemetadata', 1)) {
			$title = JText::_( 'FLEXI_METADATA_INFORMATION' );
			echo $this->pane->startPanel( $title, "metadata-page" );
			echo $this->formparams->render('meta', 'metadata');
			echo $this->pane->endPanel();
		} else {
			?>
			<input type="hidden" name="metadesc" value="<?php echo @$this->item->metadesc; ?>" />
			<input type="hidden" name="metakey" value="<?php echo @$this->item->metakey; ?>" />
			<?php
		}
	?>
	<?php if ($this->perms['canparams'] || $this->perms['isSuperAdmin']) : ?>
	<?php

		if ($this->perms['isSuperAdmin']) {
			$title = JText::_( 'FLEXI_DETAILS' );
			echo $this->pane->startPanel( $title, 'details' );
			echo $this->formparams->render('details');
			echo $this->pane->endPanel();
		}
		
		if ($this->perms['cantemplates']) {
			$title = JText::_( 'FLEXI_PARAMETERS_STANDARD' );
			echo $this->pane->startPanel( $title, "params-page" );
			echo $this->formparams->render('params', 'advanced');
			echo $this->pane->endPanel();
	
			echo '<h3 class="themes-title">' . JText::_( 'FLEXI_PARAMETERS_THEMES' ) . '</h3>';
			foreach ($this->tmpls as $tmpl) {
				$title = JText::_( 'FLEXI_PARAMETERS_SPECIFIC' ) . ' : ' . $tmpl->name;
				echo $this->pane->startPanel( $title, "params-".$tmpl->name );
				echo $tmpl->params->render();
				echo $this->pane->endPanel();
			}
		}
		
		?>
	<?php endif; ?>
	<?php 
		echo $this->pane->endPane();
	?>

		<br class="clear" />
		<?php echo JHTML::_( 'form.token' ); ?>
		<input type="hidden" name="created" value="<?php echo $this->item->created; ?>" />
		<input type="hidden" name="task" id="task" value="" />
		<input type="hidden" name="option" value="com_flexicontent" />
		<input type="hidden" name="referer" value="<?php echo str_replace(array('"', '<', '>', "'"), '', @$_SERVER['HTTP_REFERER']); ?>" />
		<input type="hidden" name="created_by" value="<?php echo $this->item->created_by; ?>" />
		<input type="hidden" name="id" value="<?php echo $this->item->id; ?>" />
		<input type="hidden" name="views" value="items" />

	</form>
</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
