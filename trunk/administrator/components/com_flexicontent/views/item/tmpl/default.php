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

require_once(JPATH_SITE.DS.'components'.DS.'com_flexicontent'.DS.'classes'.DS.'flexicontent.fields.php');

$this->document->addScript('components/com_flexicontent/assets/js/jquery.autogrow.js');
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
	if (navigator.appVersion.indexOf("MSIE") == -1) {
		var parent = $($(obj).getParent());
		parent.remove();
	} else {
		var parent = obj.parentNode;
		parent.innerHTML = "";
		parent.removeNode(true);
	}
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
										<label for="title">
										<?php echo JText::_( 'FLEXI_TITLE' ).':'; ?>
										</label>
									</td>
									<td>
										<input id="title" name="title" class="required" value="<?php echo $this->row->title; ?>" size="50" maxlength="254" />
									</td>
								</tr>
								<tr>
									<td>
										<label for="alias">
										<?php echo JText::_( 'FLEXI_ALIAS' ).':'; ?>
										</label>
									</td>
									<td>
										<input class="inputbox" type="text" name="alias" id="alias" size="50" maxlength="254" value="<?php echo $this->row->alias; ?>" />
									</td>
								</tr>
								<tr>
									<td>
										<label for="type_id">
										<?php echo JText::_( 'FLEXI_TYPE' ).':'; ?>
										</label>
									</td>
									<td>
									<?php echo $this->lists['type']; ?>
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
									if (($this->canPublish || $this->canPublishOwn) && ($this->row->id)) :
										echo $this->lists['state'] . '&nbsp;';
										if (!$this->cparams->get('auto_approve', 1)) :
											echo JText::_('FLEXI_APPROVE_VERSION') . $this->lists['vstate'];
										else :
											echo '<input type="hidden" name="vstate" value="2" />';
										endif;
									else :
										echo $this->published;
										echo '<input type="hidden" name="state" value="'.$this->row->state.'" />';
											if (!$this->cparams->get('auto_approve', 1)) :
												echo '<input type="hidden" name="vstate" value="1" />';
											else :
												echo '<input type="hidden" name="vstate" value="2" />';
											endif;
									endif;
									?>
									</td>
								</tr>
								<?php if (FLEXI_FISH || FLEXI_J16GE) : ?>
								<tr>
									<td>
										<label for="language">
										<?php echo JText::_( 'FLEXI_LANGUAGE' ).':'; ?>
										</label>
									</td>
									<td>
									<?php echo $this->lists['languages']; ?>
									</td>
								</tr>
								<?php endif; ?>
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
											echo '<li class="tagitem"><span>'.$tag->name.'</span>';
											echo '<input type="hidden" name="tag[]" value="'.$tag->tid.'" /><a href="#" class="deletetag" align="right"></a></li>';
										}
									}
									?>
								</ul>
<!-- 								<br class="clear" /> -->
							</div>
							<?php if ($this->CanUseTags) : ?>
							<div id="tags">
								<label for="input-tags"><?php echo JText::_( 'FLEXI_ADD_TAG' ); ?>
									<input type="text" id="input-tags" name="tagname" tagid='0' tagname='' />
								</label>
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
						<?php echo $this->row->typename ? JText::_( 'FLEXI_ITEM_TYPE' ) . ' : ' . $this->row->typename : JText::_( 'FLEXI_TYPE_NOT_DEFINED' ); ?>
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
									$maintext = ($this->version!=$this->row->version)?@$field->value[0]:$this->row->text;
									if ($this->tparams->get('hide_html', 0))
									{
										$field->html = '<textarea name="text" rows="20" cols="75">'.$maintext.'</textarea>';
									} else {
										$height = $this->tparams->get('height', 400);
										$field->html = $this->editor->display( 'text', $maintext, '100%', $height, '75', '20' ) ;
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
			<td valign="top" width="320px" style="padding: 7px 0 0 5px">
			
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
					<input name="reset_hits" type="button" class="button" value="<?php echo JText::_( 'FLEXI_RESET' ); ?>" onclick="reseter('resethits', '<?php echo $this->row->id; ?>', 'hits')" />
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
					<input name="reset_votes" type="button" class="button" value="<?php echo JText::_( 'FLEXI_RESET' ); ?>" onclick="reseter('resetvotes', '<?php echo $this->row->id; ?>', 'votes')" />
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
				<strong><?php echo JText::_( 'FLEXI_CREATED' ); ?></strong>
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
				<strong><?php echo JText::_( 'FLEXI_MODIFIED' ); ?></strong>
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
			foreach ($this->versions as $version) :
				$class = ($version->nr == $this->version) ? ' class="active-version"' : '';
				if ((int)$version->nr > 0) :
			?>
			<tr<?php echo $class; ?>>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo '#' . $version->nr; ?></span></td>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo JHTML::_('date', (($version->nr == 1) ? $this->row->created : $version->date), JText::_( 'FLEXI_DATE_FORMAT_FLEXI_VERSIONS' )); ?></span></td>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo ($version->nr == 1) ? flexicontent_html::striptagsandcut($this->row->creator, 25) : flexicontent_html::striptagsandcut($version->modifier, 25); ?></span></td>
				<td class="versions" align="center"><a href="#" class="hasTip" title="Comment::<?php echo $version->comment;?>"><?php echo $comment;?></a><?php
				if((int)$version->nr==(int)$this->row->version) {//is current version? ?>
					<a onclick="javascript:return clickRestore('index.php?option=com_flexicontent&view=item&cid=<?php echo $this->row->id;?>&version=<?php echo $version->nr; ?>');" href="#"><?php echo JText::_( 'FLEXI_CURRENT' ); ?></a>
				<?php }else{
				?>
					<a class="modal-versions" href="index.php?option=com_flexicontent&view=itemcompare&cid[]=<?php echo $this->row->id; ?>&version=<?php echo $version->nr; ?>&tmpl=component" title="<?php echo JText::_( 'FLEXI_COMPARE_WITH_CURRENT_VERSION' ); ?>" rel="{handler: 'iframe', size: {x:window.getSize().scrollSize.x-100, y: window.getSize().size.y-100}}"><?php echo $view; ?></a><a onclick="javascript:return clickRestore('index.php?option=com_flexicontent&controller=items&task=edit&cid=<?php echo $this->row->id; ?>&version=<?php echo $version->nr; ?>&<?php echo JUtility::getToken();?>=1');" href="#" title="<?php echo JText::sprintf( 'FLEXI_REVERT_TO_THIS_VERSION', $version->nr ); ?>"><?php echo $revert; ?>
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
					<?php echo $this->lists['cid']; ?>
				</td>
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
		</table>

		<?php
			$title = JText::_( 'FLEXI_DETAILS' );
			echo $this->pane->startPane( 'det-pane' );
			echo $this->pane->startPanel( $title, 'details' );
			echo $this->form->render('details');

			$title = JText::_( 'FLEXI_METADATA_INFORMATION' );
			echo $this->pane->endPanel();
			echo $this->pane->startPanel( $title, "metadata-page" );
			echo $this->form->render('meta', 'metadata');
			
			$title = JText::_( 'FLEXI_PARAMETERS_STANDARD' );
			echo $this->pane->endPanel();
			echo $this->pane->startPanel( $title, "params-page" );
			echo $this->form->render('params', 'advanced');
			echo $this->pane->endPanel();

			echo '<h3 class="themes-title">' . JText::_( 'FLEXI_PARAMETERS_THEMES' ) . '</h3>';

			foreach ($this->tmpls as $tmpl) {
				$title = JText::_( 'FLEXI_PARAMETERS_SPECIFIC' ) . ' : ' . $tmpl->name;
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
<input type="hidden" name="hits" value="<?php echo $this->row->hits; ?>" />
<input type="hidden" name="oldtitle" value="<?php echo $this->row->title; ?>" />
<input type="hidden" name="oldtext" value="<?php echo $this->row->text; ?>" />
<input type="hidden" name="oldstate" value="<?php echo $this->row->state; ?>" />
<input type="hidden" name="oldmodified" value="<?php echo $this->row->modified; ?>" />
<input type="hidden" name="oldmodified_by" value="<?php echo $this->row->modified_by; ?>" />
<?php if (!FLEXI_FISH) : ?>
<input type="hidden" name="language" value="<?php echo $this->row->language; ?>" />
<?php endif; ?>
</form>

</div>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>