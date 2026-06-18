<?php
/**
 * FLEXIcontent Dashboard — Version check block
 */
defined('_JEXEC') or die('Restricted access');

$check   = $this->check;
$channel = isset($check['channel']) ? $check['channel'] : 'stable';
$isBeta  = ($channel === 'beta');

// Version installée
$installed        = isset($check['current_version'])      ? $check['current_version']      : '?';
$installedDate    = isset($check['current_creationDate']) ? $check['current_creationDate'] : '';
$installedIsBeta  = (strpos($installed, 'beta') !== false || strpos($installed, 'rc') !== false);

// Versions distantes selon canal
if ($isBeta)
{
	$remoteVersion  = isset($check['beta_version'])  ? $check['beta_version']  : null;
	$remoteReleased = isset($check['beta_released']) ? $check['beta_released'] : '';
	$remoteCurrent  = isset($check['beta_current'])  ? $check['beta_current']  : null;
}
else
{
	$remoteVersion  = isset($check['stable_version'])  ? $check['stable_version']  : null;
	$remoteReleased = isset($check['stable_released']) ? $check['stable_released'] : '';
	$remoteCurrent  = isset($check['stable_current'])  ? $check['stable_current']  : null;
}

$connected    = isset($check['connect']) && $check['connect'] == 1;
$hasUpdate    = $connected && $remoteCurrent !== null && $remoteCurrent == -1;

// URL pour déclencher la mise à jour via Joomla Update Manager
$token       = \Joomla\CMS\Session\Session::getFormToken();
$updateUrl   = 'index.php?option=com_flexicontent&task=flexicontent.fcupdateredirect&format=raw&' . $token . '=1';

// Formatage date installée
$installedDateFormatted = '';
if ($installedDate)
{
	try {
		$installedDateFormatted = \Joomla\CMS\HTML\HTMLHelper::_('date', $installedDate, 'Y-m-d', 'UTC');
	} catch (Exception $e) {
		$installedDateFormatted = $installedDate;
	}
}
?>

<table class="fc-table-list fc-tbl-short" style="margin: 4px 16px 13px 4px;">
	<thead>
		<tr>
			<th colspan="2" style="height:0px; padding:0px; border:0px;"></th>
		</tr>
	</thead>
	<tbody>

		<?php if (!$connected) : ?>
		<tr>
			<td colspan="2">
				<strong><span class="text-danger"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_CONNECTION_FAILED'); ?></span></strong>
			</td>
		</tr>
		<?php endif; ?>

		<!-- Version installée -->
		<tr>
			<td>
				<span class="label"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_INSTALLED_VERSION'); ?></span>
			</td>
			<td>
				<span class="badge <?php echo $hasUpdate ? 'bg-warning badge-warning' : 'bg-success badge-success'; ?>">
					<?php echo htmlspecialchars($installed); ?>
				</span>
				<?php if ($installedIsBeta) : ?>
					&nbsp;<span class="badge bg-warning text-dark"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_UPDATE_CHANNEL_BADGE_BETA'); ?></span>
				<?php endif; ?>
				<?php if ($installedDateFormatted) : ?>
					&nbsp;<strong><?php echo \Joomla\CMS\Language\Text::_('FLEXI_RELEASED_DATE'); ?></strong>: <?php echo $installedDateFormatted; ?>
				<?php endif; ?>
			</td>
		</tr>

		<!-- Canal configuré -->
		<tr>
			<td>
				<span class="label"><?php echo \Joomla\CMS\Language\Text::_('FLEXI_UPDATE_CHANNEL_CURRENT'); ?></span>
			</td>
			<td>
				<span class="badge <?php echo $isBeta ? 'bg-warning text-dark' : 'bg-success'; ?>">
					<?php echo $isBeta
						? \Joomla\CMS\Language\Text::_('FLEXI_UPDATE_CHANNEL_BADGE_BETA')
						: \Joomla\CMS\Language\Text::_('FLEXI_UPDATE_CHANNEL_BADGE_STABLE'); ?>
				</span>
			</td>
		</tr>

		<!-- Notice beta stability -->
		<?php if ($isBeta) : ?>
		<tr>
			<td colspan="2">
				<div class="alert alert-warning py-1 px-2 mt-1 mb-0" style="font-size:0.85em;">
					⚠️ <?php echo \Joomla\CMS\Language\Text::_('FLEXI_UPDATE_BETA_STABILITY_NOTICE'); ?>
					&nbsp;<a href="index.php?option=com_installer&view=update" class="alert-link">
						<?php echo \Joomla\CMS\Language\Text::_('FLEXI_UPDATE_OPEN_INSTALLER'); ?>
					</a>
				</div>
			</td>
		</tr>
		<?php endif; ?>

		<!-- Dernière version du canal -->
		<?php if ($connected && $remoteVersion) : ?>
		<tr>
			<td>
				<span class="label">
					<?php echo $isBeta
						? \Joomla\CMS\Language\Text::_('FLEXI_LATEST_BETA_VERSION')
						: \Joomla\CMS\Language\Text::_('FLEXI_LATEST_VERSION'); ?>
				</span>
			</td>
			<td>
				<span class="badge <?php echo $hasUpdate ? 'bg-info badge-info' : 'bg-success badge-success'; ?>">
					<?php echo htmlspecialchars($remoteVersion); ?>
				</span>
				&nbsp;<strong><?php echo \Joomla\CMS\Language\Text::_('FLEXI_RELEASED_DATE'); ?></strong>:
				<?php echo htmlspecialchars($remoteReleased); ?>

				<?php if ($hasUpdate) : ?>
				&nbsp;
				<a class="btn btn-sm btn-primary d-inline-flex" href="<?php echo $updateUrl; ?>" style="margin-left:8px;">
					<span class="icon-upload" aria-hidden="true"></span>
					<?php echo \Joomla\CMS\Language\Text::_('FLEXI_INSTALL_UPDATE'); ?>
				</a>
				<?php elseif ($remoteCurrent === 0) : ?>
				&nbsp;
				<?php echo \Joomla\CMS\HTML\HTMLHelper::image(
					'components/com_flexicontent/assets/images/accept.png',
					\Joomla\CMS\Language\Text::_('FLEXI_LATEST_VERSION_INSTALLED')
				); ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php endif; ?>

	</tbody>
</table>
