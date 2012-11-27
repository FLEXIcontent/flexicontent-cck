<?php defined('_JEXEC') or die('Restricted access');

$show_searchphrase = $this->params->get('show_searchphrase', 1);
$default_searchphrase = $this->params->get('default_searchphrase', 'all');

$show_searchordering = $this->params->get('show_searchordering', 1);
$default_searchordering = $this->params->get('default_searchordering', 'newest');

$show_orderby = $this->params->get('orderby_override', 1);
$default_orderby = $this->params->get('orderby', 'rdate');

$autodisplayadvoptions = $this->params->get('autodisplayadvoptions', 1);

$show_filtersop = $this->params->get('show_filtersop', 1);
$default_filtersop = $this->params->get('default_filtersop', 'all');

$show_searchareas = $this->params->get( 'show_searchareas', 0 );

$js ="

function fc_toggleClass(ele,cls) {
  if (jQuery(ele).hasClass(cls)) {
  	jQuery(ele).removeClass(cls);
  } else {
  	jQuery(ele).addClass(cls);
  }
}

function fc_toggleClassGrp(ele, cls) {
	var inputs = ele.getElementsByTagName('input');
	for (var i = 0; i < inputs.length; ++i) {
		if (inputs[i].checked) {
			jQuery(inputs[i].parentNode).addClass(cls);
		} else {
			jQuery(inputs[i].parentNode).removeClass(cls);
		}
	}
}
";

if($autodisplayadvoptions) {
 $js .= '
	window.addEvent("domready", function() {
	  var status = {
	    "true": "open",
	    "false": "close"
	  };
	  
	  jQuery("#fc_advsearch_options_set").css("position","relative").hide(0, function(){}).css("position","static");
	  
	  '. (($autodisplayadvoptions==1 && !JRequest::getInt('use_advsearch_options')) ? '' : 'jQuery("#fc_advsearch_options_set").css("position","relative").toggle(500, function(){}).css("position","static");') .'
		
	  jQuery("#use_advsearch_options").click(function() {
	  
		  jQuery("#fc_advsearch_options_set").css("position","relative").toggle(500, function(){}).css("position","static");
    });
	});
	';
}

$this->document->addScriptDeclaration($js);

$r = 0;

//params[search_fields]
$search_fields = $this->params->get('search_fields', '');
$search_fields = $search_fields?explode(",", $search_fields):array();
$fields = &$this->fields;

$infoimage = JHTML::image ( 'components/com_flexicontent/assets/images/information.png', '', ' style="float:left; margin: 0px 8px 0px 2px;" ' );
$text_search_title_tip   = ' title="'.JText::_('FLEXI_TEXT_SEARCH').'::'.JText::_('FLEXI_TEXT_SEARCH_INFO').'" ';
$field_filters_title_tip = ' title="'.JText::_('FLEXI_FIELD_FILTERS').'::'.JText::_('FLEXI_FIELD_FILTERS_INFO').'" ';
$other_search_areas_title_tip = ' title="'.JText::_('FLEXI_SEARCH_ALSO_SEARCH_IN_AREAS').'::'.JText::_('FLEXI_SEARCH_ALSO_SEARCH_IN_AREAS_TIP').'" ';
?>

<form id="searchForm" action="<?php echo JRoute::_('index.php?option=com_flexicontent&task=search&Itemid='.(JRequest::getVar('Itemid')));?>" method="get" name="searchForm">
	
	<fieldset id='fc_textsearch_set' class='fc_search_set'>
		<legend><span class="hasTip" <?php echo $text_search_title_tip;?> ><?php echo $infoimage; ?></span><?php echo JText::_('FLEXI_TEXT_SEARCH'); ?></legend>
		
		<table id="fc_textsearch_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" cellspacing="1">
			
			<tr class="fc_search_row_<?php echo (($r++)%2);?>">
				<td nowrap="nowrap" class='fc_search_label_cell'>
					<label for="search_searchword" class='hasTip' title='<?php echo JText::_('FLEXI_SEARCH_SEARCHWORDS'); ?>::<?php echo JText::_('FLEXI_SEARCH_SEARCHWORDS_TIP'); ?>'>
						<?php echo JText::_('FLEXI_SEARCH_SEARCHWORDS'); ?>:
					</label>
				</td>
				<td nowrap="nowrap" class="fc_search_option_cell">
					<input type="text" name="searchword" id="search_searchword" size="30" maxlength="50" value="<?php echo $this->escape($this->searchword); ?>" class="inputbox" />
				</td>
				<td width="100%" nowrap="nowrap" align="center" >
					<button class="fc_button button_go" onclick="this.form.submit();"><span class="fcbutton_go"><?php echo JText::_( 'FLEXI_GO' ); ?></span></button>
				</td>
			</tr>
			
			<?php if ( $show_searchphrase ) : ?>
			<tr class="fc_search_row_<?php echo (($r++)%2);?>">
				<td nowrap="nowrap" class='fc_search_label_cell'>
					<label class='hasTip' title='<?php echo JText::_('FLEXI_SEARCH_KEYWORD_REQUIREMENT'); ?>::<?php echo JText::_('FLEXI_SEARCH_KEYWORD_REQUIREMENT_TIP'); ?>'>
						<?php echo JText::_('FLEXI_SEARCH_KEYWORD_REQUIREMENT'); ?>:
					</label>
				</td>
				<td colspan="2" class="fc_search_option_cell">
					<?php echo $this->lists['searchphrase']; ?>
				</td>
			</tr>
			<?php endif; ?>

			<?php if ($this->params->get('cantypes', 1) && isset($this->lists['contenttypes'])) : ?>
			
				<tr class="fc_search_row_<?php echo (($r++)%2);?>">
					<td nowrap="nowrap" class='fc_search_label_cell' valign='top'>
						<label for="contenttypes" class='hasTip' title='<?php echo JText::_('FLEXI_SEARCH_CONTENT_TYPE'); ?>::<?php echo JText::_('FLEXI_SEARCH_CONTENT_TYPE_TIP'); ?>'>
							<?php echo JText::_('FLEXI_SEARCH_CONTENT_TYPE'); ?>:
						</label>
					</td>
					<td colspan="2" class="fc_search_option_cell">
						<?php echo $this->lists['contenttypes'];?>
					</td>
				</tr>
				
			<?php endif; ?>

			<?php if( $show_orderby ) : ?>
			
				<tr class="fc_search_row_<?php echo (($r++)%2);?>">
					<td class='fc_search_label_cell' valign='top'>
						<label for="orderby" class="hasTip" title='<?php echo JText::_('FLEXI_SEARCH_ORDERING'); ?>::<?php echo JText::_('FLEXI_SEARCH_ORDERING_TIP'); ?>'>
							<?php echo JText::_( 'FLEXI_SEARCH_ORDERING' );?>:
						</label>
					</td>
					<td colspan="2" class="fc_search_option_cell">
						<?php echo $this->lists['orderby'];?>
					</td>
				</tr>
				
			<?php endif; ?>			
			
			<?php if ($autodisplayadvoptions) :
				$use_advsearch_options = JRequest::getInt('use_advsearch_options', 0);
				$checked_attr  = $use_advsearch_options ? 'checked=checked' : '';
				$checked_class = $use_advsearch_options ? 'highlight' : '';
				$use_advsearch_options_ff = '';
				$use_advsearch_options_ff .= '<label id="use_advsearch_options_lbl" class="flexi_radiotab rc5 '.$checked_class.'" style="display:inline-block; white-space:nowrap;" for="use_advsearch_options">';
				$use_advsearch_options_ff .= ' <input  href="javascript:;" onclick="fc_toggleClass(this.parentNode, \'highlight\');" id="use_advsearch_options" type="checkbox" name="use_advsearch_options" style="" value="1" '.$checked_attr.' />';
				$use_advsearch_options_ff .= ' &nbsp;'.JText::_('FLEXI_SEARCH_USE_ADVANCED_OPTIONS');
				$use_advsearch_options_ff .= '</label>';
			?>
			<tr class="fc_search_row_<?php echo (($r++)%2);?>">
				<td colspan="3" class="fc_search_option_cell">
					<?php echo $use_advsearch_options_ff; ?>
				</td>
			</tr>
			<?php endif; ?>
			
		</table>
	
	</fieldset>
	
<?php if ($autodisplayadvoptions) : ?>
	
	<fieldset id='fc_advsearch_options_set' class='fc_search_set'>
		<legend><?php echo JText::_('FLEXI_SEARCH_ADVANCED_SEARCH_OPTIONS'); ?></legend>
		
		<?php if(count($search_fields)>0) {?>
		<fieldset id='fc_fieldfilters_set' class='fc_search_set'>
			<legend><span class="hasTip" <?php echo $field_filters_title_tip;?> ><?php echo $infoimage; ?></span><?php echo JText::_('FLEXI_FIELD_FILTERS')." ".JText::_('FLEXI_TO_FILTER_TEXT_SEARCH_RESULTS'); ?></legend>
	
			<table id="fc_fieldfilters_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" cellspacing="1">		
			
			<?php if($show_operator = $this->params->get('show_filtersop', 1)) : ?>
				<tr class="fc_search_row_<?php echo (($r++)%2);?>">
					<td colspan="3" class="fc_search_option_cell">
						<label for="operator" class="hasTip" title='<?php echo JText::_('FLEXI_SEARCH_FILTERS_REQUIRED'); ?>::<?php echo JText::_('FLEXI_SEARCH_FILTERS_REQUIRED_TIP'); ?>'>
							<?php echo JText::_("FLEXI_SEARCH_FILTERS_REQUIRED"); ?>:
						</label>
						<?php echo $this->lists['filtersop']; ?>:
					</td>
				</tr>
			<?php endif; ?>
				
			<?php
				foreach($search_fields as $f) {
					if(!isset($fields[$f])) continue;
			?>
				<tr class="fc_search_row_<?php echo (($r++)%2);?>">
					<td class='fc_search_label_cell' valign='top'>
					<?php if ($fields[$f]->description) : ?>
						<label for="<?php echo $fields[$f]->name; ?>" class="hasTip" title="<?php echo $fields[$f]->label; ?>::<?php echo $fields[$f]->description; ?>">
							<?php echo $fields[$f]->label; ?>:
						</label>
					<?php else : ?>
						<label for="<?php echo $fields[$f]->name; ?>" class="hasTip" title="<?php echo JText::_('FLEXI_SEARCH_MISSING_FIELD_DESCR'); ?>::<?php echo JText::sprintf('FLEXI_SEARCH_MISSING_FIELD_DESCR_TIP', $fields[$f]->label ); ?>">
							<?php echo $fields[$f]->label; ?>:
						</label>
					<?php endif; ?>
					</td>
					<td colspan="2" class="fc_search_option_cell">
						<?php
						$noplugin = '<div id="fc-change-error" class="fc-error">'. JText::_( 'FLEXI_PLEASE_PUBLISH_PLUGIN' ) .'</div>';
						if(isset($fields[$f]->html)) {
							echo $fields[$f]->html;
						} else {
							echo $noplugin;
						}
						?>
					</td>
				</tr>
				<?php
					//}
				}
				?>
			</table>
			
		</fieldset>
		<?php } ?>	
		<fieldset id='fc_search_behavior_set' class='fc_search_set'>
			<legend>
				<span class="hasTip" <?php echo $other_search_areas_title_tip;?> ><?php echo $infoimage; ?></span><?php echo JText::_('FLEXI_SEARCH_ALSO_SEARCH_IN_AREAS'); ?>
			</legend>
			
			<table id="fc_search_behavior_tbl" class="fc_search_tbl <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" cellspacing="1">
				
			<?php if ( $show_searchareas ) : ?>
				
				<tr class="fc_search_row_<?php echo (($r++)%2);?>">
					<td class='fc_search_label_cell' valign='top'>
						<label class="hasTip" title='<?php echo JText::_('FLEXI_SEARCH_INCLUDE_AREAS'); ?>::<?php echo JText::_('FLEXI_SEARCH_INCLUDE_AREAS_TIP'); ?>'>
							<?php echo JText::_( 'FLEXI_SEARCH_INCLUDE_AREAS' );?> :
						</label>
					</td>
					<td colspan="2" class="fc_search_option_cell">
						<?php echo $this->lists['areas']; ?>
					</td>
				</tr>	
				
			<?php endif; ?>
				
			<?php if( $show_searchordering ) : ?>
			
				<tr class="fc_search_row_<?php echo (($r++)%2);?>">
					<td class='fc_search_label_cell' valign='top'>
						<label for="ordering" class="hasTip" title='<?php echo JText::_('FLEXI_SEARCH_ORDERING'); ?>::<?php echo JText::_('FLEXI_SEARCH_ORDERING_TIP'); ?>'>
							<?php echo JText::_( 'FLEXI_SEARCH_ORDERING' );?>:
						</label>
					</td>
					<td colspan="2" class="fc_search_option_cell">
						<?php echo $this->lists['ordering'];?>
					</td>
				</tr>
				
			<?php endif; ?>
				
			</table>
			
		</fieldset>
		
	</fieldset>

<?php endif; /* END OF IF autodisplayadvoptions */ ?>




	<table class="searchintro<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" cellspacing="1">
	<tr>
		<td colspan="3" >
			<br />
			<?php echo JText::_( 'FLEXI_SEARCH_KEYWORD' ) .' <b>'. $this->escape($this->searchword) .'</b>'; ?>
		</td>
	</tr>
	<tr>
		<td>
			<br />
			<?php echo $this->result; ?>
		</td>
	</tr>
</table>

<?php if( !$show_searchphrase ) : ?>
	<input type="hidden" name="searchphrase" value="<?php echo $default_searchphrase;?>" />
<?php endif; ?>

<?php if( $autodisplayadvoptions && !$show_filtersop ) : ?>
	<input type="hidden" name="filtersop" value="<?php echo $default_filtersop;?>" />
<?php endif; ?>

<?php if( !$autodisplayadvoptions || !$show_searchareas ) : ?>
	<input type="hidden" name="areas[]" value="flexicontent" id="area_flexicontent" />
<?php endif; ?>

<?php if( !$show_searchordering ) : ?>
	<input type="hidden" name="ordering" value="<?php echo $default_searchordering;?>" />
<?php endif; ?>

<?php if( !$show_orderby ) : ?>
	<input type="hidden" name="orderby" value="<?php echo $default_orderby;?>" />
<?php endif; ?>

<input type="hidden" name="option" value="com_flexicontent" />
<input type="hidden" name="view" value="search" />
<input type="hidden" name="task" value="search" />
<input type="hidden" name="Itemid" value="<?php echo JRequest::getVar("Itemid");?>" />
</form>
