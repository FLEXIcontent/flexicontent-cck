<?php
/**
 * @version 1.5 stable $Id: default.php 376 2010-08-24 04:12:01Z enjoyman $
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
if ($this->permission->CanUseTags) {
	$this->document->addScript('components/com_flexicontent/assets/jquery-autocomplete/jquery.bgiframe.min.js');
	$this->document->addScript('components/com_flexicontent/assets/jquery-autocomplete/jquery.ajaxQueue.js');
	$this->document->addScript('components/com_flexicontent/assets/jquery-autocomplete/jquery.autocomplete.min.js');
	$this->document->addScript('components/com_flexicontent/assets/js/jquery.pager.js');
	
	$this->document->addStyleSheet('components/com_flexicontent/assets/jquery-autocomplete/jquery.autocomplete.css');
	$this->document->addStyleSheet('components/com_flexicontent/assets/css/Pager.css');
	$this->document->addScriptDeclaration("
		jQuery(document).ready(function () {
			jQuery(\"#input-tags\").autocomplete(\"".JURI::base()."index.php?option=com_flexicontent&task=items.viewtags&format=raw&".JUtility::getToken()."=1\", {
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
		});
		jQuery(document).ready(function() {
			jQuery(\"#pager\").pager({ pagenumber: ".$this->current_page.", pagecount: ".$this->pagecount.", buttonClickCallback: PageClick });
		});

		PageClick = function(pageclickednumber) {
			jQuery.ajax({ url: \"index.php?option=com_flexicontent&task=items.getversionlist&id=".$this->form->getValue("id")."&active=".$this->form->getValue("version")."&".JUtility::getToken()."=1&tmpl=component&page=\"+pageclickednumber, context: jQuery(\"#result\"), success: function(str){
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
    var hits = new itemscreen('hits', {id:<?php echo $this->form->getValue('id') ? $this->form->getValue('id') : 0; ?>, task:'items.gethits'});
    hits.fetchscreen();

    var votes = new itemscreen('votes', {id:<?php echo $this->form->getValue('id') ? $this->form->getValue('id') : 0; ?>, task:'items.getvotes'});
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
	tag.addtag( id, tagname, 'index.php?option=com_flexicontent&task=tags.addtag&tmpl=component&<?php echo JUtility::getToken();?>=1');
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
	parent = $($(obj).getParent());
	parent.dispose();
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
//Set the info image
$infoimage 	= JHTML::image ( 'administrator/components/com_flexicontent/assets/images/lightbulb.png', JText::_( 'FLEXI_NOTES' ) );
$revert 	= JHTML::image ( 'administrator/components/com_flexicontent/assets/images/arrow_rotate_anticlockwise.png', JText::_( 'FLEXI_REVERT' ) );
$view 		= JHTML::image ( 'administrator/components/com_flexicontent/assets/images/magnifier.png', JText::_( 'FLEXI_VIEW' ) );
$comment 	= JHTML::image ( 'administrator/components/com_flexicontent/assets/images/comment.png', JText::_( 'FLEXI_COMMENT' ) );
?>

<div class="flexicontent">
<form action="index.php" method="post" enctype="multipart/form-data" name="adminForm" id="adminForm" autocomplete="off">
	<table cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td valign="top">
				<table  class="adminform">
					<tr>
						<td valign="top">
							<table cellspacing="0" cellpadding="0" border="0" width="100%">
								<tr>
									<td>
										<?php echo $this->form->getLabel('title');?>
									</td>
									<td>
										<?php echo $this->form->getInput('title');?>
									</td>
								</tr>
								<tr>
									<td>
										<?php echo $this->form->getLabel('alias');?>
									</td>
									<td>
										<?php echo $this->form->getInput('alias');?>
									</td>
								</tr>
								<tr>
									<td>
										<?php echo $this->form->getLabel('type_id');?>
									</td>
									<td>
										<?php echo $this->form->getInput('type_id');?>
									</td>
								</tr>
								<tr>
									<td>
										<label for="published">
										<?php echo JText::_( 'FLEXI_STATE' ).':'; ?>
										</label>
									</td>
									<td>
									<?php
									if (($this->canPublish || $this->canPublishOwn) && ($this->form->getValue("id"))) :
										//echo $this->lists['state'] . '&nbsp;&nbsp;&nbsp;';
										echo $this->form->getInput('state');
										if (!$this->cparams->get('auto_approve', 1)) : ?>
									</td>
								</tr>
								<tr>
									<td>
										<?php echo $this->form->getLabel('vstate'); ?>
									</td>
									<td>
										<?php echo $this->form->getInput('vstate'); ?>
									</td>
								</tr>
										<?php else :
											echo '<input type="hidden" name="jform[vstate]" value="2" />';
										endif;
									else :
										echo $this->published;
										echo '<input type="hidden" name="jform[state]" value="'.$this->form->getValue("state").'" />';
											if (!$this->cparams->get('auto_approve', 1)) :
												echo '<input type="hidden" name="jform[vstate]" value="1" />';
											else :
												echo '<input type="hidden" name="jform[vstate]" value="2" />';
											endif;
									endif;
									?>
									</td>
								</tr>
								<?php if ($this->subscribers) : ?>
								<tr>
									<td>
										<label for="notify">
										<?php echo JText::_( 'FLEXI_NOTIFY' ).':'; ?>
										</label>
									</td>
									<td>
										<input type="checkbox" name="notify" id="notify" />
										<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_NOTES' ); ?>::<?php echo JText::_( 'FLEXI_NOTIFY_NOTES' );?>">
										<?php echo $infoimage; ?>
										</span>
										(<?php echo $this->subscribers . ' ' . (($this->subscribers > 1) ? JText::_( 'FLEXI_SUBSCRIBERS' ) : JText::_( 'FLEXI_SUBSCRIBER' )); ?>)
									</td>
								</tr>
								<?php endif; ?>
							</table>
						</td>
						<td valign="top" width="50%">
							<table cellspacing="0" cellpadding="0" border="0" width="100%">
							<tr>
							<td colspan="2">
							<div class="qf_tagbox" id="qf_tagbox">
								<ul id="ultagbox">
<?php
									$nused = count($this->usedtags);
									for( $i = 0, $nused; $i < $nused; $i++ ) {
										$tag = $this->usedtags[$i];
										if ($this->permission->CanUseTags) {
											echo '<li class="tagitem"><span>'.$tag->name.'</span>';
											echo '<input type="hidden" name="jform[tag][]" value="'.$tag->tid.'" /><a href="javascript:;" class="deletetag" onclick="javascript:deleteTag(this);" align="right" title="'.JText::_('FLEXI_DELETE_TAG').'"></a></li>';
										} else {
											echo '<li class="tagitem"><span>'.$tag->name.'</span>';
											echo '<input type="hidden" name="jform[tag][]" value="'.$tag->tid.'" /><a href="javascript:;" class="deletetag" align="right"></a></li>';
										}
									}
?>
								</ul>
								<br class="clear" />
							</div>
							<?php if ($this->permission->CanUseTags) : ?>
							<div id="tags">
								<label for="input-tags"><?php echo JText::_( 'FLEXI_ADD_TAG' ); ?>
									<input type="text" id="input-tags" name="tagname" tagid='0' tagname='' />
								</label>
							</div>
							<?php endif; ?>
							</td>
							</tr>
							<tr>
								<td><label for="notify">
								<?php echo $this->form->getLabel('language'); ?>
								</label></td>
								<td><?php echo $this->form->getInput('language');?></td>
							</tr>
							</table>
						</td>
					</tr>
				</table>

				<?php
				if ($this->permission->CanConfig) :
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
						<?php echo $this->form->getValue("type_id") ? JText::_( 'FLEXI_ITEM_TYPE' ) . ' : ' . $this->fieldtype->name : JText::_( 'FLEXI_TYPE_NOT_DEFINED' ); ?>
					</legend>
					
					<table class="admintable" width="100%">
						<?php
						foreach ($this->fields as $field) {
							// used to hide the core fields from this listing
							if ( (!$field->iscore || ($field->field_type == 'maintext' && (!$this->tparams->get('hide_maintext')))) && !$field->parameters->get('backend_hidden') ) {
							// set the specific label for the maintext field
								if ($field->field_type == 'maintext') {
									$field->label = $this->tparams->get('maintext_label', $field->label);
									$field->description = $this->tparams->get('maintext_desc', $field->description);
									$maintext = @$field->value[0];
									if ($this->tparams->get('hide_html', 0)) {
										$field->html = '<textarea name="jform[text]" id="jform_text" rows="20" cols="75">'.$maintext.'</textarea>';
									} else {
										$height = $this->tparams->get('height', 400);
										$editor = & JFactory::getEditor();
										$field->html = $editor->display( 'jform[text]', $maintext, '100%', $height, '75', '20', array('pagebreak'), 'jform_text' ) ;
									}
								}
						?>
						<tr>
							<td class="key">
							<?php if ($field->description) : ?>
								<label for="<?php echo $field->name; ?>" class="hasTip" title="<?php echo $field->label; ?>::<?php echo $field->description; ?>">
									<?php echo $field->label; ?>
								</label>
							<?php else : ?>
								<label for="<?php echo $field->name; ?>">
									<?php echo $field->label; ?>
								</label>
							<?php endif; ?>
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
				} else if ($this->form->getValue("id") == 0) {
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
		if ( !$this->form->getValue("hits") ) {
			$visibility = 'style="display: none; visibility: hidden;"';
		} else {
			$visibility = '';
		}
		
		if ( !$this->form->getValue("score") ) {
			$visibility2 = 'style="display: none; visibility: hidden;"';
		} else {
			$visibility2 = '';
		}

		?>
		<table width="100%" style="border: 1px dashed silver; padding: 5px; margin-bottom: 10px;">
		<?php
		if ( $this->form->getValue("id") ) {
		?>
		<tr>
			<td>
				<strong><?php echo JText::_( 'FLEXI_ITEM_ID' ); ?>:</strong>
			</td>
			<td>
				<?php echo $this->form->getValue("id"); ?>
			</td>
		</tr>
		<?php
		}
		?>
		<tr>
			<td>
				<strong><?php echo JText::_( 'FLEXI_STATE' ); ?></strong>
			</td>
			<td>
				<?php echo $this->published;?>
			</td>
		</tr>
		<tr>
			<td>
				<strong><?php echo JText::_( 'FLEXI_HITS' ); ?></strong>
			</td>
			<td>
				<div id="hits"></div>
				<span <?php echo $visibility; ?>>
					<input name="reset_hits" type="button" class="button" value="<?php echo JText::_( 'FLEXI_RESET' ); ?>" onclick="reseter('items.resethits', '<?php echo $this->form->getValue('id'); ?>', 'hits')" />
				</span>
			</td>
		</tr>
		<tr>
			<td>
				<strong><?php echo JText::_( 'FLEXI_SCORE' ); ?></strong>
			</td>
			<td>
				<div id="votes"></div>
				<span <?php echo $visibility2; ?>>
					<input name="reset_votes" type="button" class="button" value="<?php echo JText::_( 'FLEXI_RESET' ); ?>" onclick="reseter('items.resetvotes', '<?php echo $this->form->getValue('id'); ?>', 'votes')" />
				</span>
			</td>
		</tr>
		<tr>
			<td>
				<strong><?php echo JText::_( 'FLEXI_REVISED' ); ?></strong>
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
				#<?php echo $this->form->getValue('version');?>
			</td>
		</tr>
		<tr>
			<td>
				<strong><?php echo JText::_( 'FLEXI_WORKING_VERSION' ); ?></strong>
			</td>
			<td>
				#<?php echo $this->version?$this->version:$this->form->getValue('version');?>
			</td>
		</tr>
		<tr>
			<td>
				<strong><?php echo JText::_( 'FLEXI_CREATED' ); ?></strong>
			</td>
			<td>
				<?php
				if ( $this->form->getValue('created') == $this->nullDate ) {
					echo JText::_( 'FLEXI_NEW_ITEM' );
				} else {
					echo JHTML::_('date',  $this->form->getValue('created'),  JText::_( 'DATE_FORMAT_LC2' ) );
				}
				?>
			</td>
		</tr>
		<tr>
			<td>
				<strong><?php echo JText::_( 'FLEXI_MODIFIED' ); ?></strong>
			</td>
			<td>
				<?php
					if ( $this->form->getValue('modified') == $this->nullDate ) {
						echo JText::_( 'FLEXI_NOT_MODIFIED' );
					} else {
						echo JHTML::_('date',  $this->form->getValue('modified'), JText::_( 'DATE_FORMAT_LC2' ));
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
		<?php if ($this->permission->CanVersion) : ?>
		<div id="result" >
		<table width="100%" style="border: 1px dashed silver; padding: 5px; margin-bottom: 5px;" cellpadding="0" cellspacing="0">
			<tr>
				<th style="border-bottom: 1px dotted silver; padding: 2px 0 6px 0;" colspan="4"><?php echo JText::_( 'FLEXI_VERSIONS_HISTORY' ); ?></th>
			</tr>
			<?php if ($this->form->getValue('id') == 0) : ?>
			<tr>
				<td class="versions-first" colspan="4"><?php echo JText::_( 'FLEXI_NEW_ARTICLE' ); ?></td>
			</tr>
			<?php
			else :
			JHTML::_('behavior.modal', 'a.modal-versions');
			foreach ($this->versions as $version) :
				$class = ($version->nr == $this->version) ? ' class="active-version"' : '';
				if ((int)$version->nr > 0) :
			?>
			<tr<?php echo $class; ?>>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo '#' . $version->nr; ?></span></td>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo JHTML::_('date', (($version->nr == 1) ? $this->form->getValue('created') : $version->date), JText::_( 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS' )); ?></span></td>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo ($version->nr == 1) ? flexicontent_html::striptagsandcut($this->form->getValue('creator'), 25) : flexicontent_html::striptagsandcut($version->modifier, 25); ?></span></td>
				<td class="versions" align="center"><a href="javascript:;" class="hasTip" title="Comment::<?php echo $version->comment;?>"><?php echo $comment;?></a><?php
				if((int)$version->nr==(int)$this->form->getValue('version')) {//is current version? ?>
					<a onclick="javascript:return clickRestore('index.php?option=com_flexicontent&view=item&cid=<?php echo $this->form->getValue('id');?>&version=<?php echo $version->nr; ?>');" href="javascript:;"><?php echo JText::_( 'FLEXI_CURRENT' ); ?></a>
				<?php }else{
				?>
					<a class="modal-versions" href="index.php?option=com_flexicontent&view=itemcompare&cid[]=<?php echo $this->form->getValue('id'); ?>&version=<?php echo $version->nr; ?>&tmpl=component" title="<?php echo JText::_( 'FLEXI_COMPARE_WITH_CURRENT_VERSION' ); ?>" rel="{handler: 'iframe', size: {x:window.getSize().x-100, y: window.getSize().y-100}}"><?php echo $view; ?></a><a onclick="javascript:return clickRestore('index.php?option=com_flexicontent&task=items.edit&cid=<?php echo $this->form->getValue('id'); ?>&version=<?php echo $version->nr; ?>&<?php echo JUtility::getToken();?>=1');" href="javascript:;" title="<?php echo JText::sprintf( 'FLEXI_REVERT_TO_THIS_VERSION', $version->nr ); ?>"><?php echo $revert; ?>
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
					<label for="cid">
					<strong><?php echo JText::_( 'FLEXI_CATEGORIES' ); ?></strong>
					</label>
				</td>
				<td style="padding-top: 5px;">
					<?php echo $this->form->getInput('cid');?>
				</td>
			</tr>
			<tr>
				<td style="padding-top: 5px;">
					<label for="catid">
					<strong><?php echo JText::_( 'FLEXI_CATEGORIES_MAIN' ); ?></strong>
					</label>
				</td>
				<td style="padding-top: 5px;">
					<?php //echo $this->lists['catid']; ?>
					<?php echo $this->form->getInput('catid');?>
				</td>
			</tr>
		</table>
		<?php echo JHtml::_('sliders.start','plugin-sliders-'.$this->form->getValue("id"), array('useCookie'=>1)); ?>

		<?php
		echo JHtml::_('sliders.panel',JText::_('FLEXI_DETAILS'), 'details-options');
		/*if (isset($fieldSet->description) && trim($fieldSet->description)) :
			echo '<p class="tip">'.$this->escape(JText::_($fieldSet->description)).'</p>';
		endif;*/
		?>
		<fieldset class="panelform">
		<ul class="adminformlist">
			<li><?php echo $this->form->getLabel('access');?>
			<?php echo $this->form->getInput('access');?></li>
			<li><?php echo $this->form->getLabel('created_by');?>
			<?php echo $this->form->getInput('created_by');?></li>
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

		<?php
		echo JHtml::_('sliders.panel',JText::_('FLEXI_METADATA_INFORMATION'), "metadata-page");
		//echo JHtml::_('sliders.panel',JText::_('FLEXI_PARAMETERS_STANDARD'), "params-page");
		?>
		<fieldset class="panelform">
			<?php echo $this->form->getLabel('metadesc'); ?>
			<?php echo $this->form->getInput('metadesc'); ?>

			<?php echo $this->form->getLabel('metakey'); ?>
			<?php echo $this->form->getInput('metakey'); ?>
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
			$label = !empty($fieldSet->label) ? $fieldSet->label : 'FLEXI_'.$name.'_FIELDSET_LABEL';
			echo JHtml::_('sliders.panel',JText::_($label), $name.'-options');
			?>
			<fieldset class="panelform">
				<?php foreach ($this->form->getFieldset($name) as $field) : ?>
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
		</td>
	</tr>
</table>
<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="jform[id]" value="<?php echo $this->form->getValue('id'); ?>" />
<input type="hidden" name="controller" value="items" />
<input type="hidden" name="view" value="item" />
<input type="hidden" name="task" value="" />
<?php echo $this->form->getInput('hits'); ?>
<input type="hidden" name="oldtitle" value="<?php echo $this->form->getValue('title'); ?>" />
<input type="hidden" name="oldtext" value="<?php //echo $this->form->getValue('text'); ?>" />
<input type="hidden" name="oldstate" value="<?php echo $this->form->getValue('state'); ?>" />
<input type="hidden" name="oldmodified" value="<?php echo $this->form->getValue('modified'); ?>" />
<input type="hidden" name="oldmodified_by" value="<?php echo $this->form->getValue('modified_by'); ?>" />
</form>

</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
