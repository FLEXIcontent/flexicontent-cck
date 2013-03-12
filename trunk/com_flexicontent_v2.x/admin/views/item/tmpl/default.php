<?php
/**
 * @version 1.5 stable $Id: default.php 1269 2012-05-08 01:51:53Z ggppdk $
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

$ctrl_task = FLEXI_J16GE ? 'task=items.' : 'controller=items&task=';

$this->document->addScript('components/com_flexicontent/assets/js/jquery.autogrow.js');
$this->document->addScript('components/com_flexicontent/assets/js/tabber-minimized.js');
$this->document->addStyleSheet('components/com_flexicontent/assets/css/tabber.css');
$this->document->addStyleDeclaration(".fctabber{display:none;}");   // temporarily hide the tabbers until javascript runs, then the class will be changed to tabberlive
if ($this->CanUseTags || $this->CanVersion) {
	$this->document->addScript('components/com_flexicontent/assets/jquery-autocomplete/jquery.bgiframe.min.js');
	$this->document->addScript('components/com_flexicontent/assets/jquery-autocomplete/jquery.ajaxQueue.js');
	$this->document->addScript('components/com_flexicontent/assets/jquery-autocomplete/jquery.autocomplete.min.js');
	$this->document->addScript('components/com_flexicontent/assets/js/jquery.pager.js');
	
	$this->document->addStyleSheet('components/com_flexicontent/assets/jquery-autocomplete/jquery.autocomplete.css');
	$this->document->addStyleSheet('components/com_flexicontent/assets/css/Pager.css');
	$this->document->addScriptDeclaration("
		jQuery(document).ready(function () {
			jQuery(\"#input-tags\").autocomplete(\"".JURI::base()."index.php?option=com_flexicontent&task=items.viewtags&format=raw&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1\", {
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
		});
		jQuery(document).ready(function() {
			jQuery(\"#pager\").pager({ pagenumber: ".$this->current_page.", pagecount: ".$this->pagecount.", buttonClickCallback: PageClick });
		});

		PageClick = function(pageclickednumber) {
			jQuery.ajax({ url: \"index.php?option=com_flexicontent&task=items.getversionlist&id=".$this->row->id."&active=".$this->row->version."&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1&tmpl=component&page=\"+pageclickednumber, context: jQuery(\"#result\"), success: function(str){
				jQuery(this).html(\"<table width='100%' class='versionlist' cellpadding='0' cellspacing='0'>\\
				<tr>\\
					<th colspan='4'>".JText::_( 'FLEXI_VERSIONS_HISTORY' )."</th>\\
				</tr>\\
				\"+str+\"\\
				</table>\");
				var JTooltips = new Tips($$('table.versionlist tr td a.hasTip'), { maxTitleChars: 50, fixed: false});

				SqueezeBox.initialize({});
				$$('a.modal-versions').each(function(el) {
					el.addEvent('click', function(e) {
						new Event(e).stop();
						SqueezeBox.fromElement(el);
					});
				});
			}});
			jQuery(\"#pager\").pager({ pagenumber: pageclickednumber, pagecount: ".$this->pagecount.", buttonClickCallback: PageClick });
		}
	");
}
?>
<script language="javascript" type="text/javascript">
window.addEvent( "domready", function() {
    var hits = new itemscreen('hits', {id:<?php echo $this->row->id ? $this->row->id : 0; ?>, task:'items.gethits'});
    hits.fetchscreen();

    var votes = new itemscreen('votes', {id:<?php echo $this->row->id ? $this->row->id : 0; ?>, task:'items.getvotes'});
    votes.fetchscreen();
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
	
	var tag = new itemscreen();
	tag.addtag( id, tagname, 'index.php?option=com_flexicontent&task=tags.addtag&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1');
}

function reseter(task, id, div){
	var form = document.adminForm;
	
	if (task == 'items.resethits') {
		form.jform_hits.value = 0;
	} else {
	}
		
	var res = new itemscreen();
	res.reseter( task, id, div, 'index.php?option=com_flexicontent' );
}
function clickRestore(link) {
	if(confirm("<?php echo JText::_( 'FLEXI_CONFIRM_VERSION_RESTORE' ); ?>")) {
		location.href=link;
	}
	return false;
}
function deleteTag(obj) {
	var parent = obj.parentNode;
	parent.innerHTML = "";
	parent.parentNode.removeChild(parent);
}
jQuery(document).ready(function($){
//autogrow
$("#versioncomment").autogrow({
	minHeight: 26,
	maxHeight: 250,
	lineHeight: 12
	});
})
</script>
<?php
// Create info images
$infoimage    = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/lightbulb.png', JText::_( 'FLEXI_NOTES' ) );
$revertimage  = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/arrow_rotate_anticlockwise.png', JText::_( 'FLEXI_REVERT' ) );
$viewimage    = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/magnifier.png', JText::_( 'FLEXI_VIEW' ) );
$commentimage = JHTML::image ( 'administrator/components/com_flexicontent/assets/images/comment.png', JText::_( 'FLEXI_COMMENT' ) );

// Create some variables
$itemlang = substr($this->row->language ,0,2);
if (isset($this->row->item_translations)) foreach ($this->row->item_translations as $t) if ($t->shortcode==$itemlang) {$itemlangname = $t->name; break;}
?>

<?php /* echo "Version: ". $this->row->version."<br>\n"; */?>
<?php /* echo "id: ". $this->row->id."<br>\n"; */?>
<?php /* echo "type_id: ". @$this->row->type_id."<br>\n"; */?>


<div id="flexicontent" class="flexi_edit" >
<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate" enctype="multipart/form-data" >
	<table cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td valign="top">
				<table  class="adminform">
					<tr>
						<td valign="top" width="380">
							<table cellspacing="0" cellpadding="0" border="0" width="100%">
								<tr>
									<td>
									
									<span class="flexi_label">
										<?php
											$field = $this->fields['title'];
											$label_tooltip = $field->description ? 'class="hasTip" title="'.$field->label.'::'.$field->description.'"' : '';
										?>
										<label id="jform_title-lbl" for="jform_title" <?php echo $label_tooltip; ?> >
											<?php echo $field->label.':'; ?>
											<?php /*echo JText::_( 'FLEXI_TITLE' ).':';*/ ?>
										</label>
										<?php /*echo $this->form->getLabel('title');*/ ?>
									</span>
									
									<?php	if ( isset($this->row->item_translations) ) :?>
									
										<!-- tabber start -->
										<div class="fctabber" style=''>
											<div class="tabbertab" style="padding: 0px;" >
												<h3> <?php echo '-'.$itemlang.'-'; // $t->name; ?> </h3>
												<?php echo $this->form->getInput('title');?>
											</div>
											<?php foreach ($this->row->item_translations as $t): ?>
												<?php if ($itemlang!=$t->shortcode && $t->shortcode!='*') : ?>
													<div class="tabbertab" style="padding: 0px;" >
														<h3> <?php echo $t->shortcode; // $t->name; ?> </h3>
														<?php
														$ff_id = 'jfdata_'.$t->shortcode.'_title';
														$ff_name = 'jfdata['.$t->shortcode.'][title]';
														?>
														<input class="inputbox fc_form_title" style='margin:0px;' type="text" id="<?php echo $ff_id; ?>" name="<?php echo $ff_name; ?>" value="<?php echo @$t->fields->title->value; ?>" size="42" maxlength="254" />
													</div>
												<?php endif; ?>
											<?php endforeach; ?>
										</div>
										<!-- tabber end -->
										
									<?php else : ?>
										<?php echo $this->form->getInput('title');?>
									<?php endif; ?>
									
									</td>
								</tr>
								<tr>
									<td>
										
										<span class="flexi_label">
											<?php echo $this->form->getLabel('alias');?>
										</span>

									<?php	if ( isset($this->row->item_translations) ) :?>
									
										<!-- tabber start -->
										<div class="fctabber" style=''>
											<div class="tabbertab" style="padding: 0px;" >
												<h3> <?php echo '-'.$itemlang.'-'; // $t->name; ?> </h3>
												<?php echo $this->form->getInput('alias');?>
											</div>
											<?php foreach ($this->row->item_translations as $t): ?>
												<?php if ($itemlang!=$t->shortcode && $t->shortcode!='*') : ?>
													<div class="tabbertab" style="padding: 0px;" >
														<h3> <?php echo $t->shortcode; // $t->name; ?> </h3>
														<?php
														$ff_id = 'jfdata_'.$t->shortcode.'_alias';
														$ff_name = 'jfdata['.$t->shortcode.'][alias]';
														?>
														<input class="inputbox fc_form_alias" style='margin:0px;' type="text" id="<?php echo $ff_id; ?>" name="<?php echo $ff_name; ?>" value="<?php echo @$t->fields->alias->value; ?>" size="42" maxlength="254" />
													</div>
												<?php endif; ?>
											<?php endforeach; ?>
										</div>
										<!-- tabber end -->
										
									<?php else : ?>
										<?php echo $this->form->getInput('alias');?>
									<?php endif; ?>
									
									</td>
								</tr>
								<tr>
									<td>
									
									<span class="flexi_label">
										<?php
											$field = $this->fields['document_type'];
											$label_tooltip = $field->description ? 'class="hasTip" title="'.$field->label.'::'.$field->description.'"' : '';
										?>
										<label id="jform_type_id-lbl" for="jform_type_id" <?php echo $label_tooltip; ?> >
											<?php echo $field->label.':'; ?>
											<?php /*echo JText::_( 'FLEXI_TYPE' ).':';*/ ?>
										</label>
										<?php /*echo $this->form->getLabel('type_id');*/ ?>
									</span>
										
									<?php echo $this->lists['type']; ?>
									<?php //echo $this->form->getInput('type_id'); ?>
									
									</td>
								</tr>
								<tr>
									<td>
										
									<span class="flexi_label">
										<?php
											$field = $this->fields['state'];
											$label_tooltip = $field->description ? 'class="hasTip" title="'.$field->label.'::'.$field->description.'"' : '';
										?>
										<label id="jform_state-lbl" for="jform_state" <?php echo $label_tooltip; ?> >
											<?php echo $field->label.':'; ?>
											<?php /*echo JText::_( 'FLEXI_STATE' ).':';*/ ?>
										</label>
										<?php /*echo $this->form->getLabel('state');*/ ?>
									</span>
										
									<?php
									if ( $this->canPublish || $this->canPublishOwn ) :
										echo $this->form->getInput('state') . '&nbsp;';
										
										if (!$this->cparams->get('auto_approve', 1)) :
											echo "<br/>".$this->form->getLabel('vstate') . $this->form->getInput('vstate');
										else :
											echo '<input type="hidden" name="jform[vstate]" id="jform_vstate" value="2" />';
										endif;
									else :
										echo $this->published;
										echo '<input type="hidden" name="jform[state]" id="jform_vstate" value="'.$this->row->state.'" />';
											if (!$this->cparams->get('auto_approve', 1)) :
												// Enable approval if versioning disabled, this make sense,
												// since if use can edit item THEN item should be updated !!!
												$item_vstate = $this->cparams->get('use_versioning', 1) ? 1 : 2;
												echo '<input type="hidden" name="jform[vstate]" id="jform_vstate" value="'.$item_vstate.'" />';
											else :
												echo '<input type="hidden" name="jform[vstate]" id="jform_vstate" value="2" />';
											endif;
									endif;
									?>
										
									</td>
								</tr>

								<tr>
									<td>
										<span class="flexi_label">
											<?php echo $this->form->getLabel('featured'); ?>
										</span>
										<?php echo $this->form->getInput('featured');?>
									</td>
								</tr>
															
								<?php if ($this->subscribers) : ?>
								<tr>
									<td>
									<span class="flexi_label">
										<label id="jform_notify-lbl" for="jform_notify" >
											<?php echo JText::_( 'FLEXI_NOTIFY_FAVOURING_USERS' ).':'; ?>
										</label>
									</span>
										
										<input type="checkbox" name="jform[notify]" id="jform_notify" />
										<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_NOTES' ); ?>::<?php echo JText::_( 'FLEXI_NOTIFY_NOTES' );?>">
										<?php echo $infoimage; ?>
										</span>
										(<?php echo $this->subscribers . ' ' . (($this->subscribers > 1) ? JText::_( 'FLEXI_SUBSCRIBERS' ) : JText::_( 'FLEXI_SUBSCRIBER' )); ?>)
										
									</td>
								</tr>
								<?php endif; ?>
								
								<?php if ($this->cparams->get('enable_notifications', 1) && JPluginHelper::isEnabled('content', 'notifyarticlesubmit')) : ?>
								<tr>
									<td>
										<div class="flexi_formblock" style="float:left;">
										<span class="flexi_label">
											<label for="notify">
												<?php echo JText::_( 'FLEXI_NOTIFY_DESIGNATED_USERS' ).':'; ?>
											</label>
										</span>
										<span id="fc_notifyarticlesubmit"></span>
									</div>
									</td>
								</tr>
								<?php endif; ?>
								
							</table>
						</td>
						<td valign="top" align="left" style="text-align:left;">
							<div class="qf_tagbox" id="qf_tagbox">
								<ul id="ultagbox">
								<?php
									$nused = count($this->usedtags);
									for( $i = 0, $nused; $i < $nused; $i++ ) {
										$tag = $this->usedtags[$i];
										if ($this->CanUseTags) {
											echo '<li class="tagitem"><span>'.$tag->name.'</span>';
											echo '<input type="hidden" name="jform[tag][]" value="'.$tag->tid.'" /><a href="javascript:;" class="deletetag" onclick="javascript:deleteTag(this);" align="right" title="'.JText::_('FLEXI_DELETE_TAG').'"></a></li>';
										} else {
											echo '<li class="tagitem plain"><span>'.$tag->name.'</span>';
											echo '<input type="hidden" name="jform[tag][]" value="'.$tag->tid.'" /></li>';
										}
									}
									?>
								</ul>
							</div>
							
							<?php if ($this->CanUseTags) : ?>
							<div id="tags">
							
							<span class="flexi_label">
								<label for="input-tags">
									<?php echo JText::_( 'FLEXI_ADD_TAG' ); ?>
								</label>
							</span>
								<input type="text" id="input-tags" name="tagname" tagid='0' tagname='' /><span id='input_new_tag'></span>
							
							</div>
							<?php endif; ?>
								
							<div style='clear:both; margin-bottom:12px;'></div>
							
							<?php if (FLEXI_FISH || FLEXI_J16GE) : ?>
							<div style='clear:both;'>
								<span class="flexi_label">
									<?php echo $this->form->getLabel('language').':'; ?>
								</span>
								
								<?php echo $this->lists['languages']; ?>
							</div>
							<?php endif; ?>

							<?php if ($this->cparams->get('enable_translation_groups')) : ?>
								<div style='clear:both;'>
									<label id="jform_lang_parent_id-lbl" for="jform_lang_parent_id" class="flexi_label" >
										<?php echo JText::_( 'FLEXI_ORIGINAL_CONTENT_ITEM' );?>
										<span class="editlinktip hasTip" title="::<?php echo JText::_ ( 'FLEXI_ORIGINAL_CONTENT_ITEM_DESC' );?>">
											<?php echo JHTML::image ( 'components/com_flexicontent/assets/images/icon-16-hint.png', JText::_ ( 'FLEXI_ORIGINAL_CONTENT_ITEM' ) ); ?>
										</span>
									</label>
									<?php if ( $this->row->id  && (substr(flexicontent_html::getSiteDefaultLang(), 0,2) == substr($this->row->language, 0,2) || $this->row->language=='*') ) : ?>
										<br/><small><?php echo JText::_( $this->row->language=='*' ? 'FLEXI_ORIGINAL_CONTENT_ALL_LANGS' : 'FLEXI_ORIGINAL_TRANSLATION_CONTENT' );?></small>
										<input type="hidden" name="jform[lang_parent_id]" id="jform_lang_parent_id" value="<?php echo $this->row->id; ?>" />
									<?php else : ?>
										<?php
											$jAp=& JFactory::getApplication();
											$option = JRequest::getVar('option');
											$jAp->setUserState( $option.'.itemelement.langparent_item', 1 );
											$jAp->setUserState( $option.'.itemelement.type_id', $this->row->type_id);
											$jAp->setUserState( $option.'.itemelement.created_by', $this->row->created_by);
											echo '<small>'.JText::_( 'FLEXI_ORIGINAL_CONTENT_IGNORED_IF_DEFAULT_LANG' ).'</small><br>';
											echo $this->form->getInput('lang_parent_id');
										?>
									<?php endif; ?>
								</div>
							<?php endif; ?>
							
							<?php if ($this->cparams->get('enable_translation_groups')) : ?>
								<div style='clear:both;'>
									<label id="jform_lang_parent_id-lbl" for="jform_lang_parent_id" class="flexi_label" >
										<?php echo JText::_( 'FLEXI_ASSOC_TRANSLATIONS' );?>
									</label>
									<?php
									if ( !empty($this->lang_assocs) ) {
										
										$row_modified = 0;
										foreach($this->lang_assocs as $assoc_item) {
											if ($assoc_item->id == $this->row->lang_parent_id) {
												$row_modified = strtotime($assoc_item->modified);
												if (!$row_modified)  $row_modified = strtotime($assoc_item->created);
											}
										}
										
										foreach($this->lang_assocs as $assoc_item) {
											if ($assoc_item->id==$this->row->id) continue;
											
											$_link  = 'index.php?option=com_flexicontent&'.$ctrl_task.'edit&cid[]='. $assoc_item->id;
											$_title = JText::_( 'FLEXI_EDIT_ASSOC_TRANSLATION' ).':: ['. $assoc_item->lang .'] '. $assoc_item->title;
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
							
						</td>
					</tr>
				</table>

				<?php
				if ($this->canRight) :
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
								<div id="access"><?php echo $this->form->getInput('rules'); ?></div>
							</td>
						</tr>
					</table>
					<div id="notabacces">
					<?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT_DESC' ); ?>
					</div>
				</fieldset>
				<?php endif; ?>

				<?php
				if ($this->fields && $this->row->type_id) {
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
						<?php echo $this->row->type_id ? JText::_( 'FLEXI_ITEM_TYPE' ) . ' : ' . $this->typesselected->name : JText::_( 'FLEXI_TYPE_NOT_DEFINED' ); ?>
					</legend>
					
					<!--table class="admintable" width="100%"-->
						<?php
						$hidden = array('fcloadmodule', 'fcpagenav', 'toolbar');
						
						foreach ($this->fields as $field) {
							
							// SKIP backend hidden fields from this listing
							if (
								($field->iscore && $field->field_type!='maintext')  ||
								$field->parameters->get('backend_hidden')  ||
								(in_array($field->field_type, $hidden) && empty($field->html)) ||
								in_array($field->formhidden, array(2,3))
							) continue;
							
							// check to SKIP (hide) field e.g. description field ('maintext'), alias field etc
							if ( $this->tparams->get('hide_'.$field->field_type) ) continue;
							
							// -- Tooltip for the current field label
							$edithelp = $field->edithelp ? $field->edithelp : 1;
							$label_tooltip = ( $field->description && ($edithelp==1 || $edithelp==2) ) ?
								' class="flexi_label hasTip '.($edithelp==2 ? ' fc_tooltip_icon_bg ' : '').'" title="'.$field->label.'::'.$field->description.'" ' :
								' class="flexi_label" ';
							$label_style = ""; //( $field->field_type == 'maintext' || $field->field_type == 'textarea' ) ? " style='clear:both; float:none;' " : "";
							$not_in_tabs = "";
							
							if ($field->field_type=='groupmarker') :
								echo $field->html;
								continue;
							endif;
							
							$width = $field->parameters->get('container_width', '' );
							if ($width)  $width = 'width:' .$width. ($width != (int)$width ? 'px' : '');
						?>
							<!--tr-->
								<!--td class="fcfield-row" style='padding:0px 2px 0px 2px; border: 0px solid lightgray;'-->
									<div class='clear' style='display:block; float:left; clear:both!important'></div>
									
									<label for="<?php echo (FLEXI_J16GE ? 'custom_' : '').$field->name; ?>" <?php echo $label_tooltip . $label_style; ?> >
										<?php echo $field->label; ?>
									</label>
										
									<div style="float:left!important; padding:0px!important; margin:0px!important; <?php echo $width; ?>;">
										<?php echo ($field->description && $edithelp==3) ? '<div class="fc_mini_note_box">'.$field->description.'</div>' : ''; ?>
										
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
											<?php if ($itemlang!=$t->shortcode && $t->shortcode!='*') : ?>
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
				<?php
				} else if ($this->row->id == 0) {
				?>
					<input name="jform[type_id_not_set]" value="1" type="hidden" />
					<div class="fc-info"><?php echo JText::_( 'FLEXI_CHOOSE_ITEM_TYPE' ); ?></div>
				<?php
				} else {
				?>
					<div class="fc-error"><?php echo JText::_( 'FLEXI_NO_FIELDS_TO_TYPE' ); ?></div>
				<?php
				}
				?>
			</td>
			<td valign="top" width="380px" style="padding: 7px 0 0 5px">
			
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
		<table width="100%" style="border: 1px dashed silver; padding: 5px; margin-bottom: 10px;">
		<?php
		if ( $this->row->id ) {
		?>
		<tr>
			<td>
				<strong><?php echo JText::_( 'FLEXI_ITEM_ID' ); ?>:</strong>
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
					$field = $this->fields['state'];
					$label_tooltip = $field->description ? 'class="hasTip" title="'.$field->label.'::'.$field->description.'"' : '';
				?>
				<strong <?php echo $label_tooltip; ?>><?php echo $field->label;  /* JText::_( 'FLEXI_STATE' ) */ ?></strong>
			</td>
			<td>
				<?php echo $this->published;?>
			</td>
		</tr>
		<tr>
			<td>
				<?php
					$field = $this->fields['hits'];
					$label_tooltip = $field->description ? 'class="hasTip" title="'.$field->label.'::'.$field->description.'"' : '';
				?>
				<strong <?php echo $label_tooltip; ?>><?php echo $field->label;  /* JText::_( 'FLEXI_HITS' ) */ ?></strong>
			</td>
			<td>
				<div id="hits" style="float:left;"></div> &nbsp;
				<span <?php echo $visibility; ?>>
					<input name="reset_hits" type="button" class="button" value="<?php echo JText::_( 'FLEXI_RESET' ); ?>" onclick="reseter('items.resethits', '<?php echo $this->row->id; ?>', 'hits')" />
				</span>
			</td>
		</tr>
		<tr>
			<td>
				<?php
					$field = $this->fields['voting'];
					$label_tooltip = $field->description ? 'class="hasTip" title="'.$field->label.'::'.$field->description.'"' : '';
				?>
				<strong <?php echo $label_tooltip; ?>><?php echo $field->label;  /* JText::_( 'FLEXI_SCORE' ) */ ?></strong>
			</td>
			<td>
				<div id="votes" style="float:left;"></div> &nbsp;
				<span <?php echo $visibility2; ?>>
					<input name="reset_votes" type="button" class="button" value="<?php echo JText::_( 'FLEXI_RESET' ); ?>" onclick="reseter('items.resetvotes', '<?php echo $this->row->id; ?>', 'votes')" />
				</span>
			</td>
		</tr>
		<tr>
			<td>
				<?php
					$field = $this->fields['modified'];
					$label_tooltip = $field->description ? 'class="hasTip" title="'.$field->label.'::'.$field->description.'"' : '';
				?>
				<strong <?php echo $label_tooltip; ?>><?php echo $field->label;  /* JText::_( 'FLEXI_REVISED' ) */ ?></strong>
			</td>
			<td>
				<?php echo $this->row->last_version;?> <?php echo JText::_( 'FLEXI_TIMES' ); ?>
			</td>
		</tr>
		<tr>
			<td>
				<strong><?php echo JText::_( 'FLEXI_FRONTEND_ACTIVE_VERSION' ); ?></strong>
			</td>
			<td>
				#<?php echo $this->row->current_version;?>
			</td>
		</tr>
		<tr>
			<td>
				<strong><?php echo JText::_( 'FLEXI_FORM_LOADED_VERSION' ); ?></strong>
			</td>
			<td>
				#<?php echo $this->row->version;?>
			</td>
		</tr>
		<tr>
			<td>
				<?php
					$field = $this->fields['created'];
					$label_tooltip = $field->description ? 'class="hasTip" title="'.$field->label.'::'.$field->description.'"' : '';
				?>
				<strong <?php echo $label_tooltip; ?>><?php echo $field->label;  /* JText::_( 'FLEXI_CREATED' ) */ ?></strong>
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
					$field = $this->fields['modified'];
					$label_tooltip = $field->description ? 'class="hasTip" title="'.$field->label.'::'.$field->description.'"' : '';
				?>
				<strong <?php echo $label_tooltip; ?>><?php echo $field->label; /* JText::_( 'FLEXI_MODIFIED' ) */ ?></strong>
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
		</table>
		
		<?php if ($this->cparams->get('use_versioning', 1)) : ?>
		<table width="100%" style="border: 1px dashed silver; padding: 5px; margin-bottom: 10px;">
			<tr>
				<th style="border-bottom: 1px dotted silver; padding-bottom: 3px;" colspan="4"><?php echo JText::_( 'FLEXI_VERSION_COMMENT' ); ?></th>
			</tr>
			<tr>
				<td><textarea name="jform[versioncomment]" id="versioncomment" style="width: 300px; height: 30px; line-height:1"></textarea></td>
			</tr>
		</table>
		<?php if ($this->CanVersion) : ?>
		<div id="result" >
		<table width="100%" style="border: 1px dashed silver; padding: 5px; margin-bottom: 5px;" cellpadding="0" cellspacing="0">
			<tr>
				<th style="border-bottom: 1px dotted silver; padding: 2px 0 6px 0;" colspan="4"><?php echo JText::_( 'FLEXI_VERSIONS_HISTORY' ); ?></th>
			</tr>
			<?php if ($this->row->id == 0) : ?>
			<tr>
				<td class="versions-first" colspan="4"><?php echo JText::_( 'FLEXI_NEW_ARTICLE' ); ?></td>
			</tr>
			<?php
			else :
			JHTML::_('behavior.modal', 'a.modal-versions');
			$date_format = (($date_format = JText::_( 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS_J16GE' )) == 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS_J16GE') ? "d/M H:i" : $date_format;
			foreach ($this->versions as $version) :
				$class = ($version->nr == $this->row->version) ? ' class="active-version"' : '';
				if ((int)$version->nr > 0) :
			?>
			<tr<?php echo $class; ?>>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo '#' . $version->nr; ?></span></td>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo JHTML::_('date', (($version->nr == 1) ? $this->row->created : $version->date), $date_format ); ?></span></td>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo ($version->nr == 1) ? flexicontent_html::striptagsandcut($this->row->creator, 25) : flexicontent_html::striptagsandcut($version->modifier, 25); ?></span></td>
				<td class="versions" align="center"><a href="javascript:;" class="hasTip" title="Comment::<?php echo $version->comment;?>"><?php echo $commentimage;?></a><?php
				if((int)$version->nr==(int)$this->row->current_version) { ?>
					<a onclick="javascript:return clickRestore('index.php?option=com_flexicontent&view=item&<?php echo $ctrl_task;?>edit&cid=<?php echo $this->row->id;?>&version=<?php echo $version->nr; ?>');" href="#"><?php echo JText::_( 'FLEXI_CURRENT' ); ?></a>
				<?php }else{
				?>
					<a class="modal-versions" href="index.php?option=com_flexicontent&view=itemcompare&cid[]=<?php echo $this->row->id; ?>&version=<?php echo $version->nr; ?>&tmpl=component" title="<?php echo JText::_( 'FLEXI_COMPARE_WITH_CURRENT_VERSION' ); ?>" rel="{handler: 'iframe', size: {x:window.getSize().x-100, y: window.getSize().y-100}}"><?php echo $viewimage; ?></a><a onclick="javascript:return clickRestore('index.php?option=com_flexicontent&task=items.edit&cid=<?php echo $this->row->id; ?>&version=<?php echo $version->nr; ?>&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1');" href="javascript:;" title="<?php echo JText::sprintf( 'FLEXI_REVERT_TO_THIS_VERSION', $version->nr ); ?>"><?php echo $revertimage; ?>
				<?php }?></td>
			</tr>
			<?php
				endif;
			endforeach;
			endif; ?>
		</table>
		</div>
		<div id="pager"></div>
		<div class="clear"></div>
		<?php endif; ?>
		<?php endif; ?>
		
		<table width="100%" style="border: 1px dashed silver; padding: 5px; margin-bottom: 10px;">
			<tr>	
				<th colspan="2" style="border-bottom: 1px dotted silver; padding-bottom: 5px;">
					<?php echo JText::_( 'FLEXI_CATEGORIES' ); ?>
					<span class="editlinktip hasTip" title="<?php echo JText::_ ( 'FLEXI_NOTES' ); ?>::<?php echo JText::_ ( 'FLEXI_CATEGORIES_NOTES' );?>">
					<?php echo $infoimage; ?>
					</span>
				</th>
			</tr>
			<tr>
				<td style="padding-top: 5px;">
					<label id="jform_catid-lbl" for="jform_catid">
					<strong><?php echo JText::_( 'FLEXI_CATEGORIES_MAIN' ); ?></strong>
					</label>
				</td>
				<td style="padding-top: 5px;">
					<?php echo $this->lists['catid']; ?>
				</td>
			</tr>
			<tr>
				<td style="padding-top: 5px;">
					<label id="jform_cid-lbl" for="jform_cid">
					<strong><?php echo JText::_( 'FLEXI_CATEGORIES' ); ?></strong>
					</label>
				</td>
				<td style="padding-top: 5px;">
					<?php echo $this->lists['cid']; ?>
				</td>
			</tr>
		</table>
		<?php echo JHtml::_('sliders.start','plugin-sliders-'.$this->row->id, array('useCookie'=>1)); ?>

		<?php
		echo JHtml::_('sliders.panel',JText::_('FLEXI_PUBLICATION_DETAILS'), 'details-options');
		/*if (isset($fieldSet->description) && trim($fieldSet->description)) :
			echo '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>';
		endif;*/
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
			<li><?php echo $this->form->getLabel('access');?>
			<?php echo $this->form->getInput('access');?></li>
			<li><?php echo $this->form->getLabel('created_by');?>
			<?php echo $this->form->getFieldAttribute('created_by', 'disabled') ? '<div class="fieldset_value">'.$this->row->creator.'</div>' : $this->form->getInput('created_by');?></li>
			<li><?php echo $this->form->getLabel('created_by_alias');?>
			<?php echo $this->form->getInput('created_by_alias');?></li>
			<li><?php echo $this->form->getLabel('created');?>
			<?php echo $this->form->getInput('created');?></li>
			<li><?php echo $this->form->getLabel('publish_up');?>
			<?php echo $this->form->getInput('publish_up');?></li>
			<li><?php echo $this->form->getLabel('publish_down');?>
			<?php echo $this->form->getInput('publish_down');?></li>
		</ul>
		</fieldset>

		<fieldset class="panelform">
			<?php

			echo JHtml::_('sliders.panel',JText::_('FLEXI_METADATA_INFORMATION'), "metadata-page");
			
			//echo $this->form->getLabel('metadesc');
			//echo $this->form->getInput('metadesc');
			//echo $this->form->getLabel('metakey');
			//echo $this->form->getInput('metakey');
			?>
		<fieldset class="panelform">
			<ul class="adminformlist">
				<li>
					<?php echo $this->form->getLabel('metadesc'); ?>

			<?php	if ( isset($this->row->item_translations) ) : ?>
				
				<!-- tabber start -->
				<div class="fctabber" style='display:inline-block;'>
					<div class="tabbertab" style="padding: 0px;" >
						<h3> <?php echo '-'.$itemlang.'-'; // $t->name; ?> </h3>
						<?php echo $this->form->getInput('metadesc');?>
					</div>
					<?php foreach ($this->row->item_translations as $t): ?>
						<?php if ($itemlang!=$t->shortcode && $t->shortcode!='*') : ?>
							<div class="tabbertab" style="padding: 0px;" >
								<h3> <?php echo $t->shortcode; // $t->name; ?> </h3>
								<?php
								$ff_id = 'jfdata_'.$t->shortcode.'_metadesc';
								$ff_name = 'jfdata['.$t->shortcode.'][metadesc]';
								?>
								<textarea id="<?php echo $ff_id; ?>" class="inputbox" rows="3" cols="26" name="<?php echo $ff_name; ?>"><?php echo @$t->fields->metadesc->value; ?></textarea>
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
			<?php	if ( isset($this->row->item_translations) ) :?>
			
				<!-- tabber start -->
				<div class="fctabber" style='display:inline-block;'>
					<div class="tabbertab" style="padding: 0px;" >
						<h3> <?php echo '-'.$itemlang.'-'; // $t->name; ?> </h3>
						<?php echo $this->form->getInput('metakey');?>
					</div>
					<?php foreach ($this->row->item_translations as $t): ?>
						<?php if ($itemlang!=$t->shortcode && $t->shortcode!='*') : ?>
							<div class="tabbertab" style="padding: 0px;" >
								<h3> <?php echo $t->shortcode; // $t->name; ?> </h3>
								<?php
								$ff_id = 'jfdata_'.$t->shortcode.'_metakey';
								$ff_name = 'jfdata['.$t->shortcode.'][metakey]';
								?>
								<textarea id="<?php echo $ff_id; ?>" class="inputbox" rows="3" cols="26" name="<?php echo $ff_name; ?>"><?php echo @$t->fields->metakey->value; ?></textarea>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<!-- tabber end -->
			
			<?php else : ?>
				<?php echo $this->form->getInput('metakey'); ?>
			<?php endif; ?>

				</li>
				
			<?php foreach($this->form->getGroup('metadata') as $field): ?>
				<?php if ($field->hidden): ?>
					<?php echo $field->input; ?>
				<?php else: ?>
					<?php echo $field->label; ?>
					<?php echo $field->input; ?>
				<?php endif; ?>
			<?php endforeach; ?>
		</fieldset>
		<?php
			$fieldSets = $this->form->getFieldsets('attribs');
			foreach ($fieldSets as $name => $fieldSet) :
				if ( $name=='themes' ) continue;

				$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_'.$name.'_FIELDSET_LABEL';
				echo JHtml::_('sliders.panel', JText::_('FLEXI_PARAMETERS') .": ". JText::_($label), $name.'-options');
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
		
		<?php	echo JHtml::_('sliders.end'); ?>
		
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
			<?php echo "<br><br>". JText::_( 'FLEXI_RECOMMEND_CONTENT_TYPE_LAYOUT' ); ?>
		</blockquote>
		
		<?php echo JHtml::_('sliders.start','template-sliders-'.$this->row->id, array('useCookie'=>1)); ?>
		<?php
			foreach ($this->tmpls as $tmpl) {
				$title = JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name;
				
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
		</td>
	</tr>
</table>
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
