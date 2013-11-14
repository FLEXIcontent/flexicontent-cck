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

$ctrl_task = FLEXI_J16GE ? 'task=items.' : 'controller=items&task=';

// For tabsets/tabs ids (focusing, etc)
$tabSetCnt = -1;
$tabCnt = array();

$tags_displayed = $this->row->type_id && ( $this->perms['cantags'] || count(@$this->usedtags) ) ;

// add extra css/js for the edit form
if ($this->params->get('form_extra_css'))    $this->document->addStyleDeclaration($this->params->get('form_extra_css'));
if ($this->params->get('form_extra_css_be')) $this->document->addStyleDeclaration($this->params->get('form_extra_css_be'));
if ($this->params->get('form_extra_js'))     $this->document->addScriptDeclaration($this->params->get('form_extra_js'));
if ($this->params->get('form_extra_js_be'))  $this->document->addScriptDeclaration($this->params->get('form_extra_js_be'));

// Load JS tabber lib
$this->document->addScript( JURI::root().'components/com_flexicontent/assets/js/tabber-minimized.js' );
$this->document->addStyleSheet( JURI::root().'components/com_flexicontent/assets/css/tabber.css' );
$this->document->addScriptDeclaration(' document.write(\'<style type="text/css">.fctabber{display:none;}<\/style>\'); ');  // temporarily hide the tabbers until javascript runs

if ($this->perms['cantags'] || $this->perms['canversion']) {
	$this->document->addScript(JURI::root().'components/com_flexicontent/librairies/jquery-autocomplete/jquery.bgiframe.min.js');
	$this->document->addScript(JURI::root().'components/com_flexicontent/librairies/jquery-autocomplete/jquery.ajaxQueue.js');
	$this->document->addScript(JURI::root().'components/com_flexicontent/librairies/jquery-autocomplete/jquery.autocomplete.min.js');
	$this->document->addScript(JURI::root().'components/com_flexicontent/assets/js/jquery.pager.js');     // e.g. pagination for item versions
	$this->document->addScript(JURI::root().'components/com_flexicontent/assets/js/jquery.autogrow.js');  // e.g. autogrow version comment textarea

	$this->document->addStyleSheet(JURI::root().'components/com_flexicontent/librairies/jquery-autocomplete/jquery.autocomplete.css');
	$this->document->addScriptDeclaration("
		jQuery(document).ready(function () {
			jQuery(\"#input-tags\").autocomplete(\"".JURI::base()."index.php?option=com_flexicontent&controller=items&task=viewtags&format=raw&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1\", {
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
			jQuery(\"#fc_pager\").pager({ pagenumber: ".$this->current_page.", pagecount: ".$this->pagecount.", buttonClickCallback: PageClick });
		});

		PageClick = function(pageclickednumber) {
			jQuery.ajax({ url: \"index.php?option=com_flexicontent&controller=items&task=getversionlist&id=".$this->row->id."&active=".$this->row->version."&".(FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken())."=1&format=raw&page=\"+pageclickednumber, context: jQuery(\"#result\"), success: function(str){
				jQuery(this).html(\"<table width='100%' class='versionlist' cellpadding='0' cellspacing='0'>\\
				<tr>\\
					<th colspan='4'>".JText::_( 'FLEXI_VERSIONS_HISTORY',true )."</th>\\
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
			jQuery(\"#fc_pager\").pager({ pagenumber: pageclickednumber, pagecount: ".$this->pagecount.", buttonClickCallback: PageClick });
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
	obj.innerHTML+="<li class=\"tagitem\"><span>"+name+"</span><input type='hidden' name='tag[]' value='"+id+"' /><a href=\"javascript:;\"  class=\"deletetag\" onclick=\"javascript:deleteTag(this);\" title=\"<?php echo JText::_( 'FLEXI_DELETE_TAG',true ); ?>\"></a></li>";
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
	tag.addtag( id, tagname, 'index.php?option=com_flexicontent&controller=tags&task=addtag&format=raw&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1');
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
	if(confirm("<?php echo JText::_( 'FLEXI_CONFIRM_VERSION_RESTORE',true ); ?>")) {
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
<table width="100%"><tr>
	<td valign="top" style="width:auto; padding: 0px">

	<div class="fc_edit_container_full" style="margin:0px 0px 10px 0px !important;">
	<?php /*<fieldset class="basicfields_set">
		<legend>
			<?php echo JText::_( 'FLEXI_BASIC' ); ?>
		</legend>*/ ?>

			<?php
				$field = $this->fields['title'];
				$field_description = $field->description ? $field->description :
					JText::_(FLEXI_J16GE ? $this->form->getField('title')->__get('description') : 'TIPTITLEFIELD');
				$label_tooltip = 'class="hasTip flexi_label" title="'.'::'.htmlspecialchars($field_description, ENT_COMPAT, 'UTF-8').'"';
			?>
			<label id="title-lbl" for="title" <?php echo $label_tooltip; ?> >
				<?php echo $field->label; //JText::_( 'FLEXI_TITLE' ); ?>
			</label>
			<?php /*echo $this->form->getLabel('title');*/ ?>

			<div class="container_fcfield container_fcfield_id_1 container_fcfield_name_title" id="container_fcfield_1">
			<?php	if ( isset($this->row->item_translations) ) :?>

				<!-- tabber start -->
				<div class="fctabber" style=''>
					<div class="tabbertab" style="padding: 0px;" >
						<h3 class="tabberheading"> <?php echo '-'.$itemlangname.'-'; // $itemlang; ?> </h3>
						<input id="title" name="title" class="inputbox required" value="<?php echo $this->row->title; ?>" size="40" maxlength="254" />
					</div>
					<?php foreach ($this->row->item_translations as $t): ?>
						<?php if ($itemlang!=$t->shortcode && $t->shortcode!='*') : ?>
							<div class="tabbertab" style="padding: 0px;" >
								<h3 class="tabberheading"> <?php echo $t->name; // $t->shortcode; ?> </h3>
								<?php
								$ff_id = 'jfdata_'.$t->shortcode.'_title';
								$ff_name = 'jfdata['.$t->shortcode.'][title]';
								?>
								<input class="inputbox fc_form_title fcfield_textval" style='margin:0px;' type="text" id="<?php echo $ff_id; ?>" name="<?php echo $ff_name; ?>" value="<?php echo @$t->fields->title->value; ?>" size="36" maxlength="254" />
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<!-- tabber end -->

			<?php else : ?>
				<input id="title" name="title" class="inputbox required fcfield_textval" value="<?php echo $this->row->title; ?>" size="40" maxlength="254" />
			<?php endif; ?>
			</div>

			<div class="fcclear"></div>
			<?php
				$field_description = JText::_(FLEXI_J16GE ? $this->form->getField('alias')->__get('description') : 'ALIASTIP');
				$label_tooltip = 'class="hasTip flexi_label" title="'.'::'.htmlspecialchars($field_description, ENT_COMPAT, 'UTF-8').'"';
			?>
			<label id="alias-lbl" for="alias" <?php echo $label_tooltip; ?> >
				<?php echo JText::_( 'FLEXI_ALIAS' ); ?>
			</label>

			<div class="container_fcfield container_fcfield_name_alias">
			<?php	if ( isset($this->row->item_translations) ) :?>

				<!-- tabber start -->
				<div class="fctabber" style=''>
					<div class="tabbertab" style="padding: 0px;" >
						<h3 class="tabberheading"> <?php echo '-'.$itemlangname.'-'; // $itemlang; ?> </h3>
						<input id="alias" name="alias" class="inputbox" value="<?php echo $this->row->alias; ?>" size="40" maxlength="254" />
					</div>
					<?php foreach ($this->row->item_translations as $t): ?>
						<?php if ($itemlang!=$t->shortcode && $t->shortcode!='*') : ?>
							<div class="tabbertab" style="padding: 0px;" >
								<h3 class="tabberheading"> <?php echo $t->name; // $t->shortcode; ?> </h3>
								<?php
								$ff_id = 'jfdata_'.$t->shortcode.'_alias';
								$ff_name = 'jfdata['.$t->shortcode.'][alias]';
								?>
								<input class="inputbox fc_form_alias fcfield_textval" style='margin:0px;' type="text" id="<?php echo $ff_id; ?>" name="<?php echo $ff_name; ?>" value="<?php echo @$t->fields->alias->value; ?>" size="36" maxlength="254" />
							</div>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				<!-- tabber end -->

			<?php else : ?>
				<input id="alias" name="alias" class="inputbox fcfield_textval" value="<?php echo $this->row->alias; ?>" size="40" maxlength="254" />
			<?php endif; ?>
			</div>

			<div class="fcclear"></div>
			<?php
				$field = $this->fields['document_type'];
				$field_description = $field->description ? $field->description :
					JText::_(FLEXI_J16GE ? $this->form->getField('type_id')->__get('description') : 'FLEXI_TYPE_DESC');
				$label_tooltip = 'class="hasTip flexi_label" title="'.'::'.htmlspecialchars($field_description, ENT_COMPAT, 'UTF-8').'"';
			?>
			<label id="type_id-lbl" for="type_id" for_bck="type_id" <?php echo $label_tooltip; ?> >
				<?php echo $field->label; ?>
				<?php /*echo JText::_( 'FLEXI_TYPE' );*/ ?>
			</label>
			<?php /*echo $this->form->getLabel('type_id');*/ ?>
				
			<div class="container_fcfield container_fcfield_id_8 container_fcfield_name_type" id="container_fcfield_8">
				<?php echo $this->lists['type']; ?>
				<?php //echo $this->form->getInput('type_id'); ?>
				<span class="editlinktip hasTip" style="display:inline-block;" title="<?php echo htmlspecialchars(JText::_( 'FLEXI_NOTES' ), ENT_COMPAT, 'UTF-8'); ?>::<?php echo htmlspecialchars(JText::_( 'FLEXI_TYPE_CHANGE_WARNING' ), ENT_COMPAT, 'UTF-8');?>">
					<?php echo $infoimage; ?>
				</span>
				<div id="fc-change-warning" class="fc-mssg fc-warning" style="display:none;"><?php echo JText::_( 'FLEXI_TAKE_CARE_CHANGING_FIELD_TYPE' ); ?></div>
			</div>

			<div class="fcclear"></div>
			<?php
				$field = $this->fields['state'];
				$field_description = $field->description ? $field->description :
					JText::_(FLEXI_J16GE ? $this->form->getField('state')->__get('description') : 'FLEXI_STATE_DESC');
				$label_tooltip = 'class="hasTip flexi_label" title="'.'::'.htmlspecialchars($field_description, ENT_COMPAT, 'UTF-8').'"';
			?>
			<label id="state-lbl" for="state" <?php echo $label_tooltip; ?> >
				<?php echo $field->label; ?>
				<?php /*echo JText::_( 'FLEXI_STATE' );*/ ?>
			</label>
			<?php /*echo $this->form->getLabel('state');*/ ?>
			<?php
			if ( $this->perms['canpublish'] ) : ?>
				<div class="container_fcfield container_fcfield_id_10 container_fcfield_name_state fcdualline" id="container_fcfield_10" style="margin-right:4% !important;" >
					<?php echo $this->lists['state']; ?>
					<?php //echo $this->form->getInput('state'); ?>
					<span class="editlinktip hasTip" style="display:inline-block;" title="<?php echo htmlspecialchars(JText::_( 'FLEXI_NOTES' ), ENT_COMPAT, 'UTF-8'); ?>::<?php echo htmlspecialchars(JText::_( 'FLEXI_STATE_CHANGE_WARNING' ), ENT_COMPAT, 'UTF-8');?>">
						<?php echo $infoimage; ?>
					</span>
				</div>
			<?php else :
				echo $this->published;
				echo '<input type="hidden" name="state" id="vstate" value="'.$this->row->state.'" />';
			endif;
			?>

		<?php if ( $this->perms['canpublish'] ) : ?>
			<?php if (!$this->params->get('auto_approve', 1)) : ?>
				<?php
					//echo "<br/>".$this->form->getLabel('vstate') . $this->form->getInput('vstate');
					$label_tooltip = 'class="hasTip flexi_label fcdualline" title="'.htmlspecialchars(JText::_( 'FLEXI_PUBLIC_DOCUMENT_CHANGES' ), ENT_COMPAT, 'UTF-8').'::'.htmlspecialchars(JText::_( 'FLEXI_PUBLIC_DOCUMENT_CHANGES_DESC' ), ENT_COMPAT, 'UTF-8').'"';
				?>
				<div style="float:left; width:50%; margin:0px; padding:0px;">
					<label id="vstate-lbl" for="vstate" <?php echo $label_tooltip; ?> >
						<?php echo JText::_( 'FLEXI_PUBLIC_DOCUMENT_CHANGES' ); ?>
					</label>
					<div class="container_fcfield container_fcfield_name_vstate fcdualline">
						<?php echo $this->lists['vstate']; ?>
					</div>
				</div>
			<?php else :
				echo '<input type="hidden" name="vstate" id="vstate" value="2" />';
			endif;
		elseif (!$this->params->get('auto_approve', 1)) :
			// Enable approval if versioning disabled, this make sense,
			// since if use can edit item THEN item should be updated !!!
			$item_vstate = $this->params->get('use_versioning', 1) ? 1 : 2;
			echo '<input type="hidden" name="vstate" id="vstate" value="'.$item_vstate.'" />';
		else :
			echo '<input type="hidden" name="vstate" id="vstate" value="2" />';
		endif;
		?>
		
		<?php if ($this->subscribers) : ?>
			<div class="fcclear"></div>
			<?php
				$label_tooltip = 'class="hasTip flexi_label" title="'.'::'.htmlspecialchars(JText::_( 'FLEXI_NOTIFY_NOTES' ), ENT_COMPAT, 'UTF-8').'"';
			?>
			<label id="notify-lbl" for="notify" <?php echo $label_tooltip; ?> >
				<?php echo JText::_( 'FLEXI_NOTIFY_FAVOURING_USERS' ); ?>
			</label>
			<div class="container_fcfield container_fcfield_name_notify">
				<?php echo $this->lists['notify']; ?>
			</div>
		<?php endif; ?>
		
	<?php /*</fieldset>*/ ?>
	</div>


<?php
// *****************
// MAIN TABSET START
// *****************
$tabSetCnt++;
$tabCnt[$tabSetCnt] = 0;
?>

<!-- tabber start -->
<div class='fctabber fields_tabset' id='fcform_tabset_<?php echo $tabSetCnt; ?>' >
	<div class='tabbertab' id='fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>' >
		<h3 class="tabberheading"> <?php echo JText::_( 'FLEXI_BASIC' ); ?> </h3>
		
		<?php $fset_lbl = $tags_displayed ? 'FLEXI_CATEGORIES_TAGS' : 'FLEXI_CATEGORIES';?>
		<fieldset class="basicfields_set">
			<legend>
				<?php echo JText::_( $fset_lbl ); ?>
			</legend>
			
			<label id="catid-lbl" for="catid" for_bck="catid" class="flexi_label" >
				<?php echo JText::_( 'FLEXI_CATEGORIES_MAIN' ); ?>
			</label>
			<div class="container_fcfield container_fcfield_name_catid">
				<?php echo $this->lists['catid']; ?>
				<span class="editlinktip hasTip" title="<?php echo htmlspecialchars(JText::_ ( 'FLEXI_NOTES' ), ENT_COMPAT, 'UTF-8'); ?>::<?php echo htmlspecialchars(JText::_ ( 'FLEXI_CATEGORIES_NOTES' ), ENT_COMPAT, 'UTF-8');?>">
				<?php echo $infoimage; ?>
				</span>
			</div>
			
			<?php if ( !empty($this->lists['featured_cid']) ) : ?>
				<div class="fcclear"></div>
				<label id="featured_cid-lbl" for="featured_cid" for_bck="featured_cid" class="flexi_label">
					<?php echo JText::_( 'FLEXI_FEATURED_CATEGORIES' ); ?>
				</label>
				<div class="container_fcfield container_fcfield_name_featured_cid">
					<?php echo $this->lists['featured_cid']; ?>
				</div>
			<?php endif; ?>
			
			<div class="fcclear"></div>
			<label id="cid-lbl" for="cid" for_bck="cid" class="flexi_label" >
				<?php echo JText::_( 'FLEXI_CATEGORIES' ); ?>
			</label>
			<div class="container_fcfield container_fcfield_name_cid">
				<?php echo $this->lists['cid']; ?>
			</div>
			
		<?php /*<fieldset class="basicfields_set">
			<legend>
				<?php echo JText::_( 'FLEXI_TAGGING' ); ?>
			</legend>*/ ?>
			
			<div class="fcclear"></div>
			<div id="tags">
				<?php
					$field = $this->fields['tags'];
					$label_tooltip = $field->description ? 'class="hasTip flexi_label" title="'.'::'.htmlspecialchars($field->description, ENT_COMPAT, 'UTF-8').'"' : 'class="flexi_label"';
				?>
				<label id="tag-lbl" for="tag" <?php echo $label_tooltip; ?> >
					<?php echo $field->label; ?>
					<?php /*echo JText::_( 'FLEXI_TAGS' );*/ ?>
				</label>
				<div class="container_fcfield container_fcfield_name_tags">

					<div class="qf_tagbox" id="qf_tagbox">
						<ul id="ultagbox">
						<?php
							$nused = count($this->usedtags);
							for( $i = 0, $nused; $i < $nused; $i++ ) {
								$tag = $this->usedtags[$i];
								if ( $this->perms['cantags'] ) {
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

					<?php if ( $this->perms['cantags'] ) : ?>
						<div class="fcclear"></div>
						<label for="input-tags">
							<?php echo JText::_( 'FLEXI_ADD_TAG' ); ?>
						</label>
						<input type="text" id="input-tags" name="tagname" tagid='0' tagname='' />
						<span id='input_new_tag' ></span>
						<span class="editlinktip hasTip" style="display:inline-block;" title="<?php echo htmlspecialchars(JText::_( 'FLEXI_NOTES' ), ENT_COMPAT, 'UTF-8'); ?>::<?php echo htmlspecialchars(JText::_( 'FLEXI_TAG_EDDITING_FULL' ), ENT_COMPAT, 'UTF-8');?>">
							<?php echo $infoimage; ?>
						</span>
					<?php endif; ?>
				</div>
			</div>

		</fieldset>

		
		<?php if (FLEXI_FISH || FLEXI_J16GE) : ?>
		<fieldset class="basicfields_set">
			<legend>
				<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
			</legend>
			
			<label id="language-lbl" for="language" class="flexi_label">
				<?php echo JText::_( 'FLEXI_LANGUAGE' ); ?>
			</label>

			<div class="container_fcfield container_fcfield_name_language">
				<?php echo $this->lists['languages']; ?>
			</div>

			<?php if ($this->params->get('enable_translation_groups')) : ?>

				<div class="fcclear"></div>
				<?php
					$label_tooltip = 'class="hasTip flexi_label" title="'.'::'.htmlspecialchars(JText::_( 'FLEXI_ORIGINAL_CONTENT_ITEM_DESC' ), ENT_COMPAT, 'UTF-8').'"';
				?>
				<label id="lang_parent_id-lbl" for="lang_parent_id" <?php echo $label_tooltip; ?> >
					<?php echo JText::_( 'FLEXI_ORIGINAL_CONTENT_ITEM' );?>
				</label>
				<div class="container_fcfield container_fcfield_name_originalitem">
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
						//echo '<small>'.JText::_( 'FLEXI_ORIGINAL_CONTENT_IGNORED_IF_DEFAULT_LANG' ).'</small><br>';
						echo $ff_lang_parent_id->fetchElement('lang_parent_id', $this->row->lang_parent_id, $jelement, '');
					?>
					<span class="editlinktip hasTip" style="display:inline-block;" title="<?php echo htmlspecialchars(JText::_( 'FLEXI_NOTES' ), ENT_COMPAT, 'UTF-8'); ?>::<?php echo htmlspecialchars(JText::_( 'FLEXI_ORIGINAL_CONTENT_IGNORED_IF_DEFAULT_LANG' ), ENT_COMPAT, 'UTF-8');?>">
						<?php echo $infoimage; ?>
					</span>
				<?php endif; ?>
				</div>

				<div class="fcclear"></div>
				<label id="langassocs-lbl" for="langassocs" class="flexi_label" >
					<?php echo JText::_( 'FLEXI_ASSOC_TRANSLATIONS' );?>
				</label>
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

						$_link  = 'index.php?option=com_flexicontent&'.$ctrl_task.'edit&cid[]='. $assoc_item->id;
						$_title = htmlspecialchars(JText::_( 'FLEXI_EDIT_ASSOC_TRANSLATION' ), ENT_COMPAT, 'UTF-8').':: ['. $assoc_item->lang .'] '. htmlspecialchars($assoc_item->title, ENT_COMPAT, 'UTF-8');
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
			<?php endif; /* IF enable_translation_groups */ ?>
			
		</fieldset>
		<?php endif; /* IF language */ ?>

	<?php
		if (FLEXI_ACCESS && $this->perms['canright'] && $this->row->id) :
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
			<div id="tabacces">
				<div id="access"><?php echo $this->lists['access']; ?></div>
			</div>
			<div id="notabacces">
			<?php echo JText::_( 'FLEXI_RIGHTS_MANAGEMENT_DESC' ); ?>
			</div>
		</fieldset>

	<?php endif; ?>

	</div> <!-- end tab -->



<?php
$type_lbl = $this->row->type_id ? JText::_( 'FLEXI_ITEM_TYPE' ) . ' : ' . $this->typesselected->name : JText::_( 'FLEXI_TYPE_NOT_DEFINED' );
?>
<?php if ($this->fields && $this->row->type_id) : ?>
	
	<div class='tabbertab' id='fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>' >
		<h3 class="tabberheading"> <?php echo $type_lbl; ?> </h3>
		
		<?php
		$this->document->addScriptDeclaration("
			jQuery(document).ready(function() {
				jQuery('#type_id').change(function() {
					if (jQuery('#type_id').val() != '".$this->row->type_id."')
						jQuery('#fc-change-error').css('display', 'block');
					else
						jQuery('#fc-change-error').css('display', 'none');
				});
			});
		");
		?>

		<div class="fc_edit_container_full">
		<?php /*<fieldset class="customfields_set">
			<legend>
				<?php echo $this->row->type_id ? JText::_( 'FLEXI_ITEM_TYPE' ) . ' : ' . $this->typesselected->name : JText::_( 'FLEXI_TYPE_NOT_DEFINED' ); ?>
			</legend>*/ ?>

				<?php
				$hidden = array('fcloadmodule', 'fcpagenav', 'toolbar');
				$noplugin = '<div class="fc-mssg fc-warning">'. JText::_( 'FLEXI_PLEASE_PUBLISH_PLUGIN' ) .'</div>';
				$row_k = 0;
				foreach ($this->fields as $field)
				{
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
						' class="flexi_label hasTip '.($edithelp==2 ? ' fc_tooltip_icon_bg ' : '').'" title="'.'::'.htmlspecialchars($field->description, ENT_COMPAT, 'UTF-8').'" ' :
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
						
						<div class='fcclear'></div>

						<label for="<?php echo (FLEXI_J16GE ? 'custom_' : '').$field->name; ?>" for_bck="<?php echo (FLEXI_J16GE ? 'custom_' : '').$field->name; ?>" <?php echo $label_tooltip . $label_style; ?> >
							<?php echo $field->label; ?>
						</label>

						<div style="<?php echo $width; ?>;" class="fcfield_row<?php echo $row_k;?> container_fcfield
							container_fcfield_id_<?php echo $field->id;?> container_fcfield_name_<?php echo $field->name;?>" id="container_fcfield_<?php echo $field->id;?>"
						>
								
							<?php echo ($field->description && $edithelp==3) ? '<div class="fc_mini_note_box">'.$field->description.'</div>' : ''; ?>

					<?php	if ($field->field_type=='maintext' && isset($this->row->item_translations) ) : ?>

						<!-- tabber start -->
						<div class="fctabber" style=''>
							<div class="tabbertab" style="padding: 0px;" >
								<h3 class="tabberheading"> <?php echo '- '.$itemlangname.' -'; // $t->name; ?> </h3>
								<?php
									$field_tab_labels = & $field->tab_labels;
									$field_html       = & $field->html;
									echo !is_array($field_html) ? $field_html : flexicontent_html::createFieldTabber( $field_html, $field_tab_labels, "");
								?>
							</div>
							<?php foreach ($this->row->item_translations as $t): ?>
								<?php if ($itemlang!=$t->shortcode && $t->shortcode!='*') : ?>
									<div class="tabbertab" style="padding: 0px;" >
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
							<?php echo $not_in_tabs;      // Output ENDING hidden fields, by placing them outside the tabbing area ?>

						<?php endif; ?>

					<?php endif; ?>

						</div>

				<?php
				}
				?>
		<?php /*</fieldset>*/ ?>
		</div>
	
	</div> <!-- end tab -->

<?php else : /* NO TYPE SELECTED */ ?>

	<div class='tabbertab' id='fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>' >
		<h3 class="tabberheading"> <?php echo $type_lbl; ?> </h3>
		
		<div class="fc_edit_container_full">
			<?php if ($this->row->id == 0) : ?>
				<input name="type_id_not_set" value="1" type="hidden" />
				<div class="fc-mssg fc-note"><?php echo JText::_( 'FLEXI_CHOOSE_ITEM_TYPE' ); ?></div>
			<?php else : ?>
				<div class="fc-mssg fc-warning"><?php echo JText::_( 'FLEXI_NO_FIELDS_TO_TYPE' ); ?></div>
			<?php	endif; ?>
		</div>
		
	</div> <!-- end tab -->
	
<?php	endif; ?>


	<div class='tabbertab' id='fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>' >
		<h3 class="tabberheading"> <?php echo JText::_('FLEXI_PUBLISHING'); ?> </h3>
		
		<fieldset class="panelform fc_edit_container_full">
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
			
			<div class="flexi_params">
				<?php echo $this->formparams->render('details'); ?>
			</div>
			
		</fieldset>
		
	</div> <!-- end tab -->
	
	
	<div class='tabbertab' id='fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>' >
		<h3 class="tabberheading"> <?php echo JText::_('FLEXI_DISPLAYING'); ?> </h3>
		
		<?php
			/*
			echo $this->pane->startPane( 'det-pane' );
			
			$title = JText::_('FLEXI_PARAMETERS_ITEM_BASIC' );
			echo $this->pane->startPanel( $title, "params-basic" );
			echo $this->formparams->render('params', 'basic');
			echo $this->pane->endPanel();
			
			$title = JText::_('FLEXI_PARAMETERS_ITEM_ADVANCED' );
			echo $this->pane->startPanel( $title, "params-advanced" );
			echo $this->formparams->render('params', 'advanced');
			echo $this->pane->endPanel();
	
			$title = JText::_('FLEXI_METADATA_INFORMATION' );
			echo $this->pane->startPanel( $title, "params-metadata" );
			echo $this->formparams->render('meta', 'metadata');
			echo $this->pane->endPanel();
			
			$title = JText::_('FLEXI_PARAMETERS_ITEM_SEO' );
			echo $this->pane->startPanel( $title, "params-seoconf" );
			echo $this->formparams->render('params', 'seoconf');
			echo $this->pane->endPanel();
			
			echo $this->pane->endPane();
			*/
		?>
		
		<div class="flexi_params">
			<?php echo $this->formparams->render('params', 'basic'); ?>
		</div>
		
		<div class="flexi_params">
			<?php echo $this->formparams->render('params', 'advanced'); ?>
		</div>
	
	</div> <!-- end tab -->
	
	
	<div class='tabbertab' id='fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>' >
		<h3 class="tabberheading"> <?php echo JText::_('FLEXI_META_SEO'); ?> </h3>
		
		<fieldset class="params_set">
			<legend>
				<?php echo JText::_( 'FLEXI_META' ); ?>
			</legend>
			
			<div class="fcclear"></div>
			
			<div class="flexi_params">
			<table width="100%" cellspacing="1" class="paramlist admintable"><tbody>
				<tr>
					<td width="40%" class="paramlist_key"><span class="editlinktip">
						<label id="metadescription-lbl" class="hasTip" for="metadescription" title="::<?php echo htmlspecialchars(JText::_ ( 'FLEXI_METADESC' ), ENT_COMPAT, 'UTF-8'); ?>" >
							<?php echo JText::_('FLEXI_Description'); ?>
						</label>
					</span></td>
					
					<td class="paramlist_value">
						<?php	if ( isset($this->row->item_translations) ) : ?>
			
							<!-- tabber start -->
							<div class="fctabber" style='display:inline-block;'>
								<div class="tabbertab" style="padding: 0px;" >
									<h3 class="tabberheading"> <?php echo '-'.$itemlang.'-'; // $t->name; ?> </h3>
									<textarea id="metadescription" class="fcfield_textareaval" rows="3" cols="46" name="meta[description]"><?php echo $this->formparams->get('description'); ?></textarea>
								</div>
								<?php foreach ($this->row->item_translations as $t): ?>
									<?php if ($itemlang!=$t->shortcode && $t->shortcode!='*') : ?>
										<div class="tabbertab" style="padding: 0px;" >
											<h3 class="tabberheading"> <?php echo $t->shortcode; // $t->name; ?> </h3>
											<?php
											$ff_id = 'jfdata_'.$t->shortcode.'_metadesc';
											$ff_name = 'jfdata['.$t->shortcode.'][metadesc]';
											?>
											<textarea id="<?php echo $ff_id; ?>" class="fcfield_textareaval" rows="3" cols="46" name="<?php echo $ff_name; ?>"><?php echo @$t->fields->metadesc->value; ?></textarea>
										</div>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
							<!-- tabber end -->
			
						<?php else : ?>
							<textarea id="metadescription" class="fcfield_textareaval" rows="3" cols="80" name="meta[description]"><?php echo $this->formparams->get('description'); ?></textarea>
						<?php endif; ?>
					</td>
				</tr>
				
				<tr>
					<td width="40%" class="paramlist_key"><span class="editlinktip">
						<label id="metakeywords-lbl" class="hasTip" for="metakeywords" title="::<?php echo htmlspecialchars(JText::_ ( 'FLEXI_METAKEYS' ), ENT_COMPAT, 'UTF-8'); ?>" >
							<?php echo JText::_('FLEXI_Keywords'); ?>
						</label>
					</span></td>
					
					<td class="paramlist_value">
						<?php	if ( isset($this->row->item_translations) ) :?>
			
							<!-- tabber start -->
							<div class="fctabber" style='display:inline-block;'>
								<div class="tabbertab" style="padding: 0px;" >
									<h3 class="tabberheading"> <?php echo '-'.$itemlang.'-'; // $t->name; ?> </h3>
									<textarea id="metakeywords" class="fcfield_textareaval" rows="3" cols="46" name="meta[keywords]"><?php echo $this->formparams->get('keywords'); ?></textarea>
								</div>
								<?php foreach ($this->row->item_translations as $t): ?>
									<?php if ($itemlang!=$t->shortcode && $t->shortcode!='*') : ?>
										<div class="tabbertab" style="padding: 0px;" >
											<h3 class="tabberheading"> <?php echo $t->shortcode; // $t->name; ?> </h3>
											<?php
											$ff_id = 'jfdata_'.$t->shortcode.'_metakey';
											$ff_name = 'jfdata['.$t->shortcode.'][metakey]';
											?>
											<textarea id="<?php echo $ff_id; ?>" class="fcfield_textareaval" rows="3" cols="46 name="<?php echo $ff_name; ?>"><?php echo @$t->fields->metakey->value; ?></textarea>
										</div>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
							<!-- tabber end -->
			
						<?php else : ?>
							<textarea id="metakeywords" class="fcfield_textareaval" rows="3" cols="80" name="meta[keywords]"><?php echo $this->formparams->get('keywords'); ?></textarea>
						<?php endif; ?>
					</td>
				</tr>
				
			</tbody></table>
			</div>
			
			
			<div class="fcclear"></div>
			<div class="flexi_params">
				<?php echo $this->formparams->render('meta', 'metadata'); ?>
			</div>
			
		</fieldset>
		
		<fieldset class="params_set">
			<legend>
				<?php echo JText::_( 'FLEXI_SEO' ); ?>
			</legend>
			
			<div class="fcclear"></div>
			<div class="flexi_params">
				<?php echo $this->formparams->render('params', 'seoconf'); ?>
			</div>
			
		</fieldset>
		
	</div> <!-- end tab -->
	
	
	<div class='tabbertab' id='fcform_tabset_<?php echo $tabSetCnt; ?>_tab_<?php echo $tabCnt[$tabSetCnt]++; ?>' >
		<h3 class="tabberheading"> <?php echo JText::_('FLEXI_TEMPLATE'); ?> </h3>
		
		<fieldset class="flexi_params fc_edit_container_full">
			<?php
				echo '<h3 class="themes-title">' . JText::_( 'FLEXI_PARAMETERS_LAYOUT_THEMES' ) . '</h3>';
				$type_default_layout = $this->tparams->get('ilayout');
				echo $this->formparams->render('params', 'themes');
			?>
			
			<blockquote id='__content_type_default_layout__'>
				<?php echo JText::sprintf( 'FLEXI_USING_CONTENT_TYPE_LAYOUT', $type_default_layout ); ?>
				<?php echo "<br><br>". JText::_( 'FLEXI_RECOMMEND_CONTENT_TYPE_LAYOUT' ); ?>
			</blockquote>
			
			<?php
				echo $this->pane->startPane( 'themes-pane' );
				foreach ($this->tmpls as $tmpl) {
					$title = JText::_( 'FLEXI_PARAMETERS_THEMES_SPECIFIC' ) . ' : ' . $tmpl->name;
					
					echo $this->pane->startPanel( $title, "params-".$tmpl->name );
					echo $tmpl->params->render();
					echo $this->pane->endPanel();
				}
				echo $this->pane->endPane()
			?>
			
		</fieldset>
		
	</div> <!-- end tab -->

<?php
// ***************
// MAIN TABSET END
// ***************
?>
</div> <!-- end of tab set -->
				
				
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
					$label_tooltip = $field->description ? 'class="hasTip" title="'.'::'.htmlspecialchars($field->description, ENT_COMPAT, 'UTF-8').'"' : '';
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
					$label_tooltip = $field->description ? 'class="hasTip" title="'.'::'.htmlspecialchars($field->description, ENT_COMPAT, 'UTF-8').'"' : '';
				?>
				<strong <?php echo $label_tooltip; ?>><?php echo $field->label;  /* JText::_( 'FLEXI_HITS' ) */ ?></strong>
			</td>
			<td>
				<div id="hits" style="display:none;"></div>
				<input id="hits" type="text" name="hits" size="6" value="<?php echo $this->row->hits; ?>" />
				<span <?php echo $visibility; ?>>
					<input name="reset_hits" type="button" class="button" value="<?php echo JText::_( 'FLEXI_RESET' ); ?>" onclick="reseter('resethits', '<?php echo $this->row->id; ?>', 'hits')" />
				</span>
			</td>
		</tr>
		<tr>
			<td>
				<?php
					$field = $this->fields['voting'];
					$label_tooltip = $field->description ? 'class="hasTip" title="'.'::'.htmlspecialchars($field->description, ENT_COMPAT, 'UTF-8').'"' : '';
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
					$label_tooltip = $field->description ? 'class="hasTip" title="'.'::'.htmlspecialchars($field->description, ENT_COMPAT, 'UTF-8').'"' : '';
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
					$label_tooltip = $field->description ? 'class="hasTip" title="'.'::'.htmlspecialchars($field->description, ENT_COMPAT, 'UTF-8').'"' : '';
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
					$label_tooltip = $field->description ? 'class="hasTip" title="'.'::'.htmlspecialchars($field->description, ENT_COMPAT, 'UTF-8').'"' : '';
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

	<?php if ($this->params->get('use_versioning', 1)) : ?>
		<table width="100%" style="border: 1px dashed silver; padding: 5px; margin-bottom: 10px;">
			<tr>
				<th style="border-bottom: 1px dotted silver; padding-bottom: 3px;" colspan="4"><?php echo JText::_( 'FLEXI_VERSION_COMMENT' ); ?></th>
			</tr>
			<tr>
				<td><textarea name="versioncomment" id="versioncomment" style="width: 300px; height: 30px; line-height:1"></textarea></td>
			</tr>
		</table>
		
		<?php if ( $this->perms['canversion'] ) : ?>
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
			foreach ($this->versions as $version) :
				$class = ($version->nr == $this->row->version) ? ' class="active-version"' : '';
				if ((int)$version->nr > 0) :
			?>
			<tr<?php echo $class; ?>>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo '#' . $version->nr; ?></span></td>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo JHTML::_('date', (($version->nr == 1) ? $this->row->created : $version->date), $date_format ); ?></span></td>
				<td class="versions"><span style="padding: 0 5px 0 0;"><?php echo ($version->nr == 1) ? flexicontent_html::striptagsandcut($this->row->creator, 25) : flexicontent_html::striptagsandcut($version->modifier, 25); ?></span></td>
				<td class="versions" align="center"><a href="javascript:;" class="hasTip" title="Comment::<?php echo htmlspecialchars($version->comment, ENT_COMPAT, 'UTF-8');?>"><?php echo $commentimage;?></a><?php
				if((int)$version->nr==(int)$this->row->current_version) { ?>
					<a onclick="javascript:return clickRestore('index.php?option=com_flexicontent&view=item&<?php echo $ctrl_task;?>edit&cid=<?php echo $this->row->id;?>&version=<?php echo $version->nr; ?>');" href="#"><?php echo JText::_( 'FLEXI_CURRENT' ); ?></a>
				<?php }else{
				?>
					<a class="modal-versions" href="index.php?option=com_flexicontent&view=itemcompare&cid[]=<?php echo $this->row->id; ?>&version=<?php echo $version->nr; ?>&tmpl=component" title="<?php echo JText::_( 'FLEXI_COMPARE_WITH_CURRENT_VERSION' ); ?>" rel="{handler: 'iframe', size: {x:window.getSize().scrollSize.x-100, y: window.getSize().size.y-100}}"><?php echo $viewimage; ?></a><a onclick="javascript:return clickRestore('index.php?option=com_flexicontent&controller=items&task=edit&cid=<?php echo $this->row->id; ?>&version=<?php echo $version->nr; ?>&<?php echo (FLEXI_J30GE ? JSession::getFormToken() : JUtility::getToken());?>=1');" href="javascript:;" title="<?php echo JText::sprintf( 'FLEXI_REVERT_TO_THIS_VERSION', $version->nr ); ?>"><?php echo $revertimage; ?>
				<?php }?></td>
			</tr>
			<?php
				endif;
			endforeach;
			endif; ?>
		</table>
		</div>
		<div id="fc_pager"></div>
		<div class="clear"></div>
		<?php endif; ?>
	<?php endif; ?>
	
		</td>
	</tr>
</table>
<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
<input type="hidden" name="controller" value="items" />
<input type="hidden" name="view" value="item" />
<input type="hidden" name="task" value="" />
<input type="hidden" name="unique_tmp_itemid" value="<?php echo JRequest::getVar( 'unique_tmp_itemid' );?>" />
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