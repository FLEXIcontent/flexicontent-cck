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
?>

<form id="flexicontent-searchForm" action="<?php echo JRoute::_('index.php?option=com_flexicontent&task=search&Itemid='.(JRequest::getVar('Itemid')));?>" method="get" name="searchForm">
	<table class="contentpaneopen<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>">
		<tr>
			<td nowrap="nowrap">
				<label for="search_searchword">
					<?php echo JText::_( $this->searchkeywordlabel ); ?>:
				</label>
			</td>
			<td nowrap="nowrap">
				<input type="text" name="searchword" id="search_searchword" size="30" maxlength="50" value="<?php echo $this->escape($this->searchword); ?>" class="inputbox" />
			</td>
			<?php if($show_operator = $this->params->get('show_operator', 1)) {?>
			<td nowrap="nowrap">
				<?php echo $this->lists['operator']; ?>
			</td>
			<?php }?>
			<td width="100%" nowrap="nowrap">
				<button name="Search" onclick="this.form.submit()" class="button"><?php echo JText::_( 'Search' );?></button>
			</td>
		</tr>
		<?php if($show_searchphrase = $this->params->get('show_searchphrase', 1)) {?>
		<tr>
			<td colspan="3">
				<?php echo $this->lists['searchphrase']; ?>
			</td>
		</tr>
		<?php }?>
		<?php
			if($this->params->get('cantypes', 1) && (count($this->fieldtypes)>0)) {
		?>
			<tr>
				<td class="key">
					<label for="fieldtypes" class="hasTip" title="Select Types::Please select types that you want to select in.">
						<?php echo JText::_("Field Types"); ?>
					</label>
				</td>
				<td>
					<?php echo $this->lists['fieldtypes'];?>
				</td>
				<td>&nbsp;</td>
			</tr>
		<?php
			}
		?>
		<?php if(!$autodisplayextrafields) {?>
		<tr>
			<td colspan="3">
				<a href="javascript:;" id="advancedsearchtext"><?php echo $this->params->get('linkadvsearch_txt', 'Advanced Search');?></a>
			</td>
		</tr>
		<?php }?>
		</table>
		<table id="extrafields" class="extrafields contentpaneopen<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>">
		<?php
			foreach ($this->fields as $field) {
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
					if(isset($field->html)) {
						echo $field->html;
					} else {
						echo $noplugin;
					}
					?>
				</td>
				<td>&nbsp;</td>
			</tr>
			<?php
				//}
			}
			?>
		</table>
		<table class="contentpaneopen<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>">
		<?php if($show_searchordering = $this->params->get('show_searchordering', 1)) {?>
		<tr>
			<td colspan="3">
				<label for="ordering">
					<?php echo JText::_( 'Ordering' );?>:
				</label>
				<?php echo $this->lists['ordering'];?>
			</td>
		</tr>
		<?php }?>
	</table>

	<?php if ($this->params->get( 'show_searchareas', 0 )) : ?>
		<?php echo JText::_( 'Search Only' );?>:
		<?php foreach ($this->searchareas['search'] as $val => $txt) :
			$checked = is_array( $this->searchareas['active'] ) && in_array( $val, $this->searchareas['active'] ) ? 'checked="checked"' : '';
		?>
		<input type="checkbox" name="areas[]" value="<?php echo $val;?>" id="area_<?php echo $val;?>" <?php echo $checked;?> />
			<label for="area_<?php echo $val;?>">
				<?php echo JText::_($txt); ?>
			</label>
		<?php endforeach; ?>
	<?php else:?>
		<input type="hidden" name="areas[]" value="flexicontent" id="area_flexicontent" />
	<?php endif; ?>

	<table class="searchintro<?php echo $this->escape($this->params->get('pageclass_sfx')); ?>">
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
<input type="hidden" name="logic" value="<?php echo $default_logic;?>" />
<?php } ?>
<?php if(!$show_searchordering) {
$default_searchordering = $this->params->get('default_searchordering', 'newest');
?>
<input type="hidden" name="ordering" value="<?php echo $default_searchordering;?>" />
<?php } ?>
</form>
