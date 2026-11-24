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
$skip_content_fieldset = isset($sbtns['items']) && isset($sbtns['additem']) && isset($sbtns['cats']) && isset($sbtns['addcat']) && isset($sbtns['files']) && isset($sbtns['comments']);
$skip_types_fieldset   = isset($sbtns['types']) && isset($sbtns['addtype']) && isset($sbtns['fields']) && isset($sbtns['addfield']) && isset($sbtns['tags']) && isset($sbtns['addtag']);
// Reviews button: show only if reviews are enabled in the voting field and user can manage reviews
$showReviews = false;

if (!isset($sbtns['reviews']) && $this->perms->CanReviews && file_exists(JPATH_ADMINISTRATOR . '/components/com_flexicontent/views/reviews'))
{
	$rv_db    = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
	$rv_field = $rv_db->setQuery('SELECT * FROM #__flexicontent_fields WHERE field_type = ' . $rv_db->Quote('voting'))->loadObject();

	if ($rv_field)
	{
		$rv_item = (object) array('type_id' => 0);
		FlexicontentFields::loadFieldConfig($rv_field, $rv_item);
		$showReviews = (int) $rv_field->parameters->get('allow_reviews', 0) > 0;
	}
}

$skip_viewing_fieldset = isset($sbtns['templates']) && isset($sbtns['index']) && isset($sbtns['stats']) && !$showReviews;
$skip_users_fieldset   = isset($sbtns['users']) && isset($sbtns['adduser']) && isset($sbtns['groups']) && isset($sbtns['addgroup']);
$skip_expert_fieldset  = isset($sbtns['import']) && isset($sbtns['plgfields']) && isset($sbtns['plgsystem']) && isset($sbtns['plgflexicontent']);

$sectionTypes   = $this->perms->CanTypes || $this->perms->CanFields || $this->perms->CanTags || $this->perms->CanFiles;
$sectionViewing = $this->perms->CanTemplates || $this->perms->CanIndex || $this->perms->CanStats || $showReviews;
$sectionUsers   = $this->perms->CanAuthors || $this->perms->CanGroups;
$sectionExpert  = $this->perms->CanPlugins || $this->perms->CanImport;

$skip_types_fieldset   = $skip_types_fieldset   || !$sectionTypes;
$skip_viewing_fieldset = $skip_viewing_fieldset || !$sectionViewing;
$skip_users_fieldset   = $skip_users_fieldset   || !$sectionUsers;
$skip_expert_fieldset  = $skip_users_fieldset   || !$sectionExpert;

if (isset($sbtns['comments'])) $commentsShown = false;
else if (
	($this->params->get('comments')==1 && $this->perms->CanComments) ||  // Can administer JComments
	($this->params->get('comments')==3 && $user->authorise('core.manage', 'com_komento')) ||  // Can administer Komento
	(!$this->params->get('comments') && $this->params->get('comments_admin_link'))  // Custom comments extension
) $commentsShown = true;
else $commentsShown = false;

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
					FLEXIcontent <?php echo FLEXI_VERSION . ' ' . FLEXI_RELEASE; ?><br/> GNU/GPL licence, Copyright &copy; 2009-2022
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
			echo '<div class="fc-mssg fc-warning">';
			echo \Joomla\CMS\Language\Text::_( 'FLEXI_DO_POSTINSTALL' );
			echo '</div>';
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

		<div class="fc-dashboard-layout">
		<section class="fc-dashboard-cards">
		<div id="fc-dash-boardbtns">
		<?php
		// BOF -- SHOW DASHBOARD BUTTONS -- (POST installation is DONE)
		if ($this->dopostinstall && $config_saved) :
			/**
			 * Render a Joomla-style combined quickicon: main link on the left,
			 * a "+" add link on the right (Joomla quickicon-group markup).
			 */
			$render_combo_quickicon = function ($link, $icon, $label, $add_link, $add_label, $modal = false, $modal_width = 0)
			{
				$add_js_title = flexicontent_html::encodeHTML($add_label, 2);
				$add_onclick  = $modal
					? "var url = this.getAttribute('href'); fc_showDialog(url, 'fc_modal_popup_container', 0, ".(int) $modal_width.", 0, false, {'title': '".$add_js_title."'}); return false;"
					: '';
				?>
				<div class="fc-board-button fc-board-button-combo quick-icons">
					<ul class="quickicon-group list-unstyled d-flex w-100 mb-0">
						<li class="quickicon">
							<a href="<?php echo $link; ?>">
								<div class="quickicon-info">
									<div class="quickicon-icon"><div class="<?php echo $icon; ?>" aria-hidden="true"></div></div>
								</div>
								<div class="quickicon-name d-flex align-items-end"><?php echo $label; ?></div>
							</a>
						</li>
						<li class="quickicon-linkadd j-links-link d-flex">
							<a class="d-flex" href="<?php echo $add_link; ?>" title="<?php echo htmlspecialchars($add_label, ENT_COMPAT, 'UTF-8'); ?>"<?php if ($add_onclick) echo ' onclick="'.htmlspecialchars($add_onclick, ENT_COMPAT, 'UTF-8').'"'; ?>>
								<span class="icon-plus" aria-hidden="true"></span>
							</a>
						</li>
					</ul>
				</div>
				<?php
			};
		?>

		<?php if (empty($skip_content_fieldset)): ?>
		<fieldset class="fc-board-set card fc-dashboard-card">
			<h3 class="fc-board-header card-header"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_NAV_SD_CONTENT_EDITING' );?></h3>

			<div class="fc-board-set-inner card-body"><?php
			$show_items_btn    = !isset($sbtns['items']);
			$show_additem_btn  = !isset($sbtns['additem']);
			$can_add_any_item  = false;

			if ($show_additem_btn)
			{
				// Check if user can create in at least one published category
				require_once("components/com_flexicontent/models/item.php");

				$itemmodel = new FlexicontentModelItem();
				$can_add_any_item = $itemmodel->getItemAccess()->get('access-create');
			}

			if ($show_items_btn && $show_additem_btn && $can_add_any_item)
			{
				$render_combo_quickicon(
					'index.php?option='.$option.'&amp;view=items',
					'icon-file-alt',
					\Joomla\CMS\Language\Text::_('FLEXI_ITEMS'),
					'index.php?option='.$option.'&amp;view=types&amp;tmpl=component&amp;layout=typeslist&amp;action=new',
					\Joomla\CMS\Language\Text::_('FLEXI_NEW_ITEM'),
					true,
					1200
				);
			}
			else
			{
				if ($show_items_btn)
				{
					$link = 'index.php?option='.$option.'&amp;view=items';
					FlexicontentViewFlexicontent::quickiconButton( $link, '', 'icon-items', \Joomla\CMS\Language\Text::_( 'FLEXI_ITEMS' ) );
				}
				if ($show_additem_btn && $can_add_any_item)
				{
					$link = 'index.php?option='.$option.'&amp;view=types&amp;tmpl=component&amp;layout=typeslist&amp;action=new';
					FlexicontentViewFlexicontent::quickiconButton($link, '', 'icon-apply', \Joomla\CMS\Language\Text::_('FLEXI_NEW_ITEM' ), 1, 1, 1200, 0);
				}
			}
			/*if ($this->perms->CanArchives && !isset($sbtns['archives']))
			{
				$link = 'index.php?option='.$option.'&amp;view=archive';
				FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-archive.png', \Joomla\CMS\Language\Text::_( 'FLEXI_ARCHIVE' ) );
			}*/
			if ($this->perms->CanCats)
			{
				$show_cats_btn    = !isset($sbtns['cats']);
				$show_addcat_btn  = !isset($sbtns['addcat']);
				$can_create_cat   = $show_addcat_btn ? FlexicontentHelperPerm::getPermAny('core.create') : false;

				if ($show_cats_btn && $show_addcat_btn && $can_create_cat)
				{
					$render_combo_quickicon(
						'index.php?option='.$option.'&amp;view=categories',
						'icon-folder',
						\Joomla\CMS\Language\Text::_('FLEXI_CATEGORIES'),
						'index.php?option='.$option.'&amp;view=category',
						\Joomla\CMS\Language\Text::_('FLEXI_NEW_CATEGORY')
					);
				}
				else
				{
					if ($show_cats_btn)
					{
						$link = 'index.php?option='.$option.'&amp;view=categories';
						FlexicontentViewFlexicontent::quickiconButton( $link, '' ,'icon-folder', \Joomla\CMS\Language\Text::_( 'FLEXI_CATEGORIES' ) );
					}
					if ($show_addcat_btn && $can_create_cat)
					{
						$link = 'index.php?option='.$option.'&amp;view=category';
						FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-folder-plus', \Joomla\CMS\Language\Text::_( 'FLEXI_NEW_CATEGORY' ) );
					}
				}
			}
			// Files moved here from "Types & Fields", right after Categories
			if ($this->perms->CanFiles && !isset($sbtns['files']))
			{
				$link = 'index.php?option='.$option.'&amp;view=filemanager';
				FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-images' , \Joomla\CMS\Language\Text::_( 'FLEXI_FILEMANAGER' ) );
			}
			if (isset($sbtns['comments']))
			{
				// skip
			}
			elseif (
				($this->params->get('comments')==1 && $this->perms->CanComments) ||  // Can administer JComments
				($this->params->get('comments')==3 && $user->authorise('core.manage', 'com_komento')) ||  // Can administer Komento
				(!$this->params->get('comments') && $this->params->get('comments_admin_link'))  // Custom comments extension
			) {
				echo '<span class="fc-board-button_sep"></span>';
				switch((int) $this->params->get('comments'))
				{
					case 1:
						$link = 'index.php?option=com_jcomments&amp;task=view&amp;fog=com_flexicontent';
						$link_title = \Joomla\CMS\Language\Text::_('JComments');
						break;
					case 3:
						$link = 'index.php?option=com_komento';
						$link_title = \Joomla\CMS\Language\Text::_('Komento');
						break;
					default:
						$link = $this->params->get('comments_admin_link');
						$link_title = \Joomla\CMS\Language\Text::_('FLEXI_COMMENTS');
						break;
				}
				FlexicontentViewFlexicontent::quickiconButton( $link, '', 'icon-comment', $link_title, 1 );
			}
			elseif ($this->params->get('comments')==1 && !$this->perms->JComments_Installed)
			{
				echo '<span class="fc-board-button_sep"></span>';
				$link = '';
				FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-comment' , \Joomla\CMS\Language\Text::_( 'FLEXI_JCOMMENTS_MISSING' ), 1 );
			}
			?>
			</div>
		</fieldset>
		<?php endif; ?>


		<?php if (empty($skip_types_fieldset)): ?>
		<fieldset class="fc-board-set card fc-dashboard-card">
			<h3 class="fc-board-header card-header"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_NAV_SD_TYPES_N_FIELDS' );?></h3>

			<div class="fc-board-set-inner card-body"><?php
			$add_sep = false;
			if ($this->perms->CanTypes)
			{
				$show_types_btn   = !isset($sbtns['types']);
				$show_addtype_btn = !isset($sbtns['addtype']);

				if ($show_types_btn && $show_addtype_btn)
				{
					$render_combo_quickicon(
						'index.php?option='.$option.'&amp;view=types',
						'icon-briefcase',
						\Joomla\CMS\Language\Text::_('FLEXI_TYPES'),
						'index.php?option='.$option.'&amp;view=type',
						\Joomla\CMS\Language\Text::_('FLEXI_NEW_TYPE')
					);
					$add_sep = true;
				}
				else
				{
					if ($show_types_btn) {
						$link = 'index.php?option='.$option.'&amp;view=types';
						FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-briefcase', \Joomla\CMS\Language\Text::_( 'FLEXI_TYPES' ) );
						$add_sep = true;
					}
					if ($show_addtype_btn) {
						$link = 'index.php?option='.$option.'&amp;view=type';
						FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-file-add', \Joomla\CMS\Language\Text::_( 'FLEXI_NEW_TYPE' ) );
						$add_sep = true;
					}
				}
			}
			if ($this->perms->CanFields)
			{
				$show_fields_btn   = !isset($sbtns['fields']);
				$show_addfield_btn = !isset($sbtns['addfield']);

				if ($show_fields_btn && $show_addfield_btn)
				{
					$render_combo_quickicon(
						'index.php?option='.$option.'&amp;view=fields',
						'icon-signup',
						\Joomla\CMS\Language\Text::_('FLEXI_FIELDS'),
						'index.php?option='.$option.'&amp;view=field',
						\Joomla\CMS\Language\Text::_('FLEXI_NEW_FIELD')
					);
					$add_sep = true;
				}
				else
				{
					if ($show_fields_btn) {
						$link = 'index.php?option='.$option.'&amp;view=fields';
						FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-signup', \Joomla\CMS\Language\Text::_( 'FLEXI_FIELDS' ) );
						$add_sep = true;
					}
					if ($show_addfield_btn) {
						$link = 'index.php?option='.$option.'&amp;view=field';
						FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-file-add', \Joomla\CMS\Language\Text::_( 'FLEXI_NEW_FIELD' ) );
						$add_sep = true;
					}
				}
				$addTagsBtns  = $this->perms->CanTags && (!isset($sbtns['tags']) || !isset($sbtns['addtag']));
				if ($add_sep && $addTagsBtns)
					echo '<span class="fc-board-button_sep"></span>';
			}
			if ($this->perms->CanTags)
			{
				$show_tags_btn   = !isset($sbtns['tags']);
				$show_addtag_btn = !isset($sbtns['addtag']);

				if ($show_tags_btn && $show_addtag_btn)
				{
					$render_combo_quickicon(
						'index.php?option='.$option.'&amp;view=tags',
						'icon-tags',
						\Joomla\CMS\Language\Text::_('FLEXI_TAGS'),
						'index.php?option='.$option.'&amp;view=tag',
						\Joomla\CMS\Language\Text::_('FLEXI_NEW_TAG')
					);
				}
				else
				{
					if ($show_tags_btn) {
						$link = 'index.php?option='.$option.'&amp;view=tags';
						FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-tags', \Joomla\CMS\Language\Text::_( 'FLEXI_TAGS' ) );
					}
					if ($show_addtag_btn) {
						$link = 'index.php?option='.$option.'&amp;view=tag';
						FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-file-add', \Joomla\CMS\Language\Text::_( 'FLEXI_NEW_TAG' ) );
					}
				}
			}
			?>
			</div>
		</fieldset>
		<?php endif; ?>


		<?php if (empty($skip_viewing_fieldset)): ?>
		<fieldset class="fc-board-set card fc-dashboard-card">
			<h3 class="fc-board-header card-header"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_NAV_SD_CONTENT_VIEWING' );?></h3>

			<div class="fc-board-set-inner card-body"><?php
			$add_sep = false;
			if ($this->perms->CanTemplates && !isset($sbtns['templates']))
			{
				$link = 'index.php?option='.$option.'&amp;view=templates';
				FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-paint-brush', \Joomla\CMS\Language\Text::_( 'FLEXI_TEMPLATES' ) );
				$add_sep = true;
			}
			if ($this->perms->CanIndex && !isset($sbtns['index']))
			{
				$link = 'index.php?option='.$option.'&amp;view=search';
				FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-search', \Joomla\CMS\Language\Text::_( 'FLEXI_SEARCH_INDEXES' ) );
				$add_sep = true;
			}

			if ($this->perms->CanIndex)
			{
				$link = 'index.php?option='.$option.'&amp;view=search&amp;layout=logs&amp;tmpl=component';
				FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-book', \Joomla\CMS\Language\Text::_( 'FLEXI_NAV_SD_SEARCH_LOGS' ), $modal = 1 );
				$add_sep = true;
			}

			if ($this->perms->CanStats && !isset($sbtns['stats']))
			{
				if ($add_sep)
					echo '<span class="fc-board-button_sep"></span>';

				$link = 'index.php?option='.$option.'&amp;view=stats';
				FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-chart', \Joomla\CMS\Language\Text::_( 'FLEXI_STATISTICS' ) );
				$add_sep = true;
			}

			if ($showReviews)
			{
				if ($add_sep)
					echo '<span class="fc-board-button_sep"></span>';

				$link = 'index.php?option='.$option.'&amp;view=reviews';
				FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-comments-2', \Joomla\CMS\Language\Text::_( 'FLEXI_REVIEWS' ) );
			}
			?>
			</div>
		</fieldset>
		<?php endif; ?>


		<?php if (empty($skip_users_fieldset)): ?>
		<fieldset class="fc-board-set card fc-dashboard-card">
			<h3 class="fc-board-header card-header"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_NAV_SD_USERS_N_GROUPS' );?></h3>

			<div class="fc-board-set-inner card-body"><?php
			if ($this->perms->CanAuthors)
			{
				$show_users_btn    = !isset($sbtns['users']);
				$show_adduser_btn  = !isset($sbtns['adduser']);

				if ($show_users_btn && $show_adduser_btn)
				{
					$render_combo_quickicon(
						'index.php?option='.$option.'&amp;view=users',
						'icon-users',
						\Joomla\CMS\Language\Text::_('FLEXI_USERS'),
						'index.php?option='.$option.'&amp;task=users.add',
						\Joomla\CMS\Language\Text::_('FLEXI_ADD_USER')
					);
				}
				else
				{
					if ($show_users_btn) {
						$link = 'index.php?option='.$option.'&amp;view=users';
						FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-users', \Joomla\CMS\Language\Text::_( 'FLEXI_USERS' ) );
					}
					if ($show_adduser_btn) {
						$link = 'index.php?option='.$option.'&amp;task=users.add';
						FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-file-add', \Joomla\CMS\Language\Text::_( 'FLEXI_ADD_USER' ) );
					}
				}
			}
			if ($this->perms->CanGroups)
			{
				$show_groups_btn   = !isset($sbtns['groups']);
				$show_addgroup_btn = !isset($sbtns['addgroup']);

				if ($show_groups_btn && $show_addgroup_btn)
				{
					$render_combo_quickicon(
						'index.php?option='.$option.'&amp;view=groups',
						'icon-users-cog',
						\Joomla\CMS\Language\Text::_('FLEXI_GROUPS'),
						'index.php?option='.$option.'&amp;task=groups.add',
						\Joomla\CMS\Language\Text::_('FLEXI_ADD_GROUP')
					);
				}
				else
				{
					if ($show_groups_btn) {
						$link = 'index.php?option='.$option.'&amp;view=groups';
						FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-users-cog', \Joomla\CMS\Language\Text::_( 'FLEXI_GROUPS' ) );
					}
					if ($show_addgroup_btn) {
						$link = 'index.php?option='.$option.'&amp;task=groups.add';
						FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-file-add', \Joomla\CMS\Language\Text::_( 'FLEXI_ADD_GROUP' ) );
					}
				}
			}
			?>
			</div>
		</fieldset>
		<?php endif; ?>


		<?php if (empty($skip_expert_fieldset)): ?>
		<fieldset class="fc-board-set card fc-dashboard-card">
			<h3 class="fc-board-header card-header"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_NAV_SD_EXPERT_USAGE' );?></h3>

			<div class="fc-board-set-inner card-body"><?php
			$add_sep = false;
			if ($this->perms->CanImport && !isset($sbtns['import']))
			{
				$link = 'index.php?option='.$option.'&amp;view=import';
				FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-upload', \Joomla\CMS\Language\Text::_( 'FLEXI_IMPORT' ) );
				$add_sep = true;
			}
			if ($this->perms->CanPlugins)
			{
				if ($add_sep && (!isset($sbtns['plgfields']) || !isset($sbtns['plgsystem']) || !isset($sbtns['plgflexicontent'])))
					echo '<span class="fc-board-button_sep"></span>';

				if (!isset($sbtns['plgfields'])) {
					$link = 'index.php?option=com_plugins&amp;filter_folder=flexicontent_fields';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-power-cord', \Joomla\CMS\Language\Text::_( 'FLEXI_PLUGINS' ). ' - Fields', 1 );
				}
				if (!isset($sbtns['plgsystem'])) {
					$link = 'index.php?option=com_plugins&amp;filter_folder=system&amp;filter_search=flexi';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-cube', \Joomla\CMS\Language\Text::_( 'FLEXI_PLUGINS' ). ' - System', 1 );
				}
				if (!isset($sbtns['plgflexicontent'])) {
					$link = 'index.php?option=com_plugins&amp;filter_folder=flexicontent';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-puzzle', \Joomla\CMS\Language\Text::_( 'FLEXI_PLUGINS' ). ' - Flexicontent', 1 );
				}
			}
			if ( $this->perms->CanEdit )
			{
				//$link = 'index.php?option=com_content&amp;view=featured';
				//if (!isset($sbtns['featured'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-featured.png', \Joomla\CMS\Language\Text::_( 'FLEXI_FEATURED' ), 1 );
			}
			?>
			</div>
		</fieldset>
		<?php endif; ?>


		<?php if ($this->params->get('support_url')): ?>
		<fieldset class="fc-board-set card fc-dashboard-card">
			<h3 class="fc-board-header card-header"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_HELP' );?></h3>

			<div class="fc-board-set-inner card-body"><?php
			$link = $this->params->get('support_url');
			FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-help', \Joomla\CMS\Language\Text::_( 'FLEXI_SUPPORT' ), 1 );

			// Read installation file
			/*$manifest_path = JPATH_ADMINISTRATOR .DS. 'components' .DS. 'com_flexicontent' .DS. 'flexicontent.xml';
			$com_xml = \Joomla\CMS\Installer\Installer::parseXMLInstallFile( $manifest_path );
			if (!empty($com_xml['authorUrl']))
			{
				FlexicontentViewFlexicontent::quickiconButton( $com_xml['authorUrl'], 'icon-48-dashboard.png', \Joomla\CMS\Language\Text::_( 'FLEXI_ABOUT' ), 1 );
			}*/
			?>
			</div>
		</fieldset>
		<?php endif; ?>

		<?php
		// EOF -- SHOW DASHBOARD BUTTONS
		endif; ?>


		</div> <!-- END OF #fc-dash-boardbtns -->
		</section><!-- END OF .fc-dashboard-cards -->

		<aside class="fc-dashboard-updates">
			<h2 class="visually-hidden"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_DASHBOARD_SYSTEM_INFO' ); ?></h2>

			<section class="fc-board-set card fc-dashboard-card fc-dashboard-updatecheck">
				<h3 class="fc-board-header card-header"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_DASHBOARD_SYSTEM_INFO' ); ?></h3>
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


			<section class="fc-board-set card fc-dashboard-card fc-dashboard-waiting">
				<h3 class="fc-board-header card-header"><?php echo \Joomla\CMS\Language\Text::_( 'FLEXI_DASHBOARD_WAITING_ACTION' ); ?></h3>
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
						'onclick'  => 'var url = this.getAttribute(\'data-href\'); var the_dialog = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, fc_edit_fcitem_modal_close, {title:\'' . \Joomla\CMS\Language\Text::_('FLEXI_EDIT', true) . '\', loadFunc: fc_edit_fcitem_modal_load}); return false;" ',
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
						'onclick'  => 'var url = this.getAttribute(\'data-href\'); var the_dialog = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, fc_edit_fcitem_modal_close, {title:\'' . \Joomla\CMS\Language\Text::_('FLEXI_EDIT', true) . '\', loadFunc: fc_edit_fcitem_modal_load}); return false;" ',
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
						'onclick'  => 'var url = this.getAttribute(\'data-href\'); var the_dialog = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, fc_edit_fcitem_modal_close, {title:\'' . \Joomla\CMS\Language\Text::_('FLEXI_EDIT', true) . '\', loadFunc: fc_edit_fcitem_modal_load}); return false;" ',
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
						'onclick'  => 'var url = this.getAttribute(\'data-href\'); var the_dialog = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, fc_edit_fcitem_modal_close, {title:\'' . \Joomla\CMS\Language\Text::_('FLEXI_EDIT', true) . '\', loadFunc: fc_edit_fcitem_modal_load}); return false;" ',
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

		</aside><!-- END OF .fc-dashboard-updates -->
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
