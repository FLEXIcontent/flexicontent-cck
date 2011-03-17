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
$postcats = $this->params->get("postcats", 0);
if ($cid) :
	global $globalcats;
	$cids 		= explode(",", $cid);
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
				$fixedcats .= '<input type="hidden" name="cid[]" value="'.$k.'" />';
			}
			break;
		case 1:
			foreach ($cids_kv as $k => $v) {
				$options[] = JHTML::_('select.option', $k, $v );
			}
			$fixedcats = JHTML::_('select.genericlist', $options, 'cid[]', '', 'value', 'text', '' );
			break;
		case 2:
			foreach ($cids_kv as $k => $v) {
				$options[] = JHTML::_('select.option', $k, $v );
			}
			$fixedcats = JHTML::_('select.genericlist', $options, 'cid[]', 'multiple="multiple" size="6"', 'value', 'text', '' );
			break;
	}
endif;

JHTML::_('behavior.mootools');
$this->document->addScript('administrator/components/com_flexicontent/assets/js/jquery-1.4.min.js');
$this->document->addCustomTag('<script>jQuery.noConflict();</script>');
// add extra css for the edit form
if ($this->params->get('form_extra_css')) {
	$this->document->addStyleDeclaration($this->params->get('form_extra_css'));
}
$this->document->addStyleSheet('administrator/components/com_flexicontent/assets/css/flexicontentbackend.css');
$this->document->addScript( JURI::base().'administrator/components/com_flexicontent/assets/js/itemscreen.js' );
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
	obj.innerHTML+="<li class=\"tagitem\"><span>"+name+"</span><input type='hidden' name='tag[]' value='"+id+"' /><a href=\"#\"  class=\"deletetag\" onclick=\"javascript:deleteTag(this);\" title=\"<?php echo JText::_( 'FLEXI_DELETE_TAG' ); ?>\"></a></li>";
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
		return;
	}

	var form = document.adminForm;
	var validator = document.formvalidator;
	var title = $(form.title).getValue();
	title.replace(/\s/g,'');

	if ( title.length==0 ) {
		//alert("<?php echo JText::_( 'FLEXI_ADD_TITLE', true ); ?>");
		validator.handleResponse(false,form.title);
		var invalid = $$('.invalid');
		new Fx.Scroll(window).toElement(invalid[0]);
		invalid[0].focus();
			//form.title.focus();
			return false;
	}<?php if(!$cid) {?> else if ( form.cid.selectedIndex == -1 ) {
		//alert("<?php echo JText::_( 'FLEXI_SELECT_CATEGORY', true ); ?>");
		validator.handleResponse(false,form.cid);
		var invalid = $$('.invalid');
		new Fx.Scroll(window).toElement(invalid[0]);
		invalid[0].focus();
		return false;
	} <?php } ?>else {
	<?php if (!$this->tparams->get('hide_html', 0) && !$this->tparams->get('hide_maintext')) echo $this->editor->save( 'text' ); ?>
	submitform(pressbutton);
	return true;
	}
}

function deleteTag(obj) {
	if (navigator.appVersion.indexOf("MSIE") == -1) {
		var parent = $($(obj).getParent());
		parent.remove();
	} else {
		var parent = obj.parentNode;
		parent.innerHTML = "";
		parent.removeNode(true);
	}
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
            <button type="button" class="button" onclick="return submitbutton('save')">
        	    <?php echo JText::_( 'FLEXI_SAVE' ) ?>
        	</button>
        	<button type="reset" class="button" onclick="submitbutton('cancel')">
        	    <?php echo JText::_( 'FLEXI_CANCEL' ) ?>
        	</button>
        </div>
         
        <br class="clear" />
	
        <fieldset class="flexi_general">
			<legend><?php echo JText::_( 'FLEXI_GENERAL' ); ?></legend>
			<div class="flexi_formblock">
				<label for="title" class="flexi_label">
				<?php echo JText::_( 'FLEXI_TITLE' ).':'; ?>
				</label>
				<input class="inputbox required" type="text" id="title" name="title" value="<?php echo $this->escape($this->item->title); ?>" size="65" maxlength="254" />
			</div>
			<div class="flexi_formblock">
<?php if ($cid) : ?>
				<label for="cid" class="flexi_label">
					<?php echo JText::_( 'FLEXI_CATEGORIES' ).':';?>
				</label>
				<?php echo $fixedcats; ?>
<?php else : ?>
				<label for="cid" class="flexi_label">
					<?php echo JText::_( 'FLEXI_CATEGORIES' ).':';?>
					<?php if ($this->perms['multicat']) : ?>
					<span class="editlinktip hasTip" title="<?php echo JText::_ ( 'FLEXI_NOTES' ); ?>::<?php echo JText::_ ( 'FLEXI_CATEGORIES_NOTES' );?>">
						<?php echo JHTML::image ( 'components/com_flexicontent/assets/images/icon-16-hint.png', JText::_ ( 'FLEXI_NOTES' ) ); ?>
					</span>
					<?php endif; ?>
				</label>
          		<?php echo $this->lists['cid']; ?>
<?php endif; ?>
			</div>

			<?php
			if ($autopublished = $this->params->get('autopublished', 0)) : 
			?>
				<input type="hidden" id="state" name="state" value="<?php echo $autopublished;?>" />
				<input type="hidden" id="vstate" name="vstate" value="2" />
			<?php 
			elseif ($this->perms['canpublish']) :
			?>
			<div class="flexi_formblock">
          		<label for="state" class="flexi_label">
				<?php echo JText::_( 'FLEXI_STATE' ).':';?>
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
			if (FLEXI_FISH) :
			?>
			<div class="flexi_formblock">
          		<label for="languages" class="flexi_label">
				<?php echo JText::_( 'FLEXI_LANGUAGE' ).':';?>
				</label>
          		<?php echo $this->lists['languages']; ?>
			</div>
			<?php 
			endif; 
			?>
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
				if(in_array($tag->id, $this->used)) {
					if ($this->perms['cantags']) {
						echo '<li class="tagitem"><span>'.$tag->name.'</span>';
						echo '<input type="hidden" name="tag[]" value="'.$tag->id.'" /><a href="#" onclick="javascript:deleteTag(this);" class="deletetag" align="right" title="'.JText::_('FLEXI_DELETE_TAG').'"></a></li>';
					} else {
						echo '<li class="tagitem"><span>'.$tag->name.'</span>';
						echo '<input type="hidden" name="tag[]" value="'.$tag->id.'" /><a href="#" class="deletetag" align="right"></a></li>';
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
			foreach ($this->fields as $field) {
				// used to hide the core fields from this listing
				if ( (!$field->iscore || ($field->field_type == 'maintext' && (!$this->tparams->get('hide_maintext')))) && !$field->parameters->get('backend_hidden') ) {
				// set the specific label for the maintext field
					if ($field->field_type == 'maintext')
					{
						$field->label = $this->tparams->get('maintext_label', $field->label);
						$field->description = $this->tparams->get('maintext_desc', $field->description);
						//$maintext = ($this->version!=$this->item->version)?@$field->value[0]:$this->item->text;
						$maintext = $this->item->text;
						if ($this->tparams->get('hide_html', 0))
						{
							$field->html = '<textarea name="text" rows="20" cols="75">'.$maintext.'</textarea>';
						} else {
							$height = $this->tparams->get('height', 400);
							$field->html = $this->editor->display( 'text', $maintext, '100%', $height, '75', '20', array('pagebreak') ) ;
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

	<?php if ($this->perms['canparams']) : ?>
	<?php if($this->params->get('usemetadata', 1)) {?>
    	<fieldset class="flexi_meta">
       	<legend><?php echo JText::_( 'FLEXI_METADATA_INFORMATION' ); ?></legend>

            <div class="flexi_box_left">
              	<label for="metadesc"><?php echo JText::_( 'FLEXI_META_DESCRIPTION' ); ?></label>
          		<textarea class="inputbox" cols="20" rows="5" name="metadesc" id="metadesc" style="width:100%;"><?php echo $this->item->metadesc; ?></textarea>
            </div>

            <div class="flexi_box_right">
        		<label for="metakey"><?php echo JText::_( 'FLEXI_META_KEYWORDS' ); ?></label>
        		<textarea class="inputbox" cols="20" rows="5" name="metakey" id="metakey" style="width:100%;"><?php echo $this->item->metakey; ?></textarea>
            </div>
      	</fieldset>
		<?php }else{?>
			<input type="hidden" name="metadesc" value="<?php echo @$this->item->metadesc; ?>" />
			<input type="hidden" name="metakey" value="<?php echo @$this->item->metakey; ?>" />
		<?php }?>
		<?php endif; ?>

		<br class="clear" />
        
		<input type="hidden" name="created" value="<?php echo $this->item->created; ?>" />
		<input type="hidden" name="created_by" value="<?php echo $this->item->created_by; ?>" />
		<input type="hidden" name="id" value="<?php echo $this->item->id; ?>" />
    	<input type="hidden" name="referer" value="<?php echo str_replace(array('"', '<', '>', "'"), '', @$_SERVER['HTTP_REFERER']); ?>" />
    	<?php echo JHTML::_( 'form.token' ); ?>
    	<input type="hidden" name="task" value="" />
		<input type="hidden" name="option" value="com_flexicontent" />
		<input type="hidden" name="views" value="items" />

	</form>
</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
