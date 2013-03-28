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
?>

<script language="javascript" type="text/javascript">
function submitbutton(pressbutton) {
	var form = document.adminForm;
	if (pressbutton == 'cancel') {
		submitform( pressbutton );
		return;
	}

	// do field validation
	if (form.altname.value == ""){
		alert( "<?php echo JText::_( 'FLEXI_ADD_NAME_TAG',true ); ?>" );
	} else {
		submitform( pressbutton );
	}
}
</script>

<?php $disabled = $this->row->url ? '' : ' disabled="disabled"'; ?>
<form action="index.php" method="post" name="adminForm" id="adminForm">

	<table class="admintable" cellspacing="0" cellpadding="0" border="0" width="100%">
		<tr>
			<td class="key">
				<label for="filename">
					<?php echo JText::_( 'FLEXI_FILENAME' ).':'; ?>
				</label>
			</td>
			<td>
				<input name="filename" value="<?php echo $this->row->filename; ?>" size="50" maxlength="100"<?php echo $disabled; ?> />
				<?php // echo htmlspecialchars($this->row->filename, ENT_QUOTES, 'UTF-8'); ?>
			</td>
		</tr>
		<tr>
			<td class="key">
				<label for="altname">
					<?php echo JText::_( 'FLEXI_DISPLAY_NAME' ).':'; ?>
				</label>
			</td>
			<td>
				<input name="altname" value="<?php echo $this->row->altname; ?>" size="50" maxlength="100" />
			</td>
		</tr>
		<tr>
			<td class="key">
				<label for="ext">
					<?php echo JText::_( 'FLEXI_FILEEXT' ).':'; ?>
				</label>
			</td>
			<td>
				<input name="ext" value="<?php echo $this->row->ext; ?>" size="5" maxlength="100"<?php echo $disabled; ?> />
			</td>
		</tr>
		<?php if (!FLEXI_ACCESS || FLEXI_J16GE) : ?>
		<tr>
			<td class="key">
				<label for="access">
					<?php echo JText::_( 'FLEXI_ACCESS_LEVEL' ); ?>
				</label>
			</td>
			<td>
				<?php echo $this->lists['access']; ?>
			</td>
		</tr>
		<?php endif; ?>
		<tr>
			<td class="key">
				<label for="file-desc">
				<?php echo JText::_( 'FLEXI_DESCRIPTION' ); ?>
				</label>
			</td>
			<td>
				<textarea name="description" cols="23" rows="5" id="file-desc"><?php echo $this->row->description; ?></textarea>
			</td>
		</tr>
	</table>


<?php
if (FLEXI_ACCESS) :
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


<?php echo JHTML::_( 'form.token' ); ?>
<input type="hidden" name="option" value="com_flexicontent" />
<?php if (!$this->row->url) : ?>
<input type="hidden" name="filename" value="<?php echo $this->row->filename; ?>" />
<input type="hidden" name="ext" value="<?php echo $this->row->ext; ?>" />
<?php endif; ?>
<input type="hidden" name="hits" value="<?php echo $this->row->hits; ?>" />
<input type="hidden" name="url" value="<?php echo $this->row->url; ?>" />
<input type="hidden" name="secure" value="<?php echo $this->row->secure; ?>" />
<input type="hidden" name="uploaded" value="<?php echo $this->row->uploaded; ?>" />
<input type="hidden" name="uploaded_by" value="<?php echo $this->row->uploaded_by; ?>" />
<input type="hidden" name="published" value="<?php echo $this->row->published; ?>" />
<input type="hidden" name="id" value="<?php echo $this->row->id; ?>" />
<input type="hidden" name="controller" value="filemanager" />
<input type="hidden" name="view" value="file" />
<input type="hidden" name="task" value="" />
</form>

<?php
//keep session alive while editing
JHTML::_('behavior.keepalive');
?>
