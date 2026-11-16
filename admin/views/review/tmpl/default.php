<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2018, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

$form = $this->form;
?>

<div id="flexicontent" class="flexicontent">
<form action="index.php" method="post" name="adminForm" id="adminForm" class="form-validate form-horizontal">

	<?php
	if (!\Joomla\CMS\Factory::getApplication()->isClient('administrator') || \Joomla\CMS\Factory::getApplication()->input->getCmd('tmpl') === 'component')
	{
		echo \Joomla\CMS\Toolbar\Toolbar::getInstance('toolbar')->render();
		echo '<div class="fcclear"></div><br><br>';
	}

	$fieldSets = $this->form->getFieldsets();
	foreach ($fieldSets as $name => $fieldSet) :

		//$label = !empty($fieldSet->label) ? $fieldSet->label : 'COM_FLEXICONTENT_'.$name.'_FIELDSET_LABEL';
		//echo '<h2>' . \Joomla\CMS\Language\Text::_($label) . '</h2>';
	?>

	<table class="fc-form-tbl">

		<?php foreach ($this->form->getFieldset($name) as $field) : ?>
			<?php if ($field->hidden): ?>
				<tr style="display:none !important;">
					<td colspan="2"><?php echo $field->input; ?></td>
				</tr>
			<?php else: ?>
			<tr>
				<td class="key">
					<?php echo $field->label; ?>
				</td>
				<td>
					<?php echo $field->input; ?>
				</td>
			</tr>
			<?php endif; ?>
		<?php endforeach; ?>
	</table>

	<?php endforeach; ?>

	<?php echo \Joomla\CMS\HTML\HTMLHelper::_( 'form.token' ); ?>
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="reviews" />
	<input type="hidden" name="view" value="review" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="tmpl" value="<?php echo $this->_tmpl; ?>" />

</form>
</div>
<div style="margin-bottom:24px;"></div>

<script>
// A selected user (reviewer) provides the email (his account email), so the email field is cleared and disabled
(function ()
{
	var userInput = document.querySelector('[name="jform[user_id]"]');
	var email = document.getElementById('jform_email');

	// Only when creating a review (otherwise the reviewer is shown as read-only text)
	if (!userInput || !email || userInput.type !== 'hidden') return;

	var last = null;

	function syncEmail()
	{
		var hasUser = parseInt(userInput.value, 10) > 0;
		if (hasUser === last) return;
		last = hasUser;

		if (hasUser) { email.value = ''; email.disabled = true; }
		else { email.disabled = false; }
	}

	userInput.addEventListener('change', syncEmail);
	setInterval(syncEmail, 300);
	syncEmail();
})();
</script>

<?php
//keep session alive while editing
\Joomla\CMS\HTML\HTMLHelper::_('behavior.keepalive');
?>