<?php
$user = JFactory::getUser();
$app  = JFactory::getApplication();
$isAdmin = $app->isClient('administrator');

$action = $app->input->getCmd('action', '');
$menu_id = $app->input->getInt('menu_id', '');
$catid = $isAdmin
	? $app->input->getInt('catid', 0)
	: $app->input->getInt('maincat', 0);
$refererURL = !empty($_SERVER['HTTP_REFERER']) && flexicontent_html::is_safe_url($_SERVER['HTTP_REFERER'])
	? $_SERVER['HTTP_REFERER']
	: JUri::base();
$returnURL = $isAdmin ? '' : $refererURL;

// Get types
$types = flexicontent_html::getTypesList( $_type_ids=false, $_check_perms = false, $_published=true);
$types = is_array($types) ? $types : array();

$ctrl_task = 'items.add';
$icon = "components/com_flexicontent/assets/images/layout_add.png";
$btn_class = 'choose_type';

echo '
<div id="flexicontent" style="margin:32px;" >
	<ul class="nav nav-tabs nav-stacked">
		';
		$link = 'index.php?option=com_flexicontent&amp;view=item'
			. '&amp;task=' . $ctrl_task
			. '&amp;catid=' . $catid
			. ($menu_id ? '&amp;Itemid=' . $menu_id : '')
			. '&amp;return='.base64_encode($returnURL)
			. '&amp;' . JSession::getFormToken() . '=1';
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
					$link = 'index.php?option=com_flexicontent&amp;view=item'
						. '&amp;task=' . $ctrl_task
						. '&amp;typeid=' . $type->id
						. '&amp;catid=' . $catid
						. ($menu_id ? '&amp;Itemid=' . $menu_id : '')
						. '&amp;return='.base64_encode($returnURL)
						. '&amp;' . JSession::getFormToken() . '=1';
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

