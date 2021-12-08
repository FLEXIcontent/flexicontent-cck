

<?php
foreach($captured as $fn => $i) : ?>
	<div class="fcclear"></div>

	<?php if (!is_array($captured[$fn])) :
		echo $captured[$fn]; unset($captured[$fn]);
	else:
		foreach($captured[$fn] as $n => $html) : ?>
			<div class="fcclear"></div>
			<?php echo $html;
		endforeach;
		unset($captured[$fn]);
	endif;
endforeach;
?>