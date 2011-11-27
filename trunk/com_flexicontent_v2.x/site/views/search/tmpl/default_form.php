<?php defined('_JEXEC') or die('Restricted access');
$autodisplayextrafields = $this->params->get('autodisplayextrafields', 1);
if(!$autodisplayextrafields) {
$this->document->addScriptDeclaration('
window.addEvent("domready", function() {
  var status = {
    "true": "open",
    "false": "close"
  };

  // -- vertical

  var myVerticalSlide = new Fx.Slide("extrafields").hide();

  /*$("advancedsearchtext").addEvent("click", function(event){
    event.stop();
    myVerticalSlide.slideIn();
  });*/

  $("advancedsearchtext").addEvent("click", function(event){
    //event.stop();
    //myVerticalSlide.slideOut();
    myVerticalSlide.toggle();
  });
});
');
}

$r = 0;
?>

<form id="flexicontent-searchForm" action="<?php echo JRoute::_('index.php?option=com_flexicontent&task=search&Itemid='.(JRequest::getVar('Itemid')));?>" method="get" name="searchForm">
	
	<fieldset id='fc_search_set_advsearch' class='fc_search_set'>
		<legend><?php echo JText::_('FLEXI_BASIC_SEARCH'); ?></legend>
		
		<table id="basicfields" class="basicfields <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" cellspacing="1">
			
			<tr class="fc_search_row_<?php echo (($r++)%2);?>">
				<td nowrap="nowrap" class='fc_search_label_cell'>
					<label for="search_searchword" class='hasTip' title='<?php echo JText::_('FLEXI_SEARCH_KEYWORD'); ?>::<?php echo JText::_('FLEXI_SEARCH_KEYWORD_TIP'); ?>'>
						<?php echo JText::_('FLEXI_SEARCH_KEYWORD'); ?>:
					</label>
				</td>
				<td nowrap="nowrap" class="fc_search_option_cell">
					<input type="text" name="searchword" id="search_searchword" size="30" maxlength="50" value="<?php echo $this->escape($this->searchword); ?>" class="inputbox" />
				</td>
				<td width="100%" nowrap="nowrap">
					<button name="Search" onclick="this.form.submit()" class="button"><?php echo JText::_( 'Search' );?></button>
				</td>
			</tr>
			
			<?php if ($show_searchphrase = $this->params->get('show_searchphrase', 1)) : ?>
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
			
			<?php if ($this->params->get('cantypes', 1) && (count($this->fieldtypes)>0)) : ?>
			<tr class="fc_search_row_<?php echo (($r++)%2);?>">
				<td nowrap="nowrap" class='fc_search_label_cell' valign='top'>
					<label for="fieldtypes" class='hasTip' title='<?php echo JText::_('FLEXI_SEARCH_CONTENT_TYPE'); ?>::<?php echo JText::_('FLEXI_SEARCH_CONTENT_TYPE_TIP'); ?>'>
						<?php echo JText::_('FLEXI_SEARCH_CONTENT_TYPE'); ?>:
					</label>
				</td>
				<td colspan="2" class="fc_search_option_cell">
					<?php echo $this->lists['fieldtypes'];?>
				</td>
			</tr>
			<?php endif; ?>
			
			<?php if (!$autodisplayextrafields) : ?>
			<tr class="fc_search_row_<?php echo (($r++)%2);?>">
				<td colspan="3" class="fc_search_option_cell">
					<a href="javascript:;" id="advancedsearchtext"><?php echo $this->params->get('linkadvsearch_txt', 'Advanced Search');?></a>
				</td>
			</tr>
			<?php endif; ?>
			
		</table>
	
	</fieldset>
			
	<fieldset id='fc_search_set_advsearch' class='fc_search_set'>
		<legend><?php echo JText::_('FLEXI_ADVANCED_SEARCH'); ?></legend>
		
		<table id="extrafields" class="extrafields <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" cellspacing="1">		
		
		<?php
			//params[search_fields]
			$search_fields = $this->params->get('search_fields', '');
			$search_fields = explode(",", $search_fields);
			$fields = &$this->fields;
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
		
	<fieldset id='fc_search_set_search_behavior' class='fc_search_set'>
		<legend><?php echo JText::_('FLEXI_SEARCH_BEHAVIOR'); ?></legend>
		
		<table id="resultoptions" class="resultoptions <?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" cellspacing="1">
			<?php
			if($show_operator = $this->params->get('show_operator', 1)) :
				$operator_desc = $this->params->get('operator_desc', 'FLEXI_OPERATOR_DESC');
				$operator_desc = ($operator_desc=='FLEXI_OPERATOR_DESC')?JText::_('FLEXI_OPERATOR_DESC'):$operator_desc;
			?>
			<tr class="fc_search_row_<?php echo (($r++)%2);?>">
				<td class='fc_search_label_cell' valign='top'>
					<label for="operator" class="hasTip" title='<?php echo JText::_('FLEXI_BASIC_ADVANCED_COMBINATION'); ?>::<?php echo JText::_('FLEXI_BASIC_ADVANCED_COMBINATION_TIP'); ?>'>
						<?php echo JText::_("FLEXI_BASIC_ADVANCED_COMBINATION"); ?>:
					</label>
				</td>
				<td colspan="2" class="fc_search_option_cell">
					<?php echo $this->lists['operator']; ?>:
				</td>
			</tr>
			<?php endif; ?>
			
			<?php if($show_searchordering = $this->params->get('show_searchordering', 1)) : ?>
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
			
			<?php if ($this->params->get( 'show_searchareas', 0 )) : ?>
			
			<tr class="fc_search_row_<?php echo (($r++)%2);?>">
				<td class='fc_search_label_cell' valign='top'>
					<label class="hasTip" title='<?php echo JText::_('FLEXI_SEARCH_OTHER_CONTENT'); ?>::<?php echo JText::_('FLEXI_SEARCH_OTHER_CONTENT_TIP'); ?>'>
						<?php echo JText::_( 'FLEXI_SEARCH_OTHER_CONTENT' );?>:
					</label>
				<td colspan="2" class="fc_search_option_cell">
					
				<?php // DISABLE search areas 'content' and old 'flexisearch' ?>
				<?php unset($this->searchareas['search']['content']); unset($this->searchareas['search']['flexisearch']); ?>
				
				<?php foreach ($this->searchareas['search'] as $val => $txt) :
					$checked = is_array( $this->searchareas['active'] ) && in_array( $val, $this->searchareas['active'] ) ? 'checked="checked"' : '';
				?>
					<input type="checkbox" name="areas[]" value="<?php echo $val;?>" id="area_<?php echo $val;?>" <?php echo $checked;?> />
					<label for="area_<?php echo $val;?>">
						<?php echo JText::_($txt); ?>
					</label>
				<?php endforeach; ?>
				</td>
			</tr>	
			
			<?php else:?>
		
			<tr>
				<td colspan="3">
				<input type="hidden" name="areas[]" value="flexicontent" id="area_flexicontent" />
				</td>
			</tr>	
		
		<?php endif; ?>

		</table>
		
	</fieldset>
	
	<table class="searchintro<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>" cellspacing="1">
	<tr>
		<td colspan="3" >
			<br />
			<?php echo JText::_( 'Search Keyword' ) .' <b>'. $this->escape($this->searchword) .'</b>'; ?>
		</td>
	</tr>
	<tr>
		<td>
			<br />
			<?php echo $this->result; ?>
		</td>
	</tr>
</table>

<br />
<?php if($this->total > 0) : ?>
<div align="center">
	<div style="float: right;">
		<label for="limit">
			<?php echo JText::_( 'Display Num' ); ?>
		</label>
		<?php echo $this->pagination->getLimitBox( ); ?>
	</div>
	<div>
		<?php //echo $this->pagination->getPagesCounter(); ?>
		<?php echo $this->pagination->getPagesLinks(); ?>
	</div>
</div>
<?php endif; ?>
<?php if(!$show_searchphrase) {
$default_searchphrase = $this->params->get('default_searchphrase', 'all');
?>
<input type="hidden" name="searchphrase" value="<?php echo $default_searchphrase;?>" />
<?php } ?>
<?php if(!$show_operator) {
$default_operator = $this->params->get('default_operator', 'OR');
?>
<input type="hidden" name="operator" value="<?php echo $default_operator;?>" />
<?php } ?>
<?php if(!$show_searchordering) {
$default_searchordering = $this->params->get('default_searchordering', 'newest');
?>
<input type="hidden" name="ordering" value="<?php echo $default_searchordering;?>" />
<?php } ?>

<input type="hidden" name="Itemid" value="<?php echo JRequest::getVar("Itemid");?>" />
</form>
