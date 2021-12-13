<?php
defined('_JEXEC') or die('Restricted access');

/**
 * Place rendered field using the ordering of fields manager, (and unset them)
 */
foreach ($this->fields as $fn => $field) :
	if (!isset($rendered[$fn])) continue;
	?>
	<div class="fcclear"></div>

	<?php if (!is_array($rendered[$fn])) :
		echo $rendered[$fn]->html;
		unset($rendered[$fn]);
	else:
		foreach($rendered[$fn] as $n => $o) : ?>
			<div class="fcclear"></div>
			<?php echo $o->html;
		endforeach;
		unset($rendered[$fn]);
	endif;
endforeach;



/**
 * Place remaining form elements that do not have a rendered field at the bottom
 * NOTE: above (previous) loop has unset the elements that were already displayed
 */
foreach($rendered as $fn => $o) : ?>
	<div class="fcclear"></div>

	<?php if (!is_array($rendered[$fn])) :
		echo $rendered[$fn]->html; unset($rendered[$fn]);
	else:
		foreach($rendered[$fn] as $n => $o) : ?>
			<div class="fcclear"></div>
			<?php echo $o->html;
		endforeach;
		unset($rendered[$fn]);
	endif;
endforeach;