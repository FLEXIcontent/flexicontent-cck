<?php
$user = JFactory::getUser();
$app  = JFactory::getApplication();
$action = $app->input->getCmd('action', '');
$menu_id = $app->input->getInt('menu_id', '');

// Get types
$types = flexicontent_html::getTypesList( $_type_ids=false, $_check_perms = false, $_published=true);
$types = is_array($types) ? $types : array();

$ctrl_task = $app->isAdmin() ? 'items.add' : 'add';
$icon = "components/com_flexicontent/assets/images/layout_add.png";
$btn_class = 'choose_type';

echo '
<div id="flexicontent" style="margin:32px;" >
	<ul class="nav nav-tabs nav-stacked">
		';
		$link = $app->isAdmin()
			? "index.php?option=com_flexicontent&amp;controller=items&amp;task=".$ctrl_task."&amp;". JSession::getFormToken() ."=1"
			: "index.php?option=com_flexicontent&amp;task=".$ctrl_task."&amp;". JSession::getFormToken() ."=1";
		$_name = '- ' . JText::_("FLEXI_NO_TYPE") . ' -';
		?>
			<li>
				<a class="<?php echo $btn_class; ?>" href="<?php echo $link; ?>" target="_parent">
					<?php echo $_name; ?>
					<small class="muted">
						<?php echo JText::_('FLEXI_NEW_ITEM_FORM_NO_TYPE_DESC'); ?>
					</small>
				</a>
			</li>

		<?php
		foreach($types as $type)
		{
			$allowed = ! $type->itemscreatable || $user->authorise('core.create', 'com_flexicontent.type.' . $type->id);

			/*
			 * Creation not allowed, and item type is not visible
			 */
			if (!$allowed && $type->itemscreatable == 1)
			{
				continue;
			}

			/*
			 * Creation not allowed, but item type is visible
			 */
			elseif (!$allowed && $type->itemscreatable == 2)
			{
				$link = "javascript:;";
				$uncut_length = 0;
				?>
			<li>
				<a class="<?php echo $btn_class; ?>" href="<?php echo $link; ?>" target="_parent" style="color: gray; cursor: not-allowed;">
					<?php echo $type->name; ?>
					<small class="muted">
						<?php echo $type->description ? JText::_('FLEXI_NO_DESCRIPTION') :
							flexicontent_html::striptagsandcut(
								$type->description, $cut_text_length = 220, $uncut_length,
								$ops = array(
									'cut_at_word' => true,
									'more_toggler' => true,
									'more_icon' => 'icon-paragraph-center',
									'more_txt' => 2,
									'modal_title' => $type->name
								)
							);
						?>
					</small>
				</a>
			</li>
			<?php
			}

			/*
			 * Creation (of this item type) is allowed
			 */
			else
			{
				if ($action !== 'new')
				{
					$link = 'alert(\'No action\');';
				}
				else
				{
					$link = $app->isAdmin()
						? "index.php?option=com_flexicontent&amp;controller=items&amp;task=".$ctrl_task."&amp;typeid=".$type->id."&amp;". JSession::getFormToken() ."=1"
						: "index.php?option=com_flexicontent&amp;task=".$ctrl_task."&amp;typeid=".$type->id.($menu_itemid ? '&amp;menu_id='.$menu_itemid : '')."&amp;". JSession::getFormToken() ."=1";
				}
				?>
			<li>
				<a class="<?php echo $btn_class; ?>" href="<?php echo $link; ?>" target="_parent">
					<?php echo JText::_($type->name); ?>
					<small class="muted">
						<?php echo $type->description
							? flexicontent_html::striptagsandcut(
									$type->description, $cut_text_length = 180, $uncut_length,
									$ops = array(
										'cut_at_word' => true,
										'more_toggler' => 2,
										'more_icon' => 'icon-paragraph-center',
										'more_txt' => JText::_('FLEXI_ABOUT'),
										'modal_title' => $type->name,
										'more_box_id' => 'fc-type-desc-' . $type->id
									)
								)
						: JText::_('FLEXI_NO_DESCRIPTION'); ?>
					</small>
				</a>
				<div style="display: none;" id="fc-type-desc-<?php echo $type->id; ?>">
					<?php echo $type->description; ?>
				</div>
			</li>
			<?php
			}
		}
		echo '
	</ul>
</div>
		';

