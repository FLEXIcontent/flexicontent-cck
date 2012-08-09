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

defined('_JEXEC') or die('Restricted access');

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
			jQuery(\"#input-tags\").autocomplete(\"".JURI::base()."index.php?option=com_flexicontent&controller=items&task=viewtags&format=raw&".JUtility::getToken()."=1\", {
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
			jQuery.ajax({ url: \"index.php?option=com_flexicontent&controller=items&task=getversionlist&id=".$this->row->id."&active=".$this->version."&".JUtility::getToken()."=1&format=raw&page=\"+pageclickednumber, context: jQuery(\"#result\"), success: function(str){
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
    var hits = new itemscreen('hits', {id:<?php echo $this->row->id ? $this->row->id : 0; ?>, task:'gethits'});
    hits.fetchscreen();

    var votes = new itemscreen('votes', {id:<?php echo $this->row->id ? $this->row->id : 0; ?>, task:'getvotes'});
    votes.fetchscreen();
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
	
	var tag = new itemscreen();
	tag.addtag( id, tagname, 'index.php?option=com_flexicontent&controller=tags&task=addtag&format=raw&<?php echo JUtility::getToken();?>=1');
}

function reseter(task, id, div){
	var form = document.adminForm;
	
	if (task == 'resethits') {
		form.hits.value = 0;
	} else {
	}
		
	var res = new itemscreen();
	res.reseter( task, id, div, 'index.php?option=com_flexicontent&controller=items' );
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

<?php  /* echo "Version: ". $this->row->version."<br>\n"; */?>
<?php /* echo "id: ". $this->row->id."<br>\n"; */?>
<?php /* echo "type_id: ". @$this->row->type_id."<br>\n"; */?>


<div id="flexicontent" class="flexi_edit" >
<form action="index.php" method="post" enctype="multipart/form-data" name="adminForm" id="adminForm" autocomplete="off">
	<table cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td valign="top">
				<table  class="adminform">
					<tr>
						<td valign="top" width="340">
							<table cellspacing="0" cellpadding="0" border="0" width="100%">
								<tr>
									<td>
									
										<?php
											$field = $this->fields['title'];
											$label_tooltip = $field->description ? 'class="hasTip flexi_label" title="'.$field->label.'::'.$field->description.'"' : 'class="flexi_label"';
										?>
										<label id="title-lbl" for="title" <?php echo $label_tooltip; ?> >
											<?php echo $field->label.':'; ?>
											<?php /*echo JText::_( 'FLEXI_TITLE' ).':';*/ ?>
										</label>
									
									<?php	if ( isset($this->row->item_translations) ) :?>
									
										<!-- tabber start -->
										<div class="fctabber" style=''>
											<div class="tabbertab" style="padding: 0px;" >
												<h3> <?php echo '-'.$itemlang.'-'; // $t->name; ?> </h3>
												<input id="title" name="title" class="inputbox required" value="<?php echo $this->row->title; ?>" size="42" maxlength="254" />
											</div>
											<?php foreach ($this->row->item_translations as $t): ?>
												<?php if ($itemlang!=$t->shortcode) : ?>
													<div class="tabbertab" style="padding: 0px;" >
														<h3> <?php echo $t->shortcode; // $t->name; ?> </h3>
														<?php
														$ff_id = 'jfdata_'.$t->shortcode.'_title';
														$ff_name = 'jfdata['.$t->shortcode.'][title]';
														?>
														<input class="inputbox" style='margin:0px;' type="text" id="<?php echo $ff_id; ?>" name="<?php echo $ff_name; ?>" value="<?php echo @$t->fields->title->value; ?>" size="42" maxlength="254" />
													</div>
												<?php endif; ?>
											<?php endforeach; ?>
										</div>
										<!-- tabber end -->
										
									<?php else : ?>
										<input id="title" name="title" class="inputbox required" value="<?php echo $this->row->title; ?>" size="42" maxlength="254" />
									<?php endif; ?>
									
									</td>
								</tr>
								<tr>
									<td>
										
										<label id="alias-lbl" for="alias" class="flexi_label">
										<?php echo JText::_( 'FLEXI_ALIAS' ).':'; ?>
										</label>

									<?php	if ( isset($this->row->item_translations) ) :?>
									
										<!-- tabber start -->
										<div class="fctabber" style=''>
											<div class="tabbertab" style="padding: 0px;" >
												<h3> <?php echo '-'.$itemlang.'-'; // $t->name; ?> </h3>
												<input id="alias" name="alias" class="inputbox required" value="<?php echo $this->row->alias; ?>" size="42" maxlength="254" />
											</div>
											<?php foreach ($this->row->item_translations as $t): ?>
												<?php if ($itemlang!=$t->shortcode) : ?>
													<div class="tabbertab" style="padding: 0px;" >
														<h3> <?php echo $t->shortcode; // $t->name; ?> </h3>
														<?php
														$ff_id = 'jfdata_'.$t->shortcode.'_alias';
														$ff_name = 'jfdata['.$t->shortcode.'][alias]';
														?>
														<input class="inputbox" style='margin:0px;' type="text" id="<?php echo $ff_id; ?>" name="<?php echo $ff_name; ?>" value="<?php echo @$t->fields->alias->value; ?>" size="42" maxlength="254" />
													</div>
												<?php endif; ?>
											<?php endforeach; ?>
										</div>
										<!-- tabber end -->
										
									<?php else : ?>
										<input id="alias" name="alias" class="inputbox" value="<?php echo $this->row->alias; ?>" size="42" maxlength="254" />
									<?php endif; ?>
									
									</td>
								</tr>
								<tr>
									<td>
									
										<?php
											$field = $this->fields['document_type'];
											$label_tooltip = $field->description ? 'class="hasTip flexi_label" title="'.$field->label.'::'.$field->description.'"' : 'class="flexi_label"';
										?>
										<label for="type_id" <?php echo $label_tooltip; ?> >
											<?php echo $field->label.':'; ?>
											<?php /*echo JText::_( 'FLEXI_TYPE' ).':';*/ ?>
										</label>
										
										<?php echo $this->lists['type']; ?>
									
									</td>
								</tr>
								<tr>
									<td>
										
										<?php
											$field = $this->fields['state'];
											$label_tooltip = $field->description ? 'class="hasTip flexi_label" title="'.$field->label.'::'.$field->description.'"' : 'class="flexi_label"';
										?>
										<label id="state-lbl" for="state" <?php echo $label_tooltip; ?> >
											<?php echo $field->label.':'; ?>
											<?php /*echo JText::_( 'FLEXI_STATE' ).':';*/ ?>
										</label>
										
									<?php
									if ( $this->canPublish || $this->canPublishOwn ) :
										echo $this->lists['state'] . '&nbsp;';
										
										if (!$this->cparams->get('auto_approve', 1)) :
											echo "<br/>".JText::_('FLEXI_APPROVE_VERSION') . $this->lists['vstate'];
										else :
											echo '<input type="hidden" name="vstate" id="vstate" value="2" />';
										endif;
									else :
										echo $this->published;
										echo '<input type="hidden" name="state" id="vstate" value="'.$this->row->state.'" />';
											if (!$this->cparams->get('auto_approve', 1)) :
												// Enable approval if versioning disabled, this make sense,
												// since if use can edit item THEN item should be updated !!!
												$item_vstate = $this->cparams->get('use_versioning', 1) ? 1 : 2;
												echo '<input type="hidden" name="vstate" id="vstate" value="'.$item_vstate.'" />';
											else :
												echo '<input type="hidden" name="vstate" id="vstate" value="2" />';
											endif;
									endif;
									?>
										
									</td>
								</tr>

															
								<?php if ($this->subscribers) : ?>
								<tr>
									<td>
										<label id="jform_notify-lbl" for="jform_notify" class="flexi_label">
											<?php echo JText::_( 'FLEXI_NOTIFY_FAVOURING_USERS' ).':'; ?>
										</label>
										
										<input type="checkbox" name="notify" id="notify" />
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
						<td valign="top" align="left"">
							<div class="qf_tagbox" id="qf_tagbox">
								<ul id="ultagbox">
								<?php
									$nused = count($this->usedtags);
									for( $i = 0, $nused; $i < $nused; $i++ ) {
										$tag = $this->usedtags[$i];
										if ($this->CanUseTags) {
											echo '<li class="tagitem"><span>'.$tag->name.'</span>';
											echo '<input type="hidden" name="tag[]" value="'.$tag->tid.'" /><a href="#" class="deletetag" onclick="javascript:deleteTag(this);" align="right" title="'.JText::_('FLEXI_DELETE_TAG').'"></a></li>';
										} else {
											echo '<li class="tagitem plain"><span>'.$tag->name.'</span>';
											echo '<input type="hidden" name="tag[]" value="'.$tag->tid.'" /></li>';
										}
									}
									?>
								</ul>
							</div>
							
							<?php if ($this->CanUseTags) : ?>
							<div id="tags">
							
								<label for="input-tags" class="flexi_label">
									<?php echo JText::_( 'FLEXI_ADD_TAG' ); ?>
								</label>
								<input type="text" id="input-tags" name="tagname" tagid='0' tagname='' /><span id='input_new_tag'></span>
							
							</div>
							<?php endif; ?>
								
							<div style='clear:both; margin-bottom:12px;'></div>
							
							<?php if (FLEXI_FISH || FLEXI_J16GE) : ?>
							<div style='clear:both;'>
								<label for="language" class="flexi_label">
								<?php echo JText::_( 'FLEXI_LANGUAGE' ).':'; ?>
								</label>
								
								<?php echo $this->lists['languages']; ?>
							</div>
							<?php endif; ?>

							<?php if ($this->cparams->get('enable_translation_groups')) : ?>
								<div style='clear:both;'>
									<label for="lang_parent_id" class="flexi_label" >
										<?php echo JText::_( 'FLEXI_ORIGINAL_CONTENT_ITEM' );?>
										<span class="editlinktip hasTip" title="::<?php echo JText::_ ( 'FLEXI_ORIGINAL_CONTENT_ITEM_DESC' );?>">
											<?php echo JHTML::image ( 'components/com_flexicontent/assets/images/icon-16-hint.png', JText::_ ( 'FLEXI_ORIGINAL_CONTENT_ITEM' ) ); ?>
										</span>
									</label>
									<?php if ( $this->row->id  && (substr(flexicontent_html::getSiteDefaultLang(), 0,2) == substr($this->row->language, 0,2) || $this->row->language=='*') ) : ?>
										<br/><small><?php echo JText::_( $this->row->language=='*' ? 'FLEXI_ORIGINAL_CONTENT_ALL_LANGS' : 'FLEXI_ORIGINAL_TRANSLATION_CONTENT' );?></small>
										<input type="hidden" name="lang_parent_id" id="lang_parent_id" value="<?php echo $this->row->id; ?>" />
									<?php else : ?>
										<?php
											require_once(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'elements'.DS.'item.php');
											$attrs = array(
												'type'=>"item", 'label'=>"FLEXI_ORIGINAL_CONTENT_ITEM", 'description'=>"FLEXI_ORIGINAL_CONTENT_ITEM_DESC",
												'langparent_item'=>"1", 'type_id'=>$this->row->type_id, 'created_by'=>$this->row->created_by,
												'class'=>"inputbox", 'size'=>"6"
											);
											$jelement = new JSimpleXMLElement('lang_parent_id', $attrs);
											$ff_lang_parent_id = new JElementItem();
											echo '<small>'.JText::_( 'FLEXI_ORIGINAL_CONTENT_IGNORED_IF_DEFAULT_LANG' ).'</small><br>';
											echo $ff_lang_parent_id->fetchElement('lang_parent_id', $this->row->lang_parent_id, $jelement, '');
										?>
									<?php endif; ?>
								</div>
							<?php endif; ?>
							
						</td>
					</tr>
				</table>

				<?php
				if (FLEXI_ACCESS && $this->canRight && $this->row->id) :
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

				<?php
				if ($this->fields && $this->row->type_id) {
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
						<?php echo $this->row->type_id ? JText::_( 'FLEXI_ITEM_TYPE' ) . ' : ' . $this->typesselected->name : JText::_( 'FLEXI_TYPE_NOT_DEFINED' ); ?>
					</legend>
					
					<!--table class="admintable" width="100%"-->
						<?php
						$hidden = array('fcloadmodule', 'fcpagenav', 'toolbar');
						
						foreach ($this->fields as $field) {
							
							// SKIP backend hidden fields from this listing
							if ( ($field->iscore && $field->field_type!='maintext')  ||  $field->parameters->get('backend_hidden')  ||  in_array($field->field_type, $hidden)  ||  in_array($field->formhidden, array(2,3)) ) continue;
							
							// check to SKIP (hide) field e.g. description field ('maintext'), alias field etc
							if ( $this->tparams->get('hide_'.$field->field_type) ) continue;
							
							// -- Tooltip for the current field label
							$label_tooltip = $field->description ? 'class="flexi_label hasTip" title="'.$field->label.'::'.$field->description.'"' : ' class="flexi_label" ';
							$label_style = ""; //( $field->field_type == 'maintext' || $field->field_type == 'textarea' ) ? " style='clear:both; float:none;' " : "";
							$not_in_tabs = "";
							
							if ($field->field_type=='groupmarker') :
								echo $field->html;
								continue;
							endif;
						?>
							<!--tr-->
								<!--td class="fcfield-row" style='padding:0px 2px 0px 2px; border: 0px solid lightgray;'-->
									<div class='clear' style='display:block; float:left; clear:both!important'></div>
									
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
				<div id="hits" style="display:none;"></div><input id="hits" type="text" name="hits" size="6" value="<?php echo $this->row->hits; ?>" />
				<span <?php echo $visibility; ?>>
					<input name="reset_hits" type="button" class="button" value="<?php echo JText::_( 'FLEXI_RESET' ); ?>" onclick="reseter('resethits', '<?php echo $this->row->id; ?>', 'hits')" />
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
				<div id="votes" style="float:left;"></div>
				<span <?php echo $visibility2; ?>>
					<input name="reset_votes" type="button" class="button" value="<?php echo JText::_( 'FLEXI_RESET' ); ?>" onclick="reseter('resetvotes', '<?php echo $this->row->id; ?>', 'votes')" />
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
				<?php echo $this->lastversion;?> <?php echo JText::_( 'FLEXI_TIMES' ); ?>
			</td>
		</tr>
		<tr>
			<td>
				<strong><?php echo JText::_( 'FLEXI_CURRENT_VERSION' ); ?></strong>
			</td>
			<td>
				#<?php echo $this->row->version;?>
			</td>
		</tr>
		<tr>
			<td>
				<strong><?php echo JText::_( 'FLEXI_WORKING_VERSION' ); ?></strong>
			</td>
			<td>
				#<?php echo $this->version?$this->version:$this->row->version;?>
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
				<td><textarea name="versioncomment" id="versioncomment" style="width: 300px; height: 30px; line-height:1"></textarea></td>
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
			$date_format = (($date_format = JText::_( 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS' )) == 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS') ? "%d/%m %H:%M" : $date_format;
			$ctrl_task = FLEXI_J16GE ? 'task=items.edit' : 'controller=items&task=edit';
			foreach ($this->versions as $version) :
				$class = ($version->nr == $this->version) ? ' class="active-version"' : '';
				if ((int)$version->nr > 0) :
			?>
			<tr<?php echo $class; ?>>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo '#' . $version->nr; ?></span></td>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo JHTML::_('date', (($version->nr == 1) ? $this->row->created : $version->date), $date_format ); ?></span></td>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo ($version->nr == 1) ? flexicontent_html::striptagsandcut($this->row->creator, 25) : flexicontent_html::striptagsandcut($version->modifier, 25); ?></span></td>
				<td class="versions" align="center"><a href="javascript:;" class="hasTip" title="Comment::<?php echo $version->comment;?>"><?php echo $commentimage;?></a><?php
				if((int)$version->nr==(int)$this->row->version) {//is current version? ?>
					<a onclick="javascript:return clickRestore('index.php?option=com_flexicontent&view=item&<?php echo $ctrl_task;?>&cid=<?php echo $this->row->id;?>&version=<?php echo $version->nr; ?>');" href="#"><?php echo JText::_( 'FLEXI_CURRENT' ); ?></a>
				<?php }else{
				?>
					<a class="modal-versions" href="index.php?option=com_flexicontent&view=itemcompare&cid[]=<?php echo $this->row->id; ?>&version=<?php echo $version->nr; ?>&tmpl=component" title="<?php echo JText::_( 'FLEXI_COMPARE_WITH_CURRENT_VERSION' ); ?>" rel="{handler: 'iframe', size: {x:window.getSize().scrollSize.x-100, y: window.getSize().size.y-100}}"><?php echo $viewimage; ?></a><a onclick="javascript:return clickRestore('index.php?option=com_flexicontent&controller=items&task=edit&cid=<?php echo $this->row->id; ?>&version=<?php echo $version->nr; ?>&<?php echo JUtility::getToken();?>=1');" href="#" title="<?php echo JText::sprintf( 'FLEXI_REVERT_TO_THIS_VERSION', $version->nr ); ?>"><?php echo $revertimage; ?>
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
					<label for="catid">
					<strong><?php echo JText::_( 'FLEXI_CATEGORIES_MAIN' ); ?></strong>
					</label>
				</td>
				<td style="padding-top: 5px;">
					<?php echo $this->lists['catid']; ?>
				</td>
			</tr>
			<tr>
				<td style="padding-top: 5px;">
					<label for="cid">
					<strong><?php echo JText::_( 'FLEXI_CATEGORIES' ); ?></strong>
					</label>
				</td>
				<td style="padding-top: 5px;">
					<?php echo $this->lists['cid']; ?>
				</td>
			</tr>
		</table>

		<?php
			echo $this->pane->startPane( 'det-pane' );
			
			$title = JText::_( 'FLEXI_PUBLICATION_DETAILS' );
			echo $this->pane->startPanel( $title, 'details' );
			echo $this->formparams->render('details');
			echo $this->pane->endPanel();
		?>
		
		
	<?php
		$title = JText::_( 'FLEXI_METADATA_INFORMATION' );
		echo $this->pane->startPanel( $title, 'metadata' );	
	?>
	<table class="paramlist admintable" width="100%" cellspacing="1" style="height:">
		<tbody>
		<tr>
			<td class="paramlist_key" width="40%">
				<span class="editlinktip">
					<label id="metadescription-lbl" class="hasTip" for="metadescription" title="::<?php echo JText::_('FLEXI_METADESC'); ?>" >
						<?php echo JText::_('FLEXI_Description'); ?>
					</label>
				</span>
			</td>
			<td class="paramlist_value">
			
			<?php	if ( isset($this->row->item_translations) ) :?>
				
				<!-- tabber start -->
				<div class="fctabber" style='display:inline-block;'>
					<div class="tabbertab" style="padding: 0px;" >
						<h3> <?php echo '-'.$itemlang.'-'; // $t->name; ?> </h3>
						<textarea id="metadescription" class="text_area" rows="5" cols="27" name="meta[description]"><?php echo $this->formparams->get('description'); ?></textarea>
					</div>
					<?php foreach ($this->row->item_translations as $t): ?>
						<?php if ($itemlang!=$t->shortcode) : ?>
							<div class="tabbertab" style="padding: 0px;" >
								<h3> <?php echo $t->shortcode; // $t->name; ?> </h3>
								<?php
								$ff_id = 'jfdata_'.$t->shortcode.'_metadesc';
								$ff_name = 'jfdata['.$t->shortcode.'][metadesc]';
								?>
								<textarea id="<?php echo $ff_id; ?>" class="text_area" rows="5" cols="27" name="<?php echo $ff_name; ?>"><?php echo @$t->fields->metadesc->value; ?></textarea>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<!-- tabber end -->
			
			<?php else : ?>
				<textarea id="metadescription" class="text_area" rows="5" cols="27" name="meta[description]"><?php echo $this->formparams->get('description'); ?></textarea>
			<?php endif; ?>
			
			</td>
		</tr>
			
		<tr>
			<td class="paramlist_key" width="40%">
				<span class="editlinktip">
					<label id="metakeywords-lbl" class="hasTip" for="metakeywords" title="::<?php echo JText::_('FLEXI_METAKEYS'); ?>" >
						<?php echo JText::_('FLEXI_Keywords'); ?>
					</label>
				</span>
			</td>
			<td class="paramlist_value">
			
			<?php	if ( isset($this->row->item_translations) ) :?>
			
				<!-- tabber start -->
				<div class="fctabber" style='display:inline-block;'>
					<div class="tabbertab" style="padding: 0px;" >
						<h3> <?php echo '-'.$itemlang.'-'; // $t->name; ?> </h3>
						<textarea id="metakeywords" class="text_area" rows="5" cols="27" name="meta[keywords]"><?php echo $this->formparams->get('keywords'); ?></textarea>
					</div>
					<?php foreach ($this->row->item_translations as $t): ?>
						<?php if ($itemlang!=$t->shortcode) : ?>
							<div class="tabbertab" style="padding: 0px;" >
								<h3> <?php echo $t->shortcode; // $t->name; ?> </h3>
								<?php
								$ff_id = 'jfdata_'.$t->shortcode.'_metakey';
								$ff_name = 'jfdata['.$t->shortcode.'][metakey]';
								?>
								<textarea id="<?php echo $ff_id; ?>" class="text_area" rows="5" cols="27" name="<?php echo $ff_name; ?>"><?php echo @$t->fields->metakey->value; ?></textarea>
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<!-- tabber end -->
			
			<?php else : ?>
				<textarea id="metakeywords" class="text_area" rows="5" cols="27" name="meta[keywords]"><?php echo $this->formparams->get('keywords'); ?></textarea>
			<?php endif; ?>
			
				</td>
			</tr>
		</table>
		
			<?php
			echo $this->formparams->render('meta', 'metadata');
			echo $this->pane->endPanel();
			?>
		
	
		<?php
			$title = JText::_('FLEXI_PARAMETERS') .": ". JText::_( 'FLEXI_PARAMETERS_ITEM_BASIC' );
			echo $this->pane->startPanel( $title, "params-basic" );
			echo $this->formparams->render('params', 'basic');
			echo $this->pane->endPanel();

			$title = JText::_('FLEXI_PARAMETERS') .": ". JText::_( 'FLEXI_PARAMETERS_ITEM_ADVANCED' );
			echo $this->pane->startPanel( $title, "params-advanced" );
			echo $this->formparams->render('params', 'advanced');
			echo $this->pane->endPanel();

			$title = JText::_('FLEXI_PARAMETERS') .": ". JText::_( 'FLEXI_PARAMETERS_ITEM_SEOCONF' );
			echo $this->pane->startPanel( $title, "params-seoconf" );
			echo $this->formparams->render('params', 'seoconf');
			echo $this->pane->endPanel();

			echo '<h3 class="themes-title">' . JText::_( 'FLEXI_PARAMETERS_LAYOUT_THEMES' ) . '</h3>';
			$type_default_layout = $this->tparams->get('ilayout');	
			echo $this->formparams->render('params', 'themes');
		?>
		
		<blockquote id='__content_type_default_layout__'>
			<?php echo JText::sprintf( 'FLEXI_USING_CONTENT_TYPE_LAYOUT', $type_default_layout ); ?>
			<?php echo "<br><br>". JText::_( 'FLEXI_RECOMMEND_CONTENT_TYPE_LAYOUT' ); ?>
		</blockquote>
		
		<?php
			foreach ($this->tmpls as $tmpl) {
				$title = JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name;
				
				echo $this->pane->startPanel( $title, "params-".$tmpl->name );
				echo $tmpl->params->render();
				echo $this->pane->endPanel();
			}

			echo $this->pane->endPane();
		?>
		</td>
	</tr>
</table>
<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
<input type="hidden" name="controller" value="items" />
<input type="hidden" name="view" value="item" />
<input type="hidden" name="task" value="" />
<!--input type="hidden" name="hits" value="<?php echo $this->row->hits; ?>" /-->
<?php if (!FLEXI_FISH) : ?>
<input type="hidden" name="language" value="<?php echo $this->row->language; ?>" />
<?php endif; ?>
</form>

</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>