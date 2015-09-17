<?php
/**
 * @version 1.5 stable $Id: default.php 1904 2014-05-20 12:21:09Z ggppdk $
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

$task_items = 'task=items.';
$ctrl_items = 'items.';
$tags_task  = 'task=tags.';

// For tabsets/tabs ids (focusing, etc)
$tabSetCnt = -1;
$tabSetMax = -1;
$tabCnt = array();
$tabSetStack = array();

$useAssocs = flexicontent_db::useAssociations();
$tags_displayed = $this->row->type_id && ( $this->perms['cantags'] || count(@$this->usedtags) ) ;

$close_btn = FLEXI_J30GE ? '<a class="close" data-dismiss="alert">&#215;</a>' : '<a class="fc-close" onclick="this.parentNode.parentNode.removeChild(this.parentNode);">&#215;</a>';
$alert_box = FLEXI_J30GE ? '<div %s class="alert alert-%s %s">'.$close_btn.'%s</div>' : '<div %s class="fc-mssg fc-%s %s">'.$close_btn.'%s</div>';
$btn_class = FLEXI_J30GE ? 'btn' : 'fc_button';
$tip_class = FLEXI_J30GE ? ' hasTooltip' : ' hasTip';
$noplugin = '<div class="fc-mssg fc-warning">'. JText::_( 'FLEXI_PLEASE_PUBLISH_PLUGIN' ) .'</div>';

// add extra css/js for the edit form
if ($this->params->get('form_extra_css'))    $this->document->addStyleDeclaration($this->params->get('form_extra_css'));
if ($this->params->get('form_extra_css_be')) $this->document->addStyleDeclaration($this->params->get('form_extra_css_be'));
if ($this->params->get('form_extra_js'))     $this->document->addScriptDeclaration($this->params->get('form_extra_js'));
if ($this->params->get('form_extra_js_be'))  $this->document->addScriptDeclaration($this->params->get('form_extra_js_be'));

// Load JS tabber lib
$this->document->addScript(JURI::root(true).'/components/com_flexicontent/assets/js/tabber-minimized.js');
$this->document->addStyleSheet(JURI::root(true).'/components/com_flexicontent/assets/css/tabber.css');
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs

if ($this->perms['cantags'] || $this->perms['canversion']) {
	$this->document->addScript(JURI::root(true).'/components/com_flexicontent/librairies/jquery-autocomplete/jquery.bgiframe.min.js');
	$this->document->addScript(JURI::root(true).'/components/com_flexicontent/librairies/jquery-autocomplete/jquery.ajaxQueue.js');
	$this->document->addScript(JURI::root(true).'/components/com_flexicontent/librairies/jquery-autocomplete/jquery.autocomplete.min.js');
	$this->document->addScript(JURI::root(true).'/components/com_flexicontent/assets/js/jquery.pager.js');     // e.g. pagination for item versions
	$this->document->addScript(JURI::root(true).'/components/com_flexicontent/assets/js/jquery.autogrow.js');  // e.g. autogrow version comment textarea

	$this->document->addStyleSheet(JURI::root(true).'/components/com_flexicontent/librairies/jquery-autocomplete/jquery.autocomplete.css');
	$this->document->addScriptDeclaration("
		jQuery(document).ready(function () {
			jQuery('#input-tags').autocomplete('".JURI::base(true)."/index.php?option=com_flexicontent&".$task_items."viewtags&format=raw&".JSession::getFormToken()."=1', {
				width: 260,
				max: 100,
				matchContains: false,
				mustMatch: false,
				selectFirst: false,
				dataType: 'json',
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
				jQuery('#input-tags').attr('data-tagid',row.id);
				jQuery('#input-tags').attr('data-tagname',row.name);
				addToList(row.id, row.name);
			}).keydown(function(event) {
				if((event.keyCode==13)&&(jQuery('#input-tags').attr('data-tagid')=='0') ) {//press enter button
					addtag(0, jQuery('#input-tags').attr('value'));
					resetField();
					return false;
				}else if(event.keyCode==13) {
					resetField();
					return false;
				}
			});
			function resetField() {
				jQuery('#input-tags').attr('data-tagid',0);
				jQuery('#input-tags').attr('data-tagname','');
				jQuery('#input-tags').attr('value','');
			}
		});
		
		jQuery(document).ready(function() {
			// For the initially displayed versions page:  Add onclick event that opens compare in popup 
			jQuery('a.modal-versions').each(function(index, value) {
				jQuery(this).on('click', function() {
					// Load given URL in an popup dialog
					var url = jQuery(this).attr('href');
					fc_showDialog(url, 'fc_modal_popup_container');
					return false;
				});
			});
			// Attach pagination for versions listing
			jQuery('#fc_pager').pager({ pagenumber: ".$this->current_page.", pagecount: ".$this->pagecount.", buttonClickCallback: PageClick });
		});
		
		PageClick = function(pageclickednumber) {
			jQuery.ajax({ url: 'index.php?option=com_flexicontent&".$task_items."getversionlist&id=".$this->row->id."&active=".$this->row->version."&".JSession::getFormToken()."=1&format=raw&page='+pageclickednumber, context: jQuery('#version_tbl'), success: function(str){
				jQuery(this).html(\"\\
				<table class='fc-table-list fc-tbl-short' style='margin:10px;'>\\
				\"+str+\"\\
				</table>\\
				\");
				var JTooltips = new Tips($$('table.versionlist tr td a.hasTip'), { maxTitleChars: 50, fixed: false});
				
				// Attach click event to version compare links of the newly created page
				jQuery(this).find('a.modal-versions').each(function(index, value) {
					jQuery(this).on('click', function() {
						// Load given URL in an popup dialog
						var url = jQuery(this).attr('href');
						fc_showDialog(url, 'fc_modal_popup_container');
						return false;
					});
				});
			}});
			
			// Reattach pagination inside the newly created page
			jQuery('#fc_pager').pager({ pagenumber: pageclickednumber, pagecount: ".$this->pagecount.", buttonClickCallback: PageClick });
		}
		
		jQuery(document).ready(function(){
			jQuery('#versioncomment').autogrow({
				minHeight: 26,
				maxHeight: 250,
				lineHeight: 12
			});
		})
		
	");
}

// version variables
$tags_fieldname = 'jform[tag][]';

$this->document->addScriptDeclaration("
	jQuery(document).ready(function(){
		var hits = new itemscreen('hits', {id:".($this->row->id ? $this->row->id : 0).", task:'".$ctrl_items."gethits'});
		hits.fetchscreen();
	
		var votes = new itemscreen('votes', {id:".($this->row->id ? $this->row->id : 0).", task:'".$ctrl_items."getvotes'});
		votes.fetchscreen();
	});

	function addToList(id, name) {
		obj = jQuery('#ultagbox');
		obj.append(\"<li class='tagitem'><span>\"+name+\"</span><input type='hidden' name='".$tags_fieldname."' value='\"+id+\"' /><a href='javascript:;' class='deletetag' onclick='javascript:deleteTag(this);' title='". JText::_( 'FLEXI_DELETE_TAG',true ) ."'></a></li>\");
	}
	function addtag(id, tagname) {
		if (id==null) id = 0;
	
		if(tagname == '') {
			alert('".JText::_( 'FLEXI_ENTER_TAG', true)."');
			return;
		}
	
		var tag = new itemscreen();
		tag.addtag( id, tagname, 'index.php?option=com_flexicontent&".$tags_task."addtag&format=raw&".JSession::getFormToken()."=1');
	}

	function reseter(task, id, div){
		var res = new itemscreen();
		task = '".$ctrl_items."' + task;
		res.reseter( task, id, div, 'index.php?option=com_flexicontent&controller=items' );
	}
	function clickRestore(link) {
		if(confirm('".JText::_( 'FLEXI_CONFIRM_VERSION_RESTORE',true )."')) {
			location.href=link;
		}
		return false;
	}
	function deleteTag(obj) {
		var parent = obj.parentNode;
		parent.innerHTML = '';
		parent.parentNode.removeChild(parent);
	}
");


// Create info images
$infoimage    = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/information.png', JText::_( 'FLEXI_NOTES' ) );
$revertimage  = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/arrow_rotate_anticlockwise.png', JText::_( 'FLEXI_REVERT' ) );
$viewimage    = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/magnifier.png', JText::_( 'FLEXI_VIEW' ) );
$commentimage = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/comment.png', JText::_( 'FLEXI_COMMENT' ) );

// Create some variables
$itemlang = substr($this->row->language ,0,2);
if (isset($this->row->item_translations)) foreach ($this->row->item_translations as $t) if ($t->shortcode==$itemlang) {$itemlangname = $t->name; break;}
?>

<?php /* echo "Version: ". $this->row->version."<br/>\n"; */?>
<?php /* echo "id: ". $this->row->id."<br/>\n"; */?>
<?php /* echo "type_id: ". @$this->row->type_id."<br/>\n"; */?>


<div id="flexicontent" class="flexi_edit flexicontent" >

<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate" enctype="multipart/form-data" >
	
	<div class="container-fluid" style="padding:0px!important;">
	<?php /*<fieldset class="basicfields_set">
		<legend>
			<span class="fc_legend_text"><?php echo JText::_( 'FLEXI_BASIC' ); ?></span>
		</legend>*/ ?>
		
		<div class="span6 full_width_980">
			
			<?php
				$field = isset($this->fields['title']) ? $this->fields['title'] : false;
				if ($field) {
					$field_description = $field->description ? $field->description :
						JText::_($this->form->getField('title')->description);
					$label_tooltip = 'class="'.$tip_class.' label pull-left label-fcinner label-toplevel" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"';
				} else {
					$label_tooltip = 'class="label pull-left label-fcinner label-toplevel"';
				}
			?>
			<span class="label-fcouter">
			<label id="jform_title-lbl" for="jform_title" <?php echo $label_tooltip; ?> >
				<?php echo $field ? $field->label : JText::_( 'FLEXI_TITLE' ); ?>
			</label>
			</span>
			<?php /*echo $this->form->getLabel('title');*/ ?>
	
			<div class="container_fcfield container_fcfield_id_6 container_fcfield_name_title input-fcmax" id="container_fcfield_6">
			
			<?php if ( $this->params->get('auto_title', 0) ): ?>
				<?php echo '<span class="badge badge-info">'.($this->row->id ? $this->row->id : JText::_('FLEXI_AUTO')).'</span>'; ?>
			<?php	elseif ( isset($this->row->item_translations) ) :?>
				<?php
				array_push($tabSetStack, $tabSetCnt);
				$tabSetCnt = ++$tabSetMax;
				$tabCnt[$tabSetCnt] = 0;
				?>
				<!-- tabber start -->
				<div class="fctabber tabber-inline tabber-lang" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
					<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
						<h3 class="tabberheading"> <?php echo '-'.$itemlangname.'-'; // $itemlang; ?> </h3>
						<?php echo $this->form->getInput('title');?>
					</div>
					<?php foreach ($this->row->item_translations as $t): ?>
						<?php if ($itemlang!=$t->shortcode && $t->shortcode!='*') : ?>
							<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
								<h3 class="tabberheading"> <?php echo $t->name; // $t->shortcode; ?> </h3>
								<?php
								$ff_id = 'jfdata_'.$t->shortcode.'_title';
								$ff_name = 'jfdata['.$t->shortcode.'][title]';
								?>
								<input class="fc_form_title fcfield_textval" style='margin:0px;' type="text" id="<?php echo $ff_id; ?>" name="<?php echo $ff_name; ?>" value="<?php echo @$t->fields->title->value; ?>" size="36" maxlength="254" />
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
			
			
			<div class="fcclear"></div>
			<?php
				$field_description = JText::_($this->form->getField('alias')->description);
				$label_tooltip = 'class="'.$tip_class.' label pull-left label-fcinner label-toplevel" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"';
			?>
			<span class="label-fcouter">
			<label id="jform_alias-lbl" for="jform_alias" <?php echo $label_tooltip; ?> >
				<?php echo JText::_( 'FLEXI_ALIAS' ); ?>
			</label>
			</span>

			<div class="container_fcfield container_fcfield_name_alias input-fcmax">
			<?php	if ( isset($this->row->item_translations) ) :?>
				<?php
				array_push($tabSetStack, $tabSetCnt);
				$tabSetCnt = ++$tabSetMax;
				$tabCnt[$tabSetCnt] = 0;
				?>
				<!-- tabber start -->
				<div class="fctabber tabber-inline tabber-lang" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
					<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
						<h3 class="tabberheading"> <?php echo '-'.$itemlangname.'-'; // $itemlang; ?> </h3>
						<?php echo $this->form->getInput('alias');?>
					</div>
					<?php foreach ($this->row->item_translations as $t): ?>
						<?php if ($itemlang!=$t->shortcode && $t->shortcode!='*') : ?>
							<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
								<h3 class="tabberheading"> <?php echo $t->name; // $t->shortcode; ?> </h3>
								<?php
								$ff_id = 'jfdata_'.$t->shortcode.'_alias';
								$ff_name = 'jfdata['.$t->shortcode.'][alias]';
								?>
								<input class="fc_form_alias fcfield_textval" style='margin:0px;' type="text" id="<?php echo $ff_id; ?>" name="<?php echo $ff_name; ?>" value="<?php echo @$t->fields->alias->value; ?>" size="36" maxlength="254" />
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

			
			<div class="fcclear"></div>
			<span class="label-fcouter">
			<label id="jform_catid-lbl" for="jform_catid" data-for_bck="jform_catid" class="label pull-left label-fcinner label-toplevel <?php echo $tip_class; ?>" style="display:inline-block;" title="<?php echo flexicontent_html::getToolTip( 'FLEXI_NOTES', 'FLEXI_SEC_FEAT_CATEGORIES_NOTES', 1, 1);?>">
				<?php echo JText::_( 'FLEXI_CATEGORY' ); ?>
			</label>
			</span>
			<div class="container_fcfield container_fcfield_name_catid">
				<?php echo $this->lists['catid']; ?>
			</div>
		

			<?php /* tags always available in backend */ ?>
			<?php
				$field = isset($this->fields['tags']) ? $this->fields['tags'] : false;
				if ($field) {
					$label_tooltip = 'class="'.$tip_class.' label pull-left label-fcinner label-toplevel" title="'.flexicontent_html::getToolTip(null, $field->description, 0, 1).'"';
				} else {
					$label_tooltip = 'class="label pull-left label-fcinner label-toplevel"';
				}
			?>
			<div class="fcclear"></div>
			<span class="label-fcouter">
			<label id="jform_tag-lbl" for="jform_tag" <?php echo $label_tooltip; ?> >
				<?php echo $field ? $field->label : JText::_( 'FLEXI_TAGS' ); ?>
			</label>
			</span>
			<div class="container_fcfield container_fcfield_name_tags">

				<?php if ( $this->perms['cantags'] ) : ?>
				<div class="fcclear"></div>
				<div id="tags">
					<?php /*<label for="input-tags">
						<?php echo JText::_( 'FLEXI_ADD_TAG' ); ?>
					</label> */ ?> 
					<input type="text" id="input-tags" name="tagname" data-tagid="0" data-tagname="" />
					<span id='input_new_tag' ></span>
					<span class="<?php echo $tip_class; ?>" style="display:inline-block;" title="<?php echo flexicontent_html::getToolTip( 'FLEXI_NOTES', 'FLEXI_TAG_EDDITING_FULL', 1, 1);?>">
						<?php echo $infoimage; ?>
					</span>
				</div>
				<?php endif; ?>
				
				<div class="qf_tagbox" id="qf_tagbox">
					<ul id="ultagbox">
					<?php
						$nused = count($this->usedtags);
						for( $i = 0, $nused; $i < $nused; $i++ ) {
							$tag = $this->usedtags[$i];
							if ( $this->perms['cantags'] ) {
								echo '
								<li class="tagitem">
									<span>'.$tag->name.'</span>
									<input type="hidden" name="jform[tag][]" value="'.$tag->tid.'" />
									<a href="javascript:;" class="deletetag" onclick="javascript:deleteTag(this);" title="'.JText::_('FLEXI_DELETE_TAG').'"></a>
								</li>';
							} else {
								echo '
								<li class="tagitem plain">
									<span>'.$tag->name.'</span>
									<input type="hidden" name="jform[tag][]" value="'.$tag->tid.'" />
								</li>';
							}
						}
					?>
					</ul>
				</div>

			</div>
			
		</div>
		
		
		<div class="span6 full_width_980">
			
			<?php
				$field = isset($this->fields['document_type']) ? $this->fields['document_type'] : false;
				if ($field) {
					$field_description = $field->description ? $field->description :
						JText::_($this->form->getField('type_id')->description);
					$label_tooltip = 'class="'.$tip_class.' label pull-left label-fcinner label-toplevel'.(!$this->row->type_id ? ' label-warning' : '').'" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"';
				} else {
					$label_tooltip = 'class="label pull-left label-fcinner label-toplevel"';
				}
			?>
			<div class="fcclear"></div>
			<span class="label-fcouter">
			<label id="jform_type_id-lbl" for="jform_type_id" data-for_bck="jform_type_id" <?php echo $label_tooltip; ?> >
				<?php echo $field ? $field->label : JText::_( 'FLEXI_TYPE' ); ?>
			</label>
			</span>
			<?php /*echo $this->form->getLabel('type_id');*/ ?>
		
			<div class="container_fcfield container_fcfield_id_8 container_fcfield_name_type" id="container_fcfield_8">
				<?php echo $this->lists['type']; ?>
				<?php //echo $this->form->getInput('type_id'); ?>
				<?php
				$label_tooltip = 'class="'.$tip_class.'" title="'.flexicontent_html::getToolTip('FLEXI_NOTES', 'FLEXI_TYPE_CHANGE_WARNING', 1, 1).'"';
				?>
				<span style="display:inline-block;" <?php echo $label_tooltip; ?> >
					<?php echo $infoimage; ?>
				</span>
				<div id="fc-change-warning" class="fc-mssg fc-warning" style="display:none;"><?php echo JText::_( 'FLEXI_TAKE_CARE_CHANGING_FIELD_TYPE' ); ?></div>
			</div>
		
		
			<div class="fcclear"></div>
			<span class="label-fcouter">
			<label id="jform_language-lbl" for="jform_language" class="label pull-left label-fcinner label-toplevel" >
				<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
			</label>
			</span>
			<?php /*echo $this->form->getLabel('language');*/ ?>
		
			<div class="container_fcfield container_fcfield_name_language">
				<?php echo $this->lists['languages']; ?>
			</div>
		
		
			<?php
				$field = isset($this->fields['state']) ? $this->fields['state'] : false;
				if ($field) {
					$field_description = $field->description ? $field->description :
						JText::_($this->form->getField('state')->description);
					$label_tooltip = 'class="'.$tip_class.' label pull-left label-fcinner label-toplevel" title="'.flexicontent_html::getToolTip(null, $field_description, 0, 1).'"';
				} else {
					$label_tooltip = 'class="label pull-left label-fcinner label-toplevel"';
				}
			?>
			<div class="fcclear"></div>
			<span class="label-fcouter">
			<label id="jform_state-lbl" for="jform_state" <?php echo $label_tooltip; ?> >
				<?php echo $field ? $field->label : JText::_( 'FLEXI_STATE' ); ?>
			</label>
			</span>
			<?php /*echo $this->form->getLabel('state');*/ ?>
			<?php
			if ( $this->perms['canpublish'] ) : ?>
				<div class="container_fcfield container_fcfield_id_10 container_fcfield_name_state" id="container_fcfield_10" >
					<?php echo $this->lists['state']; ?>
					<?php //echo $this->form->getInput('state'); ?>
					<span class="<?php echo $tip_class; ?>" style="display:inline-block;" title="<?php echo flexicontent_html::getToolTip( 'FLEXI_NOTES', 'FLEXI_STATE_CHANGE_WARNING', 1, 1);?>">
						<?php echo $infoimage; ?>
					</span>
				</div>
			<?php else :
				echo $this->published;
				echo '<input type="hidden" name="jform[state]" id="jform_vstate" value="'.$this->row->state.'" />';
			endif;?>
		
		
			<?php if ( $this->perms['canpublish'] ) { ?>
			
				<?php if (!$this->params->get('auto_approve', 1)) : ?>
					<div class="fcclear"></div>
					<?php
						//echo "<br/>".$this->form->getLabel('vstate') . $this->form->getInput('vstate');
						$label_tooltip = 'class="'.$tip_class.' label label-success pull-left label-fcinner" title="'.flexicontent_html::getToolTip('FLEXI_PUBLIC_DOCUMENT_CHANGES', 'FLEXI_PUBLIC_DOCUMENT_CHANGES_DESC', 1, 1).'"';
					?>
					<span class="label-fcouter">
					<label id="jform_vstate-lbl" for="jform_vstate" <?php echo $label_tooltip; ?> >
						<?php echo JText::_( 'FLEXI_PUBLIC_DOCUMENT_CHANGES' ); ?>
					</label>
					</span>
					<div class="container_fcfield container_fcfield_name_vstate">
						<?php echo $this->lists['vstate']; ?>
					</div>
				<?php else : ?>
					<?php echo '<input type="hidden" name="jform[vstate]" id="jform_vstate" value="2" />'; ?>
				<?php endif; ?>
			
			<?php } else if (!$this->params->get('auto_approve', 1)) {
				// Enable approval if versioning disabled, this make sense,
				// since if use can edit item THEN item should be updated !!!
				$item_vstate = $this->params->get('use_versioning', 1) ? 1 : 2;
				echo '<input type="hidden" name="jform[vstate]" id="jform_vstate" value="'.$item_vstate.'" />';
			} else {
				echo '<input type="hidden" name="jform[vstate]" id="jform_vstate" value="2" />';
			} ?>
			
			
			<?php if ($this->subscribers) : ?>
				<div class="fcclear"></div>
				<?php
					$label_tooltip = 'class="'.$tip_class.' label label-info pull-left label-fcinner" title="'.flexicontent_html::getToolTip('FLEXI_NOTIFY_FAVOURING_USERS', 'FLEXI_NOTIFY_NOTES', 1, 1).'"';
				?>
				<span class="label-fcouter">
				<label id="jform_notify-msg" <?php echo $label_tooltip; ?> >
					<?php echo JText::_( 'FLEXI_NOTIFY_SUBSCRIBERS' ); ?>
				</label>
				</span>
				<div class="container_fcfield container_fcfield_name_notify">
					<?php echo $this->lists['notify']; ?>
				</div>
			<?php endif; ?>

		</div>
		
	<?php /*</fieldset>*/ ?>
	</div>


<?php
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
	$field = $this->fields['text'];
	if ($field) {
		$field_description = $field->description ? $field->description :
			JText::_($this->form->getField('text')->description);
		$_desc = flexicontent_html::getToolTip(null, $field_description, 0, 1);
	} else {
		$_desc = '';
	}
	if (
		!$field->parameters->get('backend_hidden')  &&
		!in_array($field->formhidden, array(2,3))   &&    // check to SKIP (hide) field via field DB table property 'form_hidden'
		!$this->tparams->get('hide_'.$field->field_type)  // check to SKIP (hide) field via field parameter 'hidden_<fieldtype>'
	) :
?>

	<!-- Description tab -->
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-file-2">
		<h3 class="tabberheading hasTooltip" title="<?php echo $_desc; ?>"> <?php echo $field->label ? $field->label : JText::_( 'FLEXI_DESCRIPTION' ); ?> </h3>
	
			<?php
			// Decide label classes, tooltip, etc
			$lbl_class = 'label pull-left label-fcinner label-toplevel';
			$lbl_title = '';
			
			// field is required
			$lbl_class = $field->parameters->get('required', 0 ) ? 'required' : '';
			
			// field has tooltip
			$edithelp = $field->edithelp ? $field->edithelp : 1;
			if ( $field->description && ($edithelp==1 || $edithelp==2) ) {
				$lbl_class .= ($edithelp==2 ? ' fc_tooltip_icon' : '');
				$label_tooltip = 'class="'.$tip_class.' '.$lbl_class.' label pull-left label-fcinner label-toplevel" title="'.flexicontent_html::getToolTip(null, $field->description, 0, 1).'"';
			} else {
				$label_tooltip = 'class="'.$lbl_class.' label pull-left label-fcinner label-toplevel"';
			}
			
			// Some fields may force a container width ?
			$display_label_form = $field->parameters->get('display_label_form', 1);
			$full_width = $display_label_form==0 || $display_label_form==2 || $display_label_form==-1;
			$width = $field->parameters->get('container_width', ($full_width ? '100%!important;' : false) );
			$container_width = empty($width) ? '' : 'width:' .$width. ($width != (int)$width ? 'px!important;' : '');
			$container_class = "container_fcfield container_fcfield_id_".$field->id." container_fcfield_name_".$field->name;
			?>
			
			<?php /* description field label will be USED as TAB handle title, with field's description as Tooltip */
			/*if ($display_label_form > 0): ?>
				<span class="label-fcouter">
				<label id="label_fcfield_<?php echo $field->id; ?>" for="<?php echo 'custom_'.$field->name;?>" data-for_bck="<?php echo 'custom_'.$field->name;?>" <?php echo $label_tooltip;?> >
					<?php echo $field->label; ?>
				</label>
				</span>
				<?php if($display_label_form==2):  ?>
					<div class='fcclear'></div>
				<?php endif; ?>
			<?php endif; */?>
			
			<div style="<?php echo $container_width; ?>;" class="<?php echo $container_class;?>" id="container_fcfield_<?php echo $field->id;?>">
				<?php echo ($field->description && $edithelp==3) ? '<div class="alert fc-small fc-iblock">'.$field->description.'</div>' : ''; ?>
				
			<?php	if (isset($this->row->item_translations) ) :
				// CASE: CORE 'description' FIELD with multi-tabbed editing of joomfish (J1.5) or falang (J2.5+)
				array_push($tabSetStack, $tabSetCnt);
				$tabSetCnt = ++$tabSetMax;
				$tabCnt[$tabSetCnt] = 0;
				?>
				<!-- tabber start -->
				<div class="fctabber tabber-inline tabber-lang" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
					<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
						<h3 class="tabberheading"> <?php echo '- '.$itemlangname.' -'; // $t->name; ?> </h3>
						<?php
							$field_tab_labels = & $field->tab_labels;
							$field_html       = & $field->html;
							echo !is_array($field_html) ? $field_html : flexicontent_html::createFieldTabber( $field_html, $field_tab_labels, "");
						?>
					</div>
					<?php foreach ($this->row->item_translations as $t): ?>
						<?php if ($itemlang!=$t->shortcode && $t->shortcode!='*') : ?>
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
				
			<?php elseif ( !is_array($field->html) ) : /* CASE 2: NORMAL FIELD non-tabbed */ ?>
				<?php echo isset($field->html) ? $field->html : $noplugin; ?>
			<?php endif; ?>
			
		</div>
	</div> <!-- end tab -->
	
<?php endif; ?>




<?php
//$type_lbl = $this->row->type_id ? JText::_( 'FLEXI_ITEM_TYPE' ) . ' : ' . $this->typesselected->name : JText::_( 'FLEXI_TYPE_NOT_DEFINED' );
if ($this->row->type_id) {
	$_str = JText::_('FLEXI_DETAILS');
	$_str = mb_strtoupper(mb_substr($_str, 0, 1, 'UTF-8')) . mb_substr($_str, 1, NULL, 'UTF-8');
	
	$type_lbl = $this->typesselected->name;
	$type_lbl = $type_lbl ? JText::_($type_lbl) : JText::_('FLEXI_CONTENT_TYPE');
	$type_lbl = $type_lbl .' ('. $_str .')';
} else {
	$type_lbl = JText::_('FLEXI_TYPE_NOT_DEFINED');
}
?>
<?php if ($this->fields && $this->row->type_id) : ?>
	
	<!-- Field manager tab -->
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-signup">
		<h3 class="tabberheading"> <?php echo $type_lbl; ?> </h3>
		
		<?php
			$this->document->addScriptDeclaration("
				jQuery(document).ready(function() {
					jQuery('#jform_type_id').change(function() {
						if (jQuery('#jform_type_id').val() != '".$this->row->type_id."')
							jQuery('#fc-change-warning').css('display', 'block');
						else
							jQuery('#fc-change-warning').css('display', 'none');
					});
				});
			");
		?>
		
		<div class="fc_tabset_inner">
			
			<?php
			$hide_ifempty_fields = array('fcloadmodule', 'fcpagenav', 'toolbar');
			$row_k = 0;
			foreach ($this->fields as $field)
			{
				if (
					// SKIP backend hidden fields from this listing
					($field->iscore && $field->field_type!='maintext')   ||   $field->parameters->get('backend_hidden')  ||   in_array($field->formhidden, array(2,3))   ||
					
					// Skip hide-if-empty fields from this listing
					( empty($field->html) && ($field->formhidden==4 || in_array($field->field_type, $hide_ifempty_fields)) )
				) continue;
				
				// check to SKIP (hide) field e.g. description field ('maintext'), alias field etc
				if ( $this->tparams->get('hide_'.$field->field_type) ) continue;
				
				$not_in_tabs = "";
				if ($field->field_type=='groupmarker') {
					echo $field->html;
					continue;
				} else if ($field->field_type=='coreprops') {
					// not used in backend (yet?)
					continue;
				} else if ($field->field_type=='maintext') {
					// placed in separate TAB
					continue;
				}
				
				// Decide label classes, tooltip, etc
				$lbl_class = 'label pull-left label-fcinner label-toplevel';
				$lbl_title = '';
				
				// field is required
				$lbl_class = $field->parameters->get('required', 0 ) ? 'required' : '';
				
				// field has tooltip
				$edithelp = $field->edithelp ? $field->edithelp : 1;
				if ( $field->description && ($edithelp==1 || $edithelp==2) ) {
					$lbl_class .= ($edithelp==2 ? ' fc_tooltip_icon' : '');
					$label_tooltip = 'class="'.$tip_class.' '.$lbl_class.' label pull-left label-fcinner label-toplevel" title="'.flexicontent_html::getToolTip(null, $field->description, 0, 1).'"';
				} else {
					$label_tooltip = 'class="'.$lbl_class.' label pull-left label-fcinner label-toplevel"';
				}
				
				// Some fields may force a container width ?
				$display_label_form = $field->parameters->get('display_label_form', 1);
				$row_k = 1 - $row_k;
				$full_width = $display_label_form==0 || $display_label_form==2 || $display_label_form==-1;
				$width = $field->parameters->get('container_width', ($full_width ? '100%!important;' : false) );
				$container_width = empty($width) ? '' : 'width:' .$width. ($width != (int)$width ? 'px!important;' : '');
				$container_class = "fcfield_row".$row_k." container_fcfield container_fcfield_id_".$field->id." container_fcfield_name_".$field->name;
				?>
				
				<div class='fcclear'></div>
				<span class="label-fcouter" style="<?php echo $display_label_form < 1 ? 'display:none;' : '' ?>">
				<label id="label_fcfield_<?php echo $field->id; ?>" for="<?php echo 'custom_'.$field->name;?>" data-for_bck="<?php echo 'custom_'.$field->name;?>" <?php echo $label_tooltip;?> >
					<?php echo $field->label; ?>
				</label>
				</span>
				<?php if($display_label_form==2):  ?>
					<div class='fcclear'></div>
				<?php endif; ?>
								
				<div style="<?php echo $container_width; ?>" class="<?php echo $container_class;?>" id="container_fcfield_<?php echo $field->id;?>">
					<?php echo ($field->description && $edithelp==3) ? '<div class="alert fc-small fc-iblock">'.$field->description.'</div>' : ''; ?>
				
				<?php if ( !is_array($field->html) ) : /* CASE 2: NORMAL FIELD non-tabbed */ ?>
					
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
			}
			?>
			
		</div>

	</div> <!-- end tab -->
	
<?php else : /* NO TYPE SELECTED */ ?>

	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" >
		<h3 class="tabberheading"> <?php echo $type_lbl; ?> </h3>
		
		<div class="fc_tabset_inner">
			<?php if ($this->row->id == 0) : ?>
				<input name="jform[type_id_not_set]" value="1" type="hidden" />
				<div class="fc-mssg fc-note"><?php echo JText::_( 'FLEXI_CHOOSE_ITEM_TYPE' ); ?></div>
			<?php else : ?>
				<div class="fc-mssg fc-warning"><?php echo JText::_( 'FLEXI_NO_FIELDS_TO_TYPE' ); ?></div>
			<?php	endif; ?>
		</div>
		
	</div> <!-- end tab -->
	
<?php	endif; ?>



	<!-- Assignment tab -->
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-tree-2">
		<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_ASSIGNMENTS' ); ?> </h3>
		
		<?php $fset_lbl = JText::_('FLEXI_CATEGORIES') .' / '. JText::_('FLEXI_FEATURED');?>
		
		<div class="fcclear"></div>
		<fieldset class="basicfields_set" id="fcform_categories_tags_container">
			<legend>
				<span class="fc_legend_text"><?php echo JText::_( $fset_lbl ); ?></span>
			</legend>
			
			<?php if (1) : /* secondary categories always available in backend */ ?>
				
				<div class="fcclear"></div>
				<span class="label-fcouter">
				<label id="jform_cid-lbl" for="jform_cid" data-for_bck="jform_cid" class="label pull-left label-fcinner label-toplevel">
					<?php echo JText::_( 'FLEXI_SECONDARY_CATEGORIES' ); ?>
				</label>
				</span>
				<div class="container_fcfield container_fcfield_name_catid">
					<?php echo $this->lists['cid']; ?>
				</div>
				
			<?php endif; ?>

			<?php if ( !empty($this->lists['featured_cid']) ) : ?>
				<div class="fcclear"></div>
				<span class="label-fcouter">
				<label id="jform_featured_cid-lbl" for="jform_featured_cid" data-for_bck="jform_featured_cid" class="label pull-left label-fcinner label-toplevel">
					<?php echo JText::_( 'FLEXI_FEATURED_CATEGORIES' ); ?>
				</label>
				</span>
				<div class="container_fcfield container_fcfield_name_featured_cid">
					<?php echo $this->lists['featured_cid']; ?>
				</div>
			<?php endif; ?>


			<div class="fcclear"></div>
			<span class="label-fcouter">
			<label class="label pull-left label-fcinner label-toplevel">
				<?php echo JText::_( 'FLEXI_FEATURED' ); ?>
				<small style="float:right; clear:both;"><?php echo JText::_( 'FLEXI_JOOMLA_FEATURED_VIEW' ); ?></small>
			</label>
			</span>
			<div class="container_fcfield container_fcfield_name_featured">
				<?php echo $this->lists['featured']; ?>
				<?php //echo $this->form->getInput('featured');?>
			</div>

		</fieldset>
		
		
		<div class="fcclear"></div>
		<fieldset class="basicfields_set" id="fcform_language_container">
			<legend>
				<span class="fc_legend_text"><?php echo JText::_('FLEXI_LANGUAGE') .' '. JText::_('FLEXI_ASSOCIATIONS'); ?></span>
			</legend>
			
			<!-- BOF of language / language associations section -->
			<?php if ( $useAssocs/*$this->params->get('enable_translation_groups')*/ ) : ?>

				<div class="fcclear"></div>
				<?php echo $this->loadTemplate('associations'); ?>	
				<?php /*include('development_tmp.php');*/ ?>

<?php /*
				<div class="fcclear"></div>
				<?php
					$label_tooltip = 'class="'.$tip_class.' label pull-left label-fcinner label-toplevel" title="'.flexicontent_html::getToolTip(null, 'FLEXI_ORIGINAL_CONTENT_ITEM_DESC', 1, 1).'"';
				?>
				<span class="label-fcouter">
				<label id="jform_lang_parent_id-lbl" for="jform_lang_parent_id" <?php echo $label_tooltip; ?> >
					<?php echo JText::_( 'FLEXI_ORIGINAL_CONTENT_ITEM' );?>
				</label>
				</span>
				
				<div class="container_fcfield container_fcfield_name_originalitem">
				<?php if ( $this->row->id  && (substr(flexicontent_html::getSiteDefaultLang(), 0,2) == substr($this->row->language, 0,2) || $this->row->language=='*') ) : ?>
					<br/><small><?php echo JText::_( $this->row->language=='*' ? 'FLEXI_ORIGINAL_CONTENT_ALL_LANGS' : 'FLEXI_ORIGINAL_TRANSLATION_CONTENT' );?></small>
					<input type="hidden" name="jform[lang_parent_id]" id="jform_lang_parent_id" value="<?php echo $this->row->id; ?>" />
				<?php else : ?>
					<?php
					if (1) { // currently selecting associated item, is always allowed in backend
						$jAp= JFactory::getApplication();
						$option = JRequest::getVar('option');
						$jAp->setUserState( $option.'.itemelement.langparent_item', 1 );
						$jAp->setUserState( $option.'.itemelement.type_id', $this->row->type_id);
						$jAp->setUserState( $option.'.itemelement.created_by', $this->row->created_by);
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
				<span class="label-fcouter">
				<label id="langassocs-lbl" for="langassocs" class="label pull-left label-fcinner label-toplevel" >
					<?php echo JText::_( 'FLEXI_ASSOC_TRANSLATIONS' );?>
				</label>
				</span>
				<div class="container_fcfield container_fcfield_name_langassocs">
				<?php
				if ( !empty($this->lang_assocs) )
				{
					$row_modified = 0;
					foreach($this->lang_assocs as $assoc_item) {
						if ($assoc_item->id == $this->row->lang_parent_id) {
							$row_modified = strtotime($assoc_item->modified);
							if (!$row_modified)  $row_modified = strtotime($assoc_item->created);
						}
					}
					
					foreach($this->lang_assocs as $assoc_item)
					{
						if ($assoc_item->id==$this->row->id) continue;
						
						$assoc_modified = strtotime($assoc_item->modified);
						if (!$assoc_modified)  $assoc_modified = strtotime($assoc_item->created);
						$_class = ( $assoc_modified < $row_modified ) ? ' fc_assoc_outdated' : '';
						
						$_link  = 'index.php?option=com_flexicontent&'.$task_items.'edit&cid='. $assoc_item->id;
						$_title = flexicontent_html::getToolTip(
							JText::_( $assoc_modified < $row_modified ? 'FLEXI_OUTDATED' : 'FLEXI_UPTODATE'),
							//JText::_( 'FLEXI_EDIT_ASSOC_TRANSLATION').
							($assoc_item->lang=='*' ? JText::_("All") : $this->langs->{$assoc_item->lang}->name).' <br/><br/> '.
							$assoc_item->title, 0, 1
						);
						
						echo '<a class="fc_assoc_translation '.$tip_class.$_class.'" target="_blank" href="'.$_link.'" title="'.$_title.'" >';
						if ( !empty($assoc_item->lang) && !empty($this->langs->{$assoc_item->lang}->imgsrc) ) {
							echo ' <img src="'.$this->langs->{$assoc_item->lang}->imgsrc.'" alt="'.$assoc_item->lang.'" />';
						} else if( !empty($assoc_item->lang) ) {
							echo $assoc_item->lang=='*' ? JText::_("All") : $assoc_item->lang;
						}
						echo "</a>";
					}
				}
				?>
				</div>
*/ ?>
			<?php endif; ?>
			<!-- EOF of language / language associations section -->
			
		</fieldset>
	
	</div> <!-- end tab -->



<?php 
if ($this->perms['canparams']) : ?>

	<!-- Publishing tab -->
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-calendar">
		<h3 class="tabberheading"> <?php echo JText::_('FLEXI_PUBLISHING'); ?> </h3>
		
		<div class="fc_tabset_inner">
			<div class="fc-info fc-nobgimage fc-mssg-inline" style="font-size: 12px; margin: 0px 0px 16px 0px !important; padding: 16px 32px !important">
			<?php
				// Dates displayed in the item form, are in user timezone for J2.5, and in site's default timezone for J1.5
				$site_zone = JFactory::getApplication()->getCfg('offset');
				$user_zone = JFactory::getUser()->getParam('timezone', $site_zone);
				$tz = new DateTimeZone( $user_zone );
				$tz_offset = $tz->getOffset(new JDate()) / 3600;
				$tz_info =  $tz_offset > 0 ? ' UTC +' . $tz_offset : ' UTC ' . $tz_offset;
				$tz_info .= ' ('.$user_zone.')';
				echo JText::sprintf( FLEXI_J16GE ? 'FLEXI_DATES_IN_USER_TIMEZONE_NOTE' : 'FLEXI_DATES_IN_SITE_TIMEZONE_NOTE', ' ', $tz_info );
			?>
			</div>
			
			
			<?php /*if ($this->perms['isSuperAdmin']) :*/ ?>
			<fieldset class="panelform">
				<span class="label-fcouter"><?php echo str_replace('class="', 'class="label label-fcinner ', $this->form->getLabel('created_by')); ?></span>
				<div class="container_fcfield"><?php echo $this->form->getInput('created_by'); ?></div>
			</fieldset>	
			<?php /*endif;*/ ?>
			
			<?php if ($this->perms['editcreationdate']) : ?>
			<fieldset class="panelform">
				<span class="label-fcouter"><?php echo str_replace('class="', 'class="label label-fcinner ', $this->form->getLabel('created')); ?></span>
				<div class="container_fcfield"><?php echo $this->form->getInput('created'); ?></div>
			</fieldset>	
			<?php endif; ?>
			
			<fieldset class="panelform">
				<span class="label-fcouter"><?php echo str_replace('class="', 'class="label label-fcinner ', $this->form->getLabel('created_by_alias')); ?></span>
				<div class="container_fcfield"><?php echo $this->form->getInput('created_by_alias'); ?></div>
			</fieldset>	
			
			<fieldset class="panelform">
				<span class="label-fcouter"><?php echo str_replace('class="', 'class="label label-fcinner ', $this->form->getLabel('publish_up')); ?></span>
				<div class="container_fcfield"><?php echo $this->form->getInput('publish_up'); ?></div>
			</fieldset>	
			
			<fieldset class="panelform">
				<span class="label-fcouter"><?php echo str_replace('class="', 'class="label label-fcinner ', $this->form->getLabel('publish_down')); ?></span>
				<div class="container_fcfield"><?php echo $this->form->getInput('publish_down'); ?></div>
			</fieldset>	
			
			<fieldset class="panelform">
				<span class="label-fcouter"><?php echo str_replace('class="', 'class="label label-fcinner ', $this->form->getLabel('access')); ?></span>
				<?php if ($this->perms['canacclvl']) :?>
					<div class="container_fcfield"><?php echo $this->form->getInput('access'); ?></div>
				<?php else :?>
					<div class="container_fcfield"><span class="label"><?php echo $this->row->access_level; ?></span></div>
				<?php endif; ?>
			</fieldset>	

		</div>
		
	</div> <!-- end tab -->
<?php endif; ?>



	<!-- META/SEO tab -->
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-bookmark">
		<h3 class="tabberheading"> <?php echo JText::_('FLEXI_META_SEO'); ?> </h3>
		
		<?php
		//echo $this->form->getLabel('metadesc');
		//echo $this->form->getInput('metadesc');
		//echo $this->form->getLabel('metakey');
		//echo $this->form->getInput('metakey');
		?>
		
		<span class="fcsep_level1" style=""><?php echo JText::_( 'FLEXI_META' ); ?></span>
		<div class="fcclear"></div>
		
		<fieldset class="panelform">
			<span class="label-fcouter"><?php echo str_replace('class="', 'class="label label-fcinner ', $this->form->getLabel('metadesc')); ?></span>
			
			<div class="container_fcfield">
				
				<?php	if ( isset($this->row->item_translations) ) : ?>
					<?php
					array_push($tabSetStack, $tabSetCnt);
					$tabSetCnt = ++$tabSetMax;
					$tabCnt[$tabSetCnt] = 0;
					?>
					<!-- tabber start -->
					<div class="fctabber tabber-inline tabber-lang" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
						<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
							<h3 class="tabberheading"> <?php echo '-'.$itemlangname.'-'; // $itemlang; ?> </h3>
							<?php echo $this->form->getInput('metadesc');?>
						</div>
						<?php foreach ($this->row->item_translations as $t): ?>
							<?php if ($itemlang!=$t->shortcode && $t->shortcode!='*') : ?>
								<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
									<h3 class="tabberheading"> <?php echo $t->name; // $t->shortcode; ?> </h3>
									<?php
									$ff_id = 'jfdata_'.$t->shortcode.'_metadesc';
									$ff_name = 'jfdata['.$t->shortcode.'][metadesc]';
									?>
									<textarea id="<?php echo $ff_id; ?>" class="inputbox" rows="3" cols="46" name="<?php echo $ff_name; ?>"><?php echo @$t->fields->metadesc->value; ?></textarea>
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
		</fieldset>
			
		<fieldset class="panelform">
			<span class="label-fcouter"><?php echo str_replace('class="', 'class="label label-fcinner ', $this->form->getLabel('metakey')); ?></span>
			
			<div class="container_fcfield">
				<?php	if ( isset($this->row->item_translations) ) :?>
					<?php
					array_push($tabSetStack, $tabSetCnt);
					$tabSetCnt = ++$tabSetMax;
					$tabCnt[$tabSetCnt] = 0;
					?>
					<!-- tabber start -->
					<div class="fctabber tabber-inline tabber-lang" id="fcform_tabset_<?php echo $tabSetCnt; ?>">
						<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
							<h3 class="tabberheading"> <?php echo '-'.$itemlangname.'-'; // $itemlang; ?> </h3>
							<?php echo $this->form->getInput('metakey');?>
						</div>
						<?php foreach ($this->row->item_translations as $t): ?>
							<?php if ($itemlang!=$t->shortcode && $t->shortcode!='*') : ?>
								<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" style="padding: 0px;">
									<h3 class="tabberheading"> <?php echo $t->name; // $t->shortcode; ?> </h3>
									<?php
									$ff_id = 'jfdata_'.$t->shortcode.'_metakey';
									$ff_name = 'jfdata['.$t->shortcode.'][metakey]';
									?>
									<textarea id="<?php echo $ff_id; ?>" class="inputbox" rows="3" cols="46" name="<?php echo $ff_name; ?>"><?php echo @$t->fields->metakey->value; ?></textarea>
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
		</fieldset>
		
		<?php foreach($this->form->getGroup('metadata') as $field): ?>
			<?php if ($field->hidden): ?>
				<span style="display:none !important;">
					<?php echo $field->input; ?>
				</span>
			<?php else: ?>
				<fieldset class="panelform">
					<?php echo ($field->label ? '
						<span class="label-fcouter">'.str_replace('class="', 'class="label label-fcinner ', $field->label).'</span>
						<div class="container_fcfield">'.$field->input.'</div>
					' : $field->input); ?>
				</fieldset>
			<?php endif; ?>
		<?php endforeach; ?>
		
		
		<span class="fcsep_level1" style=""><?php echo JText::_( 'FLEXI_SEO' ); ?></span>
		<div class="fcclear"></div>
		
		<?php foreach ($this->form->getFieldset('params-seoconf') as $field) : ?>
			<?php if ($field->hidden): ?>
				<span style="display:none !important;">
					<?php echo $field->input; ?>
				</span>
			<?php else: ?>
				<fieldset class="panelform">
					<?php echo ($field->label ? '
						<span class="label-fcouter">'.str_replace('class="', 'class="label label-fcinner ', $field->label).'</span>
						<div class="container_fcfield">'.$field->input.'</div>
					' : $field->input); ?>
				</fieldset>
			<?php endif; ?>
		<?php endforeach; ?>
		
	</div> <!-- end tab -->



	<!-- Display parameters tab -->
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-eye-open">
		<h3 class="tabberheading"> <?php echo JText::_('FLEXI_DISPLAYING'); ?> </h3>
		
		<div class="fc_tabset_inner">
		<?php
			$fieldSets = $this->form->getFieldsets('attribs');
			foreach ($fieldSets as $name => $fieldSet) :
				if ( $name=='themes' || $name=='params-seoconf'  || $name=='images' ||  $name=='urls' ) continue;

				//$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_'.$name.'_FIELDSET_LABEL';
				//echo JHtml::_('sliders.panel', JText::_($label), $name.'-options');
				?>
				<?php foreach ($this->form->getFieldset($name) as $field) : ?>
					
					<?php if ($field->hidden): ?>
						<span style="display:none !important;">
							<?php echo $field->input; ?>
						</span>
					<?php else: ?>
						<fieldset class="panelform">
							<?php echo ($field->label ? '
								<span class="label-fcouter">'.str_replace('class="', 'class="label label-fcinner ', $field->label).'</span>
								<div class="container_fcfield">'.$field->input.'</div>
							' : $field->input); ?>
						</fieldset>
					<?php endif; ?>
					
				<?php endforeach; ?>
				
		<?php endforeach; ?>
		</div>
		
	</div> <!-- end tab -->



<?php 
// *********************
// JOOMLA IMAGE/URLS TAB
// *********************
if (JComponentHelper::getParams('com_content')->get('show_urls_images_backend', 0) ) : ?>
	
	<!-- Joomla images/urls tab -->
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-joomla" >
		<h3 class="tabberheading"> <?php echo JText::_('FLEXI_COMPATIBILITY'); ?> </h3>
		
		<?php
		$fields_grps_compatibility = array('images', 'urls');
		foreach ($fields_grps_compatibility as $name => $fields_grp_name) :
		?>
		
		<fieldset class="flexi_params fc_tabset_inner">
			<?php foreach ($this->form->getGroup($fields_grp_name) as $field) : ?>
				<div class="fcclear"></div>
				<?php if ($field->hidden): ?>
					<span style="display:none !important;">
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
<?php endif; ?>



	<!-- Template tab -->
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-palette">
		<h3 class="tabberheading"> <?php echo JText::_('FLEXI_TEMPLATE'); ?> </h3>
		
		<div class="fc_tabset_inner">
			<div class="fc-info fc-nobgimage fc-mssg-inline" style="font-size: 12px; margin: 0px 0px 48px 0px !important; padding: 16px 32px !important">
				<?php echo JText::_( 'FLEXI_PARAMETERS_LAYOUT_EXPLANATION' ); ?>
				<br/><br/>
				<ol style="margin:0 0 0 16px; padding:0;">
					<li style="margin:0; padding:0;"> Select TEMPLATE layout </li>
					<li style="margin:0; padding:0;"> Open slider with TEMPLATE (layout) PARAMETERS </li>
				</ol>
				<br/>
				<b>NOTE:</b> Common method for -displaying- fields is by <b>editing the template layout</b> in template manager and placing the fields into <b>template positions</b>
			</div>
			
			<div class="fcclear"></div>
			
			<?php foreach($this->form->getFieldset('themes') as $field): ?>
				<div class="fcclear"></div>
				<?php if ($field->hidden): ?>
					<span style="display:none !important;">
						<?php echo $field->input; ?>
					</span>
				<?php elseif ($field->input): ?>
					<fieldset class="panelform">
						<span class="label-fcouter"><?php echo str_replace('class="', 'class="label label-fcinner ', $field->label); ?></span>
						<div class="container_fcfield">
							<?php echo $field->input;?>
						</div>
					</fieldset>
				<?php endif; ?>
			<?php endforeach; ?>
			
			<div class="fcclear"></div>
			<?php $type_default_layout = $this->tparams->get('ilayout'); ?>
			<span class="fc-success fc-nobgimage fc-mssg-inline" id='__content_type_default_layout__'>
				<?php echo JText::sprintf( 'FLEXI_USING_CONTENT_TYPE_LAYOUT', $type_default_layout ); ?>
				<?php echo "<br/><br/>". JText::_( 'FLEXI_RECOMMEND_CONTENT_TYPE_LAYOUT' ); ?>
			</span>
			
			<div class="fcclear"></div>
			
			<div style="max-width:1200px; padding-top: 24px;">
				<?php
				echo JHtml::_('sliders.start','theme-sliders-'.$this->form->getValue("id"), array('useCookie'=>1));
				$groupname = 'attribs';  // Field Group name this is for name of <fields name="..." >
				
				foreach ($this->tmpls as $tmplname => $tmpl) :
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
								$fieldname =  $field->fieldname;
								$value = $tmpl->params->getValue($fieldname, $groupname, $this->row->itemparams->get($fieldname));
								echo str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_',
									$tmpl->params->getLabel($fieldname, $groupname)); ?>
								<div class="container_fcfield">
								<?php echo
									str_replace('jform_attribs_', 'jform_layouts_'.$tmpl->name.'_', 
										str_replace('[attribs]', '[layouts]['.$tmpl->name.']',
											$tmpl->params->getInput($fieldname, $groupname, $value)
										)
									); ?>
								</div>
							<?php endforeach; ?>
						</fieldset>
					<?php endforeach; //fieldSets ?>
				<?php endforeach; //tmpls ?>
				
				<?php echo JHtml::_('sliders.end'); ?>
			</div>
		</div>
		
	</div> <!-- end tab -->



	<!-- Versioning tab -->
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-stack" >
		<h3 class="tabberheading">  <?php echo JText::_('FLEXI_VERSIONS'); ?> </h3>
		
		<div class="fc_tabset_inner">

		<?php
		// used to hide "Reset Hits" when hits = 0
		if ( !$this->row->hits ) {
			$visibility = 'style="display: none; visibility: hidden;"';
		} else {
			$visibility = '';
		}

		if ( !$this->row->score ) {
			$visibility2 = 'style="display: none; visibility: hidden;"';
		} else {
			$visibility2 = '';
		}

		?>
		<table class="fc-table-list fc-tbl-short" style="margin:10px;">
		<tr>
			<th colspan="2">
				<?php echo JText::_( 'FLEXI_VERSION_INFO' ); ?>
			</th>
		</tr>
		<?php
		if ( $this->row->id ) {
		?>
		<tr>
			<td style="width:150px; text-align:right;">
				<span class="label"><?php echo JText::_( 'FLEXI_ITEM_ID' ); ?></span>
			</td>
			<td>
				<?php echo $this->row->id; ?>
			</td>
		</tr>
		<?php
		}
		?>
		<tr>
			<td>
				<?php
					$field = isset($this->fields['state']) ? $this->fields['state'] : false;
					if ($field) {
						$label_tooltip = 'class="'.$tip_class.' label" title="'.flexicontent_html::getToolTip(null, $field->description, 0, 1).'"';
					} else {
						$label_tooltip = 'class="label"';
					}
				?>
				<span <?php echo $label_tooltip; ?>><?php echo $field ? $field->label : JText::_( 'FLEXI_STATE' ); ?></span>
			</td>
			<td>
				<?php echo $this->published;?>
			</td>
		</tr>
		<tr>
			<td>
				<?php
					$field = isset($this->fields['hits']) ? $this->fields['hits'] : false;
					if ($field) {
						$label_tooltip = 'class="'.$tip_class.' label" title="'.flexicontent_html::getToolTip(null, $field->description, 0, 1).'"';
					} else {
						$label_tooltip = 'class="label"';
					}
				?>
				<span <?php echo $label_tooltip; ?>><?php echo $field ? $field->label : JText::_( 'FLEXI_HITS' ); ?></span>
			</td>
			<td>
				<div id="hits" style="float:left;"></div> &nbsp;
				<span <?php echo $visibility; ?>>
					<input name="reset_hits" type="button" class="button btn-small btn-warning" value="<?php echo JText::_( 'FLEXI_RESET' ); ?>" onclick="reseter('<?php echo $ctrl_items; ?>resethits', '<?php echo $this->row->id; ?>', 'hits')" />
				</span>
			</td>
		</tr>
		<tr>
			<td>
				<?php
					$field = isset($this->fields['voting']) ? $this->fields['voting'] : false;
					if ($field) {
						$label_tooltip = 'class="'.$tip_class.' label" title="'.flexicontent_html::getToolTip(null, $field->description, 0, 1).'"';
					} else {
						$label_tooltip = 'class="label"';
					}
				?>
				<span <?php echo $label_tooltip; ?>><?php echo $field ? $field->label : JText::_( 'FLEXI_SCORE' ); ?></span>
			</td>
			<td>
				<div id="votes" style="float:left;"></div> &nbsp;
				<span <?php echo $visibility2; ?>>
					<input name="reset_votes" type="button" class="button btn-small btn-warning" value="<?php echo JText::_( 'FLEXI_RESET' ); ?>" onclick="reseter('<?php echo $ctrl_items; ?>resetvotes', '<?php echo $this->row->id; ?>', 'votes')" />
				</span>
			</td>
		</tr>
		<tr>
			<td>
				<?php
					$label_tooltip = 'class="label"';
				?>
				<span <?php echo $label_tooltip; ?>><?php echo JText::_( 'FLEXI_REVISED' ); ?></span>
			</td>
			<td>
				<?php echo $this->row->last_version;?> <?php echo JText::_( 'FLEXI_TIMES' ); ?>
			</td>
		</tr>
		<tr>
			<td>
				<strong class="label"><?php echo JText::_( 'FLEXI_FRONTEND_ACTIVE_VERSION' ); ?></strong>
			</td>
			<td>
				#<?php echo $this->row->current_version;?>
			</td>
		</tr>
		<tr>
			<td>
				<strong class="label"><?php echo JText::_( 'FLEXI_FORM_LOADED_VERSION' ); ?></strong>
			</td>
			<td>
				#<?php echo $this->row->version;?>
			</td>
		</tr>
		<tr>
			<td>
				<?php
					$field = isset($this->fields['created']) ? $this->fields['created'] : false;
					if ($field) {
						$label_tooltip = 'class="'.$tip_class.' label" title="'.flexicontent_html::getToolTip(null, $field->description, 0, 1).'"';
					} else {
						$label_tooltip = 'class="label"';
					}
				?>
				<span <?php echo $label_tooltip; ?>><?php echo $field ? $field->label : JText::_( 'FLEXI_CREATED' ); ?></span>
			</td>
			<td>
				<?php
				if ( $this->row->created == $this->nullDate ) {
					echo JText::_( 'FLEXI_NEW_ITEM' );
				} else {
					echo JHTML::_('date',  $this->row->created,  JText::_( 'DATE_FORMAT_LC2' ) );
				}
				?>
			</td>
		</tr>
		<tr>
			<td>
				<?php
					$field = isset($this->fields['modified']) ? $this->fields['modified'] : false;
					if ($field) {
						$label_tooltip = 'class="'.$tip_class.' label" title="'.flexicontent_html::getToolTip(null, $field->description, 0, 1).'"';
					} else {
						$label_tooltip = 'class="label"';
					}
				?>
				<span <?php echo $label_tooltip; ?>><?php echo $field ? $field->label : JText::_( 'FLEXI_MODIFIED' ); ?></span>
			</td>
			<td>
				<?php
					if ( $this->row->modified == $this->nullDate ) {
						echo JText::_( 'FLEXI_NOT_MODIFIED' );
					} else {
						echo JHTML::_('date',  $this->row->modified, JText::_( 'DATE_FORMAT_LC2' ));
					}
				?>
			</td>
		</tr>
	<?php if ($this->params->get('use_versioning', 1)) : ?>
			<tr>
				<td style="padding-top:8px;">
					<span class="label"><?php echo JText::_( 'FLEXI_VERSION_COMMENT' ); ?></span>
				</td>
				<td></td>
			</tr><tr>
				<td colspan="2" style="text-align:center;">
					<textarea name="jform[versioncomment]" id="versioncomment" style="width: 96%; padding: 6px 2%; line-height:120%" rows="4"></textarea>
				</td>
			</tr>
		<?php endif; ?>
		</table>
	
	
	<?php if ($this->params->get('use_versioning', 1)) : ?>		
		<?php if ( $this->perms['canversion'] ) : ?>
		
		<table class="fc-table-list fc-tbl-short" style="margin:10px;">
			<tr>
				<th>
					<?php echo JText::_( 'FLEXI_VERSIONS_HISTORY' ); ?>
				</th>
			</tr>
			<tr><td>
				<table id="version_tbl" class="fc-table-list fc-tbl-short" style="margin:10px;">
				<?php if ($this->row->id == 0) : ?>
				<tr>
					<td class="versions-first" colspan="4"><?php echo JText::_( 'FLEXI_NEW_ARTICLE' ); ?></td>
				</tr>
				<?php
				else :
				$date_format = (($date_format = JText::_( 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS_J16GE' )) == 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS_J16GE') ? "d/M H:i" : $date_format;
				foreach ($this->versions as $version) :
					$class = ($version->nr == $this->row->version) ? ' id="active-version" class="success"' : '';
					if ((int)$version->nr > 0) :
				?>
				<tr<?php echo $class; ?>>
					<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo '#' . $version->nr; ?></span></td>
					<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo JHTML::_('date', (($version->nr == 1) ? $this->row->created : $version->date), $date_format ); ?></span></td>
					<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo ($version->nr == 1) ? flexicontent_html::striptagsandcut($this->row->creator, 25) : flexicontent_html::striptagsandcut($version->modifier, 25); ?></span></td>
					<td class="versions"><a href="javascript:;" class="hasTip" title="Comment::<?php echo htmlspecialchars($version->comment, ENT_COMPAT, 'UTF-8');?>"><?php echo $commentimage;?></a><?php
					if((int)$version->nr==(int)$this->row->current_version) { ?>
						<a onclick="javascript:return clickRestore('index.php?option=com_flexicontent&amp;view=item&amp;<?php echo $task_items;?>edit&amp;cid=<?php echo $this->row->id;?>&amp;version=<?php echo $version->nr; ?>');" href="#"><?php echo JText::_( 'FLEXI_CURRENT' ); ?></a>
					<?php }else{
					?>
						<a class="modal-versions"
							href="index.php?option=com_flexicontent&amp;view=itemcompare&amp;cid=<?php echo $this->row->id; ?>&amp;version=<?php echo $version->nr; ?>&amp;tmpl=component"
							title="<?php echo JText::_( 'FLEXI_COMPARE_WITH_CURRENT_VERSION' ); ?>"
						>
							<?php echo $viewimage; ?>
						</a>
						<a onclick="javascript:return clickRestore('index.php?option=com_flexicontent&amp;task=items.edit&amp;cid=<?php echo $this->row->id; ?>&amp;version=<?php echo $version->nr; ?>&amp;<?php echo JSession::getFormToken();?>=1');"
							href="javascript:;"
							title="<?php echo JText::sprintf( 'FLEXI_REVERT_TO_THIS_VERSION', $version->nr ); ?>"
						>
							<?php echo $revertimage; ?>
						</a>
					<?php }?></td>
				</tr>
				<?php
					endif;
				endforeach;
				endif; ?>
				</table>
			</td></tr>
			<tr style="background:unset;"><td style="background:unset;">
				<div id="fc_pager"></div>
			</td></tr>
		</table>
		
		<?php endif; ?>
	<?php endif; ?>
	
	</div>
	</div> <!-- end tab -->
	
	
	<?php if ( $this->perms['canright'] ) : ?>
	<!-- Permissions tab -->
	<div class="tabbertab" id="fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>" data-icon-class="icon-power-cord">
		<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_PERMISSIONS' ); ?> </h3>
		
		<div class="fc_tabset_inner">
			<div id="access"><?php echo $this->form->getInput('rules'); ?></div>
		</div>
		
	</div> <!-- end tab -->
	<?php endif; ?>
	
</div> <!-- end of tab set -->

<?php $tabSetCnt = array_pop($tabSetStack); ?>


<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="jform[id]" value="<?php echo $this->row->id; ?>" />
<input type="hidden" name="controller" value="items" />
<input type="hidden" name="view" value="item" />
<input type="hidden" name="task" value="" />
<input type="hidden" name="unique_tmp_itemid" value="<?php echo JRequest::getVar( 'unique_tmp_itemid' );?>" />
<?php echo $this->form->getInput('hits'); ?>

</form>

</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
