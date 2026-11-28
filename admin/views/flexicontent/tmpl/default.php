<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2019, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\String\StringHelper;
require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/html/fc_service_registry.php';
flexicontent_register_html_helpers(JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/html');

$app      = \Joomla\CMS\Factory::getApplication();
$option   = $app->input->get('option', '', 'CMD');
$user     = \Joomla\CMS\Factory::getApplication()->getIdentity();
$template = $app->getTemplate();
$session  = \Joomla\CMS\Factory::getApplication()->getSession();
$hlpname  = 'fcbase';

$btn_class = 'btn';
$tooltip_class = 'hasTooltip';
$edit_item_txt = \Joomla\CMS\Language\Text::_( 'FLEXI_EDIT_ITEM' );

// hide dashboard buttons
$dashboard_buttons_hide = $this->params->get('dashboard_buttons_hide', array());
$dashboard_buttons_hide = FLEXIUtilities::paramToArray($dashboard_buttons_hide);

$sbtns = array_flip($dashboard_buttons_hide);
// Reviews button: show only if reviews are enabled in the voting field and user can manage reviews
require_once JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/dashboard.php';
$showReviews = FlexicontentDashboard::reviewsEnabled($this->perms);


// disable dashboard sliders
$dashboard_sliders_disable = $this->params->get('dashboard_sliders_disable', array());
$dashboard_sliders_disable  = FLEXIUtilities::paramToArray($dashboard_sliders_disable);

// Other options
$modal_item_edit = $this->params->get('dashboard_modal_item_edit', 1);
$onclick_modal_edit = $modal_item_edit ? 'onclick="var url = this.getAttribute(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\'); return false;"' : '';
$disable_fc_logo = $this->params->get('dashboard_disable_fc_logo', 0);
$hide_fc_license_credits = $this->params->get('dashboard_hide_fc_license_credits', 1); /* hide inside sliders */

// Get/Check PHP requiremenets
$php_lims = flexicontent_html::checkPHPLimits();

$ssliders = array_flip($dashboard_sliders_disable);
// The state sliders live in the "Waiting for action" card. PHP/DB and About
// were moved to the "Update" card accordion, so they no longer count here.
$skip_sliders = isset($sbtns['pending']) && isset($sbtns['revised']) && isset($sbtns['inprogress']) && isset($sbtns['draft']);
$skip_sliders = $skip_sliders && $this->dopostinstall && $this->allplgpublish;

// ensures the PHP version is correct
if (version_compare(PHP_VERSION, FLEXI_PHP_NEEDED, '<'))
{
	echo '<div class="fc-mssg fc-error">';
	echo \Joomla\CMS\Language\Text::sprintf( 'FLEXI_UPGRADE_PHP_VERSION_GE', FLEXI_PHP_NEEDED) . '<br/>';
	echo '</div>';
	return false;
}

$ctrl = 'items.';
$items_task = 'task=items.';
?>

<div id="flexicontent" class="flexicontent">

<form action="index.php" method="post" name="adminForm" id="adminForm">

<div class="<?php echo FLEXI_J40GE ? 'row' : 'row-fluid'; ?>">

<?php if (!empty( $this->sidebar) && FLEXI_J40GE == false) : ?>

	<div id="j-sidebar-container" class="span2 col-md-2">

		<?php echo str_replace('type="button"', '', $this->sidebar); ?>

	</div>
	
	<div id="j-main-container" class="span10 col-md-10">

	<?php else : ?>

		<div id="j-main-container" class="span12 col-md-12">

<?php endif;?>


		<?php
		$config_saved = $this->params->get('flexi_cat_extension');


		

		if (version_compare(PHP_VERSION, FLEXI_PHP_RECOMMENDED, '<'))
		{
			$app->enqueueMessage(\Joomla\CMS\Language\Text::sprintf( 'PHP version >= %s is recommended', FLEXI_PHP_RECOMMENDED), 'warning');
		}
		$_title = "PHP/DB Requirements";

		// Set a system message with warning of failed PHP limits
		$phplimits_printed = $app->getUserStateFromRequest( $option.'.flexicontent.phplimits_printed',	'phplimits_printed',	0, 'int' );
		if ($this->dopostinstall && isset($php_lims['warning']) && $config_saved)
		{
			$app->setUserState( $option.'.flexicontent.phplimits_printed', $phplimits_printed+1 );
			if ($phplimits_printed < 1)
			{
				$mssg = '<b>PHP/DB requirements</b><br/>';
				foreach($php_lims as $type => $html) {
					$mssg .= implode('<br/>', $html);
				}
				$mssg .= \Joomla\CMS\Language\Text::sprintf(
					'<br/>(you may have to contact your web hosting company for setting these for you)<br/>
					For more information on changing these limitations, please see this article: %s',
					'<a href="http://www.flexicontent.org/documentation/faq/78-installation-upgrade/591">PHP/DB Requirements</a>'
				);
				\Joomla\CMS\Factory::getApplication()->enqueueMessage($mssg, 'warning', '');
			}
		}

		if ( isset($php_lims['warning']) )
			$_title .= ' - <span class="badge badge-important">Warning</span>';
		else if ( isset($php_lims['notice']) )
			$_title .= ' - <span class="badge bg-warning badge-warning">Notice</span>';
		else
			$_title .= ' - <span class="badge bg-success badge-success">OK</span>';
		?>

		<?php
		/*
		 * Build the content of the "Update" card accordion up front, so it can
		 * be rendered independently of the "Waiting for action" card:
		 *   1. version comparison  2. PHP/DB requirements  3. About FLEXIcontent
		 */

		// 1. Version comparison (auto-loaded, or loaded when the slide is expanded)
		$fc_version_check = '';
		if (!isset($ssliders['version']))
		{
			$auto_load_version = $this->params->get('show_updatecheck', 1) && $this->perms->CanConfig;
			$token = \Joomla\CMS\Session\Session::getFormToken();
			$version_labels = json_encode(array(
				'ok'     => \Joomla\CMS\Language\Text::_('FLEXI_VERSION_STATUS_OK'),
				'update' => \Joomla\CMS\Language\Text::_('FLEXI_VERSION_STATUS_UPDATE'),
				'error'  => \Joomla\CMS\Language\Text::_('FLEXI_VERSION_STATUS_ERROR'),
			));
			$version_url = json_encode('index.php?option=com_flexicontent&task=flexicontent.fcversioncompare&format=raw&' . $token . '=1');
			$auto_load_js = $auto_load_version ? 'true' : 'false';
			$this->document->addScriptDeclaration("
				FlexiForm.ready(() => {
					const labels = $version_labels;
					const result = document.getElementById('displayfversion');
					let loading = false;
					const badge = status => {
						const node = document.getElementById('fc-version-badge');
						if (!node) return;
						node.classList.remove('bg-secondary','bg-success','badge-success','bg-warning','badge-warning','bg-danger');
						node.classList.add(status === 'update' ? 'bg-warning' : status === 'error' ? 'bg-danger' : 'bg-success');
						node.textContent = labels[status] || labels.ok;
					};
					const load = async () => {
						if (!result || loading || result.textContent.trim()) return;
						loading = true; result.setAttribute('aria-busy', 'true');
						try {
							const response = await fetch($version_url, {credentials:'same-origin'});
							if (!response.ok) throw Error(response.statusText);
							await FlexiForm.insertHTML(result, await response.text());
							badge(result.querySelector('#fc-version-status')?.dataset.status || 'ok');
						} catch (error) { badge('error'); }
						finally { loading = false; result.removeAttribute('aria-busy'); }
					};
					if ($auto_load_js) load();
					document.getElementById('fc-dash-update-sliders-version')?.addEventListener('show.bs.collapse', load);
				});
			");

			$fc_version_check = '<div id="displayfversion" style="min-height:20px;"></div>';
		}

		// 2. PHP/DB requirements
		ob_start();
		echo '<div class="fc-mssg fc-note">';
		foreach ($php_lims as $type => $html) {
			echo implode('<br/>', $html);
		}
		echo \Joomla\CMS\Language\Text::sprintf(
			'<br/>(you may have to contact your web hosting company for setting these for you)<br/>
			For more information on changing these limitations, please see this article: %s',
			'<a href="http://www.flexicontent.org/documentation/faq/78-installation-upgrade/591">PHP/DB Requirements</a>'
		);
		echo '</div>';
		$fc_requirements = ob_get_clean();

		// 3. About FLEXIcontent (no nested card/header: it lives inside an accordion slide)
		ob_start(); ?>
		<div id="fc-dash-credits">
			<div class="fc-board-set-inner card-body">
			<?php
				$logo_style = ';';
				if (!$disable_fc_logo)
				{
					echo \Joomla\CMS\HTML\HTMLHelper::image('administrator/components/com_flexicontent/assets/images/logo.png', 'FLEXIcontent', ' id="fc-dash-logo" ');
				}
			?>
				<span id="fc-dash-license" class="nowrap_box fc-mssg-inline fc-info fc-nobgimage" style="">
					FLEXIcontent <?php echo FLEXI_VERSION . ' ' . FLEXI_RELEASE; ?><br/> GNU/GPL licence, Copyright &copy; 2009-2026
				</span><br/><br/>
				<span id="fc-dash-devs" class="nowrap_box">
					<span class="fc-mssg-inline fc-nobgimage fc-noborder nowrap_box" style="text-align:center;" >
						<span class="label text-white bg-info label-info <?php echo $tooltip_class;?>" title="Core developer">Georgios Papadakis</span><br/><br/>
						<a class="<?php echo $btn_class.' btn-small btn-primary '.$tooltip_class;?>" style="" href="http://www.flexicontent.org" title="FLEXIcontent home page" target="_blank">FLEXIcontent.org</a>
					</span>

					<span class="fc-mssg-inline fc-nobgimage fc-noborder nowrap_box" style="text-align:center;" >
						<span class="label text-white bg-info label-info <?php echo $tooltip_class;?>" title="Core developer">Emmanuel Danan</span><br/><br/>
						<a class="<?php echo $btn_class.' btn-small btn-primary '.$tooltip_class;?>" style="" href="http://www.agerix.fr" title="Agerix : L'agence digitale gauloise<br>Spécialistes Joomla - conception, refonte, migration et maintenance." target="_blank">agerix.fr</a>
					</span>

					<span class="fc-mssg-inline fc-nobgimage fc-noborder nowrap_box">
						<span class="label <?php echo $tooltip_class;?>" title="Core Developer">Yannick Berges</span><br/><br/>
						<a class="<?php echo $btn_class.' btn-small '.$tooltip_class;?>" style="" href="http://com3elles.com/" target="_blank" title="<?php echo flexicontent_html::getToolTip("Com'3Elles", "Com'3Elles, agence de communication, conseil et formations", 0, 1); ?>">
							com3elles.com
						</a>
					</span>

					<span class="fc-mssg-inline fc-nobgimage fc-noborder nowrap_box">
						<span class="label <?php echo $tooltip_class;?>" title="Developer / Contributor">iamrobert Digital Design</span><br/><br/>
						<a class="<?php echo $btn_class.' btn-small '.$tooltip_class;?>" style="" href="https://www.iamrobert.com/" target="_blank" title="iamrobert Digital Design">
							iamrobert.com
						</a>
					</span>

					<span class="fc-mssg-inline fc-nobgimage fc-noborder nowrap_box">
						<span class="label <?php echo $tooltip_class;?>" title="Developer / Contributor">Roman Lipatov</span><br/><br/>
						<a class="<?php echo $btn_class.' btn-small '.$tooltip_class;?>" style="" href="https://github.com/lipatovroman" target="_blank" title="Roman Lipatov on GitHub">
							github.com/lipatovroman
						</a>
					</span>

					<span class="fc-mssg-inline fc-nobgimage fc-noborder nowrap_box">
						<span class="label text-white bg-info label-info <?php echo $tooltip_class;?>" title="Core Developer">Marvelic Engine</span><br/><br/>
						<a class="<?php echo $btn_class.' btn-small '.$tooltip_class;?>" style="" href="http://www.marvelic.co.th" target="_blank" title="<?php echo flexicontent_html::getToolTip("Marvelic Engine", "Marvelic Engine is a Joomla consultancy based in Bangkok, Thailand. Support services include consulting, Joomla implementation, training, and custom extensions development.", 0, 1); ?>">
							marvelic.co.th
						</a>
					</span>

					<span class="fc-mssg-inline fc-nobgimage fc-noborder nowrap_box">
						<span class="label <?php echo $tooltip_class;?>" title="Core Developer">Suriya Kaewmungmuang</span>
					</span>

					<span class="fc-mssg-inline fc-nobgimage fc-noborder nowrap_box">
						<span class="label <?php echo $tooltip_class;?>" title="Developer / Contributor">Ruben Reyes</span><br/><br/>
						<a class="<?php echo $btn_class.' btn-small '.$tooltip_class;?>" style="" href="http://www.lyquix.com" target="_blank" title="<?php echo flexicontent_html::getToolTip("Lyquix", "Lyquix - Philadelphia Marketing, Advertising, Web Design and Development Agency", 0, 1); ?>">
							lyquix.com
						</a>
					</span>
				</span>

			</div>
		</div>
		<?php $fc_logo_license = ob_get_clean();
		?>

		<div class="fc-dashboard-notices">
		<?php
		if (!$this->dopostinstall)
		{
			if ($this->perms->CanConfig)
			{
				echo '<div class="fc-mssg fc-warning">';
				echo \Joomla\CMS\Language\Text::_( 'FLEXI_DO_POSTINSTALL' );
				echo '</div>';
			}
		}
		else if ( !$this->existmenu || !$this->existcat || !$config_saved )
		{
			echo '<div class="fc-mssg fc-warning">';
			if ( !$config_saved )
			{
				$fc_screen_width = (int) $session->get('fc_screen_width', 0, 'flexicontent');
				$_width = ($fc_screen_width && $fc_screen_width-84 > 940 ) ? ($fc_screen_width-84 > 1400 ? 1400 : $fc_screen_width-84 ) : 940;
				$fc_screen_height = (int) $session->get('fc_screen_height', 0, 'flexicontent');
				$_height = ($fc_screen_height && $fc_screen_height-128 > 550 ) ? ($fc_screen_height-128 > 1000 ? 1000 : $fc_screen_height-128 ) : 550;
				$conf_link = 'index.php?option=com_config&view=component&component=com_flexicontent&path=';
				$conf_link = '<a href="'.$conf_link.'" class="btn btn-warning">';

				echo \Joomla\CMS\Language\Text::sprintf( 'FLEXI_CONFIGURATION_NOT_SAVED', $conf_link.\Joomla\CMS\Language\Text::_("FLEXI_CONFIG").'</a>' ) . '<br/>';
			}
			else if (!$this->existcat)	echo \Joomla\CMS\Language\Text::_( 'FLEXI_NO_CATEGORIES_CREATED' );
			else if (!$this->existmenu)	echo \Joomla\CMS\Language\Text::_( 'FLEXI_NO_MENU_CREATED' );
			echo '</div>';
		}
		?>


		<?php
		// Optional task: move the DB tables to InnoDB
		if ($this->dopostinstall && $config_saved && !empty($this->dbengine_pending))
		{
			echo '<div class="fc-mssg fc-info">'
				. \Joomla\CMS\Language\Text::sprintf('FLEXI_DBENGINE_NOTE', (int) $this->dbengine_pending)
				. ' <a class="btn btn-small btn-sm" href="index.php?option=com_flexicontent&amp;view=flexicontent&amp;layout=dbengine">' . \Joomla\CMS\Language\Text::_('FLEXI_DBENGINE_OPEN') . '</a>'
				. '</div>';
		}
		?>
		</div><!-- END OF .fc-dashboard-notices -->

		<?php
		// Customizable blocks of buttons (layout is stored in the component parameter "dashboard_layout")
		$dash_ready    = $this->dopostinstall && $config_saved;
		$dash_is_admin = $user->authorise('core.admin', 'com_flexicontent');

		$dash_layout   = FlexicontentDashboard::getLayout($this->params);
		$dash_titles   = FlexicontentDashboard::getSysTitles($dash_layout);
		$dash_registry = array();

		if ($dash_ready)
		{
			$dash_registry = FlexicontentDashboard::getRegistry($this->perms, $this->params, $user, $showReviews);

			if ($dash_is_admin)
			{
				$dash_data = FlexicontentDashboard::getEditorData($dash_layout, $dash_registry);
				$dash_data['url']   = 'index.php?option=com_flexicontent&format=raw&task=flexicontent.';
				$dash_data['token'] = \Joomla\CMS\Session\Session::getFormToken();
				$dash_data['icons'] = array(
					'icon-link', 'icon-home', 'icon-star', 'icon-cog', 'icon-wrench', 'icon-file-alt', 'icon-folder', 'icon-images',
					'icon-users', 'icon-tags', 'icon-search', 'icon-chart', 'icon-book', 'icon-upload', 'icon-download', 'icon-envelope',
					'icon-globe', 'icon-bookmark', 'icon-heart', 'icon-flag', 'icon-help', 'icon-puzzle', 'icon-cube', 'icon-power-cord',
					'icon-paint-brush', 'icon-comment', 'icon-lock', 'icon-calendar', 'icon-print', 'icon-cart', 'icon-database', 'icon-code',
				);
				$this->document->addScriptOptions('com_flexicontent.dashboard', $dash_data);

				foreach (array(
					'FLEXI_DASH_CUSTOMIZE', 'FLEXI_DASH_ADD_BLOCK', 'FLEXI_DASH_RESET', 'FLEXI_DASH_RESET_CONFIRM', 'FLEXI_DASH_ADD_BUTTON',
					'FLEXI_DASH_NEW_BLOCK', 'FLEXI_DASH_BLOCK_TITLE', 'FLEXI_DASH_WIDTH', 'FLEXI_DASH_REMOVE', 'FLEXI_DASH_BUILTIN_BUTTONS',
					'FLEXI_DASH_CUSTOM_LINK', 'FLEXI_DASH_LINK_TITLE', 'FLEXI_DASH_LINK_URL', 'FLEXI_DASH_ICON', 'FLEXI_DASH_OPEN_IN',
					'FLEXI_DASH_TARGET_SELF', 'FLEXI_DASH_TARGET_BLANK', 'FLEXI_DASH_TARGET_MODAL', 'FLEXI_DASH_NO_MORE_BUTTONS',
					'FLEXI_DASH_SAVE_ERROR', 'FLEXI_DASH_UNAVAILABLE', 'FLEXI_DASH_TOGGLE_PLUS', 'FLEXI_DASH_EMPTY_BLOCK',
					'FLEXI_DASH_DELETE_BLOCK_CONFIRM', 'FLEXI_DASH_SYSTEM_BLOCK', 'FLEXI_DASH_BG_COLOR', 'FLEXI_DASH_EXPORT', 'FLEXI_DASH_IMPORT', 'FLEXI_DASH_IMPORT_OK', 'FLEXI_DASH_IMPORT_ERROR', 'FLEXI_DASH_BG_RESET', 'FLEXI_DASH_STANDARD_BUTTONS', 'FLEXI_DASH_ALREADY_USED', 'FLEXI_DASH_PICK_HINT', 'FLEXI_DASH_EDIT_LINK', 'FLEXI_DASH_HINT', 'FLEXI_DASH_URL_REQUIRED',
					'FLEXI_SAVE', 'FLEXI_CANCEL', 'FLEXI_ADD', 'FLEXI_DELETE',
				) as $dash_txt)
				{
					\Joomla\CMS\Language\Text::script($dash_txt);
				}

				$this->document->addScript(\Joomla\CMS\Uri\Uri::base(true) . '/components/com_flexicontent/assets/js/dashboard_layout.js', array('version' => FLEXI_VHASH), array('defer' => true));
			}
		}
		?>

		<?php
		// The system blocks are rendered first, then placed by the dashboard layout like the blocks of buttons
		$dash_sys = array();
		ob_start();
?>
			<section class="fc-board-set card fc-dashboard-card fc-dashboard-updatecheck">
				<h3 class="fc-board-header card-header"><?php echo htmlspecialchars($dash_titles['sysinfo'], ENT_QUOTES, 'UTF-8'); ?></h3>
				<div class="fc-board-set-inner card-body">
		<?php
		echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.startAccordion', 'fc-dash-update-sliders', array());

		if ($fc_version_check !== '')
		{
			$version_title = \Joomla\CMS\Language\Text::_( 'FLEXI_UPDATE_CHECK' )
				. ' <span id="fc-version-badge" class="badge bg-secondary">&hellip;</span>';
			echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addSlide', 'fc-dash-update-sliders', $version_title, 'fc-dash-update-sliders-version' );
			echo $fc_version_check;
			echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endSlide');
		}

		echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addSlide', 'fc-dash-update-sliders', $_title, 'fc-dash-update-sliders-requirements' );
		echo $fc_requirements;
		echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endSlide');

		echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addSlide', 'fc-dash-update-sliders', \Joomla\CMS\Language\Text::_( 'About FLEXIcontent' ), 'fc-dash-update-sliders-aboutflexi' );
		echo $fc_logo_license;
		echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endSlide');

		echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endAccordion');
		?>
				</div>
			</section>
<?php
		$dash_sys['sysinfo'] = ob_get_clean();
		ob_start();
?>
			<section class="fc-board-set card fc-dashboard-card fc-dashboard-waiting">
				<h3 class="fc-board-header card-header"><?php echo htmlspecialchars($dash_titles['waiting'], ENT_QUOTES, 'UTF-8'); ?></h3>
				<div class="fc-board-set-inner card-body">
		<?php
		if (!$this->dopostinstall || !$this->allplgpublish) :

			// Make sure POST-INSTALLATION Task slider is open
			echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.startAccordion', 'fc-dash-sliders', array('active' => 'fc-dash-sliders-postinstall'));

			$title = \Joomla\CMS\Language\Text::_( 'FLEXI_POST_INSTALL' );
			echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addSlide', 'fc-dash-sliders', $title, 'fc-dash-sliders-postinstall' );
			echo $this->loadTemplate('postinstall');
			echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endSlide');

			echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endAccordion');


		elseif (!$skip_sliders && $config_saved) :

			echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.startAccordion', 'fc-dash-sliders', array());

			if (!isset($ssliders['pending'])):
				$title = \Joomla\CMS\Language\Text::_( 'FLEXI_PENDING_SLIDER' ).' - <span class="badge bg-warning badge-warning">'.$this->totalrows['pending'].'</span>';
				echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addSlide', 'fc-dash-sliders', $title, 'fc-dash-sliders-pending' );
				$show_all_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_state=PE';
			?>
			<table class="table">

				<thead>
				<tr>
					<th><?php echo \Joomla\CMS\Language\Text::_('FLEXI_TITLE'); ?></th>
					<th><?php echo \Joomla\CMS\Language\Text::_('FLEXI_CREATED'); ?></th>
					<th><?php echo \Joomla\CMS\Language\Text::_('FLEXI_AUTHOR'); ?></th>
				</tr>
				</thead>

				<tbody>
				<?php
				$k = 0;
				$n = count($this->pending);

				for ($i = 0, $n; $i < $n; $i++)
				{
					$row          = $this->pending[$i];
					$assetName    = 'com_content.article.' . $row->id;
					$isAuthor     = $row->created_by && $row->created_by == $user->id;
					$row->canEdit = $user->authorise('core.edit', $assetName) || ($isAuthor && $user->authorise('core.edit.own', $assetName));
				?>
				<tr>
					<td>
					<?php
					echo \Joomla\CMS\HTML\HTMLHelper::_($hlpname . '.edit_link', $row, $i, $row->canEdit, $config = array(
						'ctrl'     => 'items',
						'view'     => 'item',
						'onclick'  => 'var url = this.getAttribute(\'data-href\'); var the_dialog = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, fc_edit_fcitem_modal_close, {title:\'' . \Joomla\CMS\Language\Text::_('FLEXI_EDIT', true) . '\', loadFunc: fc_edit_fcitem_modal_load}); return false;',
					));
					?>
					</td>
					<td><?php echo \Joomla\CMS\HTML\HTMLHelper::_('date',  $row->created); ?></td>
					<td><?php echo $row->creator; ?></td>
				</tr>
				<?php $k = 1 - $k; } ?>
				<tr>
					<td colspan="3">
						... &nbsp;
						<?php echo '<button type="button" class="'.$btn_class.'" onclick="window.open(\''.$show_all_link.'\')" >' . \Joomla\CMS\Language\Text::_('FLEXI_SHOW_ALL') . ' ( ' . \Joomla\CMS\Language\Text::_('FLEXI_ITEMS_MANAGER') . ' )</button><br/>'; ?>
					</td>
				</tr>
				</tbody>

			</table>
			<?php
				echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endSlide');
			endif; /* !isset($ssliders['pending']) */ ?>


			<?php if (!isset($ssliders['revised'])):
				$title = \Joomla\CMS\Language\Text::_( 'FLEXI_REVISED_VER_SLIDER' ).' - <span class="badge bg-warning badge-warning">'.$this->totalrows['revised'].'</span>';
				echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addSlide', 'fc-dash-sliders', $title, 'fc-dash-sliders-revised' );
				$show_all_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_state=RV';
			?>
			<table class="table">

				<thead>
				<tr>
					<th><?php echo \Joomla\CMS\Language\Text::_('FLEXI_TITLE'); ?></th>
					<th><?php echo \Joomla\CMS\Language\Text::_('FLEXI_MODIFIED'); ?></th>
					<th><?php echo \Joomla\CMS\Language\Text::_('FLEXI_NF_MODIFIER'); ?></th>
				</tr>
				</thead>

				<tbody>
				<?php
				$k = 0;
				$n = count($this->revised);

				for ($i = 0, $n; $i < $n; $i++)
				{
					$row          = $this->revised[$i];
					$assetName    = 'com_content.article.' . $row->id;
					$isAuthor     = $row->created_by && $row->created_by == $user->id;
					$row->canEdit = $user->authorise('core.edit', $assetName) || ($isAuthor && $user->authorise('core.edit.own', $assetName));
				?>
				<tr>
					<td>
					<?php
					echo \Joomla\CMS\HTML\HTMLHelper::_($hlpname . '.edit_link', $row, $i, $row->canEdit, $config = array(
						'ctrl'     => 'items',
						'view'     => 'item',
						'onclick'  => 'var url = this.getAttribute(\'data-href\'); var the_dialog = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, fc_edit_fcitem_modal_close, {title:\'' . \Joomla\CMS\Language\Text::_('FLEXI_EDIT', true) . '\', loadFunc: fc_edit_fcitem_modal_load}); return false;',
					));
					?>
					</td>
					<td><?php echo \Joomla\CMS\HTML\HTMLHelper::_('date',  $row->modified); ?></td>
					<td><?php echo $row->modifier; ?></td>
				</tr>
				<?php $k = 1 - $k; } ?>
				<tr>
					<td colspan="3">
						... &nbsp;
						<?php echo '<button type="button" class="'.$btn_class.'" onclick="window.open(\''.$show_all_link.'\')" >' . \Joomla\CMS\Language\Text::_('FLEXI_SHOW_ALL') . ' ( ' . \Joomla\CMS\Language\Text::_('FLEXI_ITEMS_MANAGER') . ' )</button><br/>'; ?>
					</td>
				</tr>
				</tbody>

			</table>
			<?php
				echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endSlide');
			endif; /* !isset($ssliders['revised']) */ ?>


			<?php if (!isset($ssliders['inprogress'])): ?>
			<?php
				$title = \Joomla\CMS\Language\Text::_( 'FLEXI_IN_PROGRESS_SLIDER' ).' - <span class="badge bg-info badge-info-2">'.$this->totalrows['inprogress'].'</span>';
				echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addSlide', 'fc-dash-sliders', $title, 'fc-dash-sliders-inprogress' );
				$show_all_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_state=IP';
			?>
			<table class="table">

				<thead>
				<tr>
					<th><?php echo \Joomla\CMS\Language\Text::_('FLEXI_TITLE'); ?></th>
					<th><?php echo \Joomla\CMS\Language\Text::_('FLEXI_CREATED'); ?></th>
					<th><?php echo \Joomla\CMS\Language\Text::_('FLEXI_AUTHOR'); ?></th>
				</tr>
				</thead>

				<tbody>
				<?php
				$k = 0;
				$n = count($this->inprogress);

				for ($i = 0, $n; $i < $n; $i++)
				{
					$row          = $this->inprogress[$i];
					$assetName    = 'com_content.article.' . $row->id;
					$isAuthor     = $row->created_by && $row->created_by == $user->id;
					$row->canEdit = $user->authorise('core.edit', $assetName) || ($isAuthor && $user->authorise('core.edit.own', $assetName));
			?>
				<tr>
					<td>
					<?php
					echo \Joomla\CMS\HTML\HTMLHelper::_($hlpname . '.edit_link', $row, $i, $row->canEdit, $config = array(
						'ctrl'     => 'items',
						'view'     => 'item',
						'onclick'  => 'var url = this.getAttribute(\'data-href\'); var the_dialog = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, fc_edit_fcitem_modal_close, {title:\'' . \Joomla\CMS\Language\Text::_('FLEXI_EDIT', true) . '\', loadFunc: fc_edit_fcitem_modal_load}); return false;',
					));
					?>
					</td>
					<td><?php echo \Joomla\CMS\HTML\HTMLHelper::_('date',  $row->created); ?></td>
					<td><?php echo $row->creator; ?></td>
				</tr>
				<?php $k = 1 - $k; } ?>
				<tr>
					<td colspan="3">
						... &nbsp;
						<?php echo '<button type="button" class="'.$btn_class.'" onclick="window.open(\''.$show_all_link.'\')" >' . \Joomla\CMS\Language\Text::_('FLEXI_SHOW_ALL') . ' ( ' . \Joomla\CMS\Language\Text::_('FLEXI_ITEMS_MANAGER') . ' )</button><br/>'; ?>
					</td>
				</tr>
				</tbody>


			</table>
			<?php
				echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endSlide');
			endif; /* !isset($ssliders['inprogress']) */ ?>


			<?php if (!isset($ssliders['draft'])): ?>
			<?php
				$title = \Joomla\CMS\Language\Text::_( 'FLEXI_DRAFT_SLIDER' ).' - <span class="badge bg-info badge-info-2">'.$this->totalrows['draft'].'</span>';
				echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.addSlide', 'fc-dash-sliders', $title, 'fc-dash-sliders-draft' );
				$show_all_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_state=OQ';
			?>
			<table class="table">

				<thead>
				<tr>
					<th><?php echo \Joomla\CMS\Language\Text::_('FLEXI_TITLE'); ?></th>
					<th><?php echo \Joomla\CMS\Language\Text::_('FLEXI_CREATED'); ?></th>
					<th><?php echo \Joomla\CMS\Language\Text::_('FLEXI_AUTHOR'); ?></th>
				</tr>
				</thead>

				<tbody>
			<?php
				$k = 0;
				$n = count($this->draft);

				for ($i = 0, $n; $i < $n; $i++)
				{
					$row          = $this->draft[$i];
					$assetName    = 'com_content.article.' . $row->id;
					$isAuthor     = $row->created_by && $row->created_by == $user->id;
					$row->canEdit = $user->authorise('core.edit', $assetName) || ($isAuthor && $user->authorise('core.edit.own', $assetName));
			?>
				<tr>
					<td>
					<?php
					echo \Joomla\CMS\HTML\HTMLHelper::_($hlpname . '.edit_link', $row, $i, $row->canEdit, $config = array(
						'ctrl'     => 'items',
						'view'     => 'item',
						'onclick'  => 'var url = this.getAttribute(\'data-href\'); var the_dialog = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, fc_edit_fcitem_modal_close, {title:\'' . \Joomla\CMS\Language\Text::_('FLEXI_EDIT', true) . '\', loadFunc: fc_edit_fcitem_modal_load}); return false;',
					));
					?>
					</td>
					<td><?php echo \Joomla\CMS\HTML\HTMLHelper::_('date',  $row->created); ?></td>
					<td><?php echo $row->creator; ?></td>
				</tr>
				<?php $k = 1 - $k; } ?>
				<tr>
					<td colspan="3">
						... &nbsp;
						<?php echo '
						<a href="javascript:;" role="button" class="' . $btn_class . '" onclick="window.open(\''.$show_all_link.'\'); return false;" >
							' . \Joomla\CMS\Language\Text::_('FLEXI_SHOW_ALL') . ' ( ' . \Joomla\CMS\Language\Text::_('FLEXI_ITEMS_MANAGER') . ' )
						</a><br/>';
						?>
					</td>
				</tr>
				</tbody>

			</table>
			<?php
				echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endSlide');
			endif; /* !isset($ssliders['draft']) */ ?>

			<?php
			echo \Joomla\CMS\HTML\HTMLHelper::_('bootstrap.endAccordion');

		endif; /* !$skip_sliders */ ?>
				</div>
			</section>
<?php
		$dash_sys['waiting'] = ob_get_clean();
		?>

		<div class="fc-dashboard-layout">
		<section class="fc-dashboard-cards">
			<div id="fc-dash-boardbtns" class="fc-dash-grid">
				<?php echo FlexicontentDashboard::render($dash_layout, $dash_registry, $dash_sys); ?>
			</div> <!-- END OF #fc-dash-boardbtns -->

			<?php if ($dash_ready && $dash_is_admin) : ?>
			<div class="fc-dash-toolbar" id="fc-dash-toolbar">
				<button type="button" class="btn fc-dash-customize-btn" id="fc-dash-customize">
					<span class="icon-cog" aria-hidden="true"></span> <?php echo \Joomla\CMS\Language\Text::_('FLEXI_DASH_CUSTOMIZE'); ?>
				</button>
			</div>
			<?php endif; ?>
		</section><!-- END OF .fc-dashboard-cards -->
		</div><!-- END OF .fc-dashboard-layout -->


	<!-- Common management form fields -->
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="" />
	<input type="hidden" name="view" value="" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="fcform" value="1" />
	<?php echo \Joomla\CMS\HTML\HTMLHelper::_( 'form.token' ); ?>

	<!-- fc_perf -->

	</div>  <!-- j-main-container -->
</div>  <!-- row / row-fluid-->

</form>
</div><!-- #flexicontent end -->
