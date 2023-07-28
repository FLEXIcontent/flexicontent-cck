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
JHtml::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_flexicontent/helpers/html');

$app      = JFactory::getApplication();
$option   = $app->input->get('option', '', 'CMD');
$user     = JFactory::getUser();
$template = $app->getTemplate();
$session  = JFactory::getSession();
$hlpname  = 'fcbase';

$btn_class = 'btn';
$tooltip_class = 'hasTooltip';
$edit_item_txt = JText::_( 'FLEXI_EDIT_ITEM' );

// hide dashboard buttons
$dashboard_buttons_hide = $this->params->get('dashboard_buttons_hide', array());
$dashboard_buttons_hide = FLEXIUtilities::paramToArray($dashboard_buttons_hide);

$sbtns = array_flip($dashboard_buttons_hide);
$skip_content_fieldset = isset($sbtns['items']) && isset($sbtns['additem']) && isset($sbtns['cats']) && isset($sbtns['addcat']) && isset($sbtns['comments']);
$skip_types_fieldset   = isset($sbtns['types']) && isset($sbtns['addtype']) && isset($sbtns['fields']) && isset($sbtns['addfield']) && isset($sbtns['tags']) && isset($sbtns['addtag']) && isset($sbtns['files']);
$skip_viewing_fieldset = isset($sbtns['templates']) && isset($sbtns['index']) && isset($sbtns['stats']);
$skip_users_fieldset   = isset($sbtns['users']) && isset($sbtns['adduser']) && isset($sbtns['groups']) && isset($sbtns['addgroup']);
$skip_expert_fieldset  = isset($sbtns['import']) && isset($sbtns['plgfields']) && isset($sbtns['plgsystem']) && isset($sbtns['plgflexicontent']);

$sectionTypes   = $this->perms->CanTypes || $this->perms->CanFields || $this->perms->CanTags || $this->perms->CanFiles;
$sectionViewing = $this->perms->CanTemplates || $this->perms->CanIndex || $this->perms->CanStats;
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
$onclick_modal_edit = $modal_item_edit ? 'onclick="var url = jQuery(this).attr(\'href\'); fc_showDialog(url, \'fc_modal_popup_container\'); return false;"' : '';
$disable_fc_logo = $this->params->get('dashboard_disable_fc_logo', 0);
$hide_fc_license_credits = $this->params->get('dashboard_hide_fc_license_credits', 1); /* hide inside sliders */

// Get/Check PHP requiremenets
$php_lims = flexicontent_html::checkPHPLimits();

$ssliders = array_flip($dashboard_sliders_disable);
$skip_sliders = isset($sbtns['pending']) && isset($sbtns['revised']) && isset($sbtns['inprogress']) && isset($sbtns['draft']) && isset($sbtns['version']);
$skip_sliders = $skip_sliders && $this->dopostinstall && $this->allplgpublish && !$hide_fc_license_credits && !isset($php_lims['warning']);

// ensures the PHP version is correct
if (version_compare(PHP_VERSION, FLEXI_PHP_NEEDED, '<'))
{
	echo '<div class="fc-mssg fc-error">';
	echo JText::sprintf( 'FLEXI_UPGRADE_PHP_VERSION_GE', FLEXI_PHP_NEEDED) . '<br/>';
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
			$app->enqueueMessage(JText::sprintf( 'PHP version >= %s is recommended', FLEXI_PHP_RECOMMENDED), 'warning');
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
				$mssg .= JText::sprintf(
					'<br/>(you may have to contact your web hosting company for setting these for you)<br/>
					For more information on changing these limitations, please see this article: %s',
					'<a href="http://www.flexicontent.org/documentation/faq/78-installation-upgrade/591">PHP/DB Requirements</a>'
				);
				JFactory::getApplication()->enqueueMessage($mssg, 'warning', '');
			}
		}

		if ( isset($php_lims['warning']) )
			$_title .= ' - <span class="badge badge-important">Warning</span>';
		else if ( isset($php_lims['notice']) )
			$_title .= ' - <span class="badge bg-warning badge-warning">Notice</span>';
		else
			$_title .= ' - <span class="badge bg-success badge-success">OK</span>';
		?>

		<div id="fc-dash-boardbtns">
		<?php
		if (!$this->dopostinstall)
		{
			echo '<div class="fc-mssg fc-warning">';
			echo JText::_( 'FLEXI_DO_POSTINSTALL' );
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

				echo JText::sprintf( 'FLEXI_CONFIGURATION_NOT_SAVED', $conf_link.JText::_("FLEXI_CONFIG").'</a>' ) . '<br/>';
			}
			else if (!$this->existcat)	echo JText::_( 'FLEXI_NO_CATEGORIES_CREATED' );
			else if (!$this->existmenu)	echo JText::_( 'FLEXI_NO_MENU_CREATED' );
			echo '</div>';
		}
		?>


		<?php
		// BOF -- SHOW DASHBOARD BUTTONS -- (POST installation is DONE)
		if ($this->dopostinstall && $config_saved) :
		?>

		<?php if (empty($skip_content_fieldset)): ?>
		<fieldset class="fc-board-set">
			<h3 class="fc-board-header"><?php echo JText::_( 'FLEXI_NAV_SD_CONTENT_EDITING' );?></h3>

			<div class="fc-board-set-inner"><?php
			if (!isset($sbtns['items']))
			{
				$link = 'index.php?option='.$option.'&amp;view=items';
				FlexicontentViewFlexicontent::quickiconButton( $link, '', 'icon-items', JText::_( 'FLEXI_ITEMS' ) );
			}
			if (!isset($sbtns['additem']))
			{
				// Check if user can create in at least one published category
				require_once("components/com_flexicontent/models/item.php");

				$itemmodel = new FlexicontentModelItem();
				$CanAddAny = $itemmodel->getItemAccess()->get('access-create');

				if ($CanAddAny)
				{
					$link = 'index.php?option='.$option.'&amp;view=types&amp;tmpl=component&amp;layout=typeslist&amp;action=new';
					FlexicontentViewFlexicontent::quickiconButton($link, '', 'icon-apply', JText::_('FLEXI_NEW_ITEM' ), 1, 1, 1200, 0);
				}
			}
			/*if ($this->perms->CanArchives && !isset($sbtns['archives']))
			{
				$link = 'index.php?option='.$option.'&amp;view=archive';
				FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-archive.png', JText::_( 'FLEXI_ARCHIVE' ) );
			}*/
			if ($this->perms->CanCats)
			{
				if (!isset($sbtns['cats']))
				{
					$link = 'index.php?option='.$option.'&amp;view=categories';
					FlexicontentViewFlexicontent::quickiconButton( $link, '' ,'icon-folder', JText::_( 'FLEXI_CATEGORIES' ) );
				}
				if (!isset($sbtns['addcat']))
				{
					$canCreateAny = FlexicontentHelperPerm::getPermAny('core.create');
					if ($canCreateAny)
					{
						$link = 'index.php?option='.$option.'&amp;view=category';
						FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-folder-plus', JText::_( 'FLEXI_NEW_CATEGORY' ) );
					}
				}
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
						$link_title = JText::_('JComments');
						break;
					case 3:
						$link = 'index.php?option=com_komento';
						$link_title = JText::_('Komento');
						break;
					default:
						$link = $this->params->get('comments_admin_link');
						$link_title = JText::_('FLEXI_COMMENTS');
						break;
				}
				FlexicontentViewFlexicontent::quickiconButton( $link, '', 'icon-comment', $link_title, 1 );
			}
			elseif ($this->params->get('comments')==1 && !$this->perms->JComments_Installed)
			{
				echo '<span class="fc-board-button_sep"></span>';
				$link = '';
				FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-comment' , JText::_( 'FLEXI_JCOMMENTS_MISSING' ), 1 );
			}
			?>
			</div>
		</fieldset>
		<?php endif; ?>


		<?php if (empty($skip_types_fieldset)): ?>
		<fieldset class="fc-board-set">
			<h3 class="fc-board-header"><?php echo JText::_( 'FLEXI_NAV_SD_TYPES_N_FIELDS' );?></h3>

			<div class="fc-board-set-inner"><?php
			$add_sep = false;
			if ($this->perms->CanTypes)
			{
				if (!isset($sbtns['types'])) {
					$link = 'index.php?option='.$option.'&amp;view=types';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-briefcase', JText::_( 'FLEXI_TYPES' ) );
					$add_sep = true;
				}
				if (!isset($sbtns['addtype'])) {
					$link = 'index.php?option='.$option.'&amp;view=type';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-file-add', JText::_( 'FLEXI_NEW_TYPE' ) );
					$add_sep = true;
				}
			}
			if ($this->perms->CanFields)
			{
				if (!isset($sbtns['fields'])) {
					$link = 'index.php?option='.$option.'&amp;view=fields';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-signup', JText::_( 'FLEXI_FIELDS' ) );
					$add_sep = true;
				}
				if (!isset($sbtns['addfield'])) {
					$link = 'index.php?option='.$option.'&amp;view=field';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-file-add', JText::_( 'FLEXI_NEW_FIELD' ) );
					$add_sep = true;
				}
				$addTagsBtns  = $this->perms->CanTags && (!isset($sbtns['tags']) || !isset($sbtns['addtag']));
				$addFilesBtns = $this->perms->CanFiles && !isset($sbtns['files']);
				if ($add_sep && ($addTagsBtns || $addFilesBtns))
					echo '<span class="fc-board-button_sep"></span>';
			}
			if ($this->perms->CanTags)
			{
				if (!isset($sbtns['tags'])) {
					$link = 'index.php?option='.$option.'&amp;view=tags';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-tags', JText::_( 'FLEXI_TAGS' ) );
				}
				if (!isset($sbtns['addtag'])) {
					$link = 'index.php?option='.$option.'&amp;view=tag';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-file-add', JText::_( 'FLEXI_NEW_TAG' ) );
				}
			}
			if ($this->perms->CanFiles && !isset($sbtns['files']))
			{
				$link = 'index.php?option='.$option.'&amp;view=filemanager';
				FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-images' , JText::_( 'FLEXI_FILEMANAGER' ) );
			}
			?>
			</div>
		</fieldset>
		<?php endif; ?>


		<?php if (empty($skip_viewing_fieldset)): ?>
		<fieldset class="fc-board-set">
			<h3 class="fc-board-header"><?php echo JText::_( 'FLEXI_NAV_SD_CONTENT_VIEWING' );?></h3>

			<div class="fc-board-set-inner"><?php
			$add_sep = false;
			if ($this->perms->CanTemplates && !isset($sbtns['templates']))
			{
				$link = 'index.php?option='.$option.'&amp;view=templates';
				FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-eye', JText::_( 'FLEXI_TEMPLATES' ) );
				$add_sep = true;
			}
			if ($this->perms->CanIndex && !isset($sbtns['index']))
			{
				$link = 'index.php?option='.$option.'&amp;view=search';
				FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-search', JText::_( 'FLEXI_SEARCH_INDEXES' ) );
				$add_sep = true;
			}

			$CanSeeSearchLogs = JFactory::getUser()->authorise('core.manage', 'com_search');

			if ($CanSeeSearchLogs)
			{
				$params = JComponentHelper::getParams('com_search');
				$enable_log_searches = $params->get('enabled');
				if ($enable_log_searches)
				{
					$link = 'index.php?option=com_search&tmpl=component';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-book', JText::_( 'FLEXI_NAV_SD_SEARCH_LOGS' ), $modal = 1 );
					$add_sep = true;
				}
				else
				{
					$link = 'index.php?option=com_config&view=component&component=com_search&path=';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-book', JText::_( 'FLEXI_NAV_SD_SEARCH_LOGS' ), $modal = 1, $close_function = 'function(){window.location.reload(false)}' );
					$add_sep = true;
				}
			}

			if ($this->perms->CanStats && !isset($sbtns['stats']))
			{
				if ($add_sep)
					echo '<span class="fc-board-button_sep"></span>';

				$link = 'index.php?option='.$option.'&amp;view=stats';
				FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-chart', JText::_( 'FLEXI_STATISTICS' ) );
			}
			?>
			</div>
		</fieldset>
		<?php endif; ?>


		<?php if (empty($skip_users_fieldset)): ?>
		<fieldset class="fc-board-set">
			<h3 class="fc-board-header"><?php echo JText::_( 'FLEXI_NAV_SD_USERS_N_GROUPS' );?></h3>

			<div class="fc-board-set-inner"><?php
			if ($this->perms->CanAuthors)
			{
				if (!isset($sbtns['users'])) {
					$link = 'index.php?option='.$option.'&amp;view=users';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-users', JText::_( 'FLEXI_USERS' ) );
				}
				if (!isset($sbtns['adduser'])) {
					$link = 'index.php?option='.$option.'&amp;task=users.add';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-file-add', JText::_( 'FLEXI_ADD_USER' ) );
				}
			}
			if ($this->perms->CanGroups)
			{
				if (!isset($sbtns['groups'])) {
					$link = 'index.php?option='.$option.'&amp;view=groups';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-users', JText::_( 'FLEXI_GROUPS' ) );
				}
				if (!isset($sbtns['addgroup'])) {
					$link = 'index.php?option='.$option.'&amp;task=groups.add';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-file-add', JText::_( 'FLEXI_ADD_GROUP' ) );
				}
			}
			?>
			</div>
		</fieldset>
		<?php endif; ?>


		<?php if (empty($skip_expert_fieldset)): ?>
		<fieldset class="fc-board-set">
			<h3 class="fc-board-header"><?php echo JText::_( 'FLEXI_NAV_SD_EXPERT_USAGE' );?></h3>

			<div class="fc-board-set-inner"><?php
			$add_sep = false;
			if ($this->perms->CanImport && !isset($sbtns['import']))
			{
				$link = 'index.php?option='.$option.'&amp;view=import';
				FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-upload', JText::_( 'FLEXI_IMPORT' ) );
				$add_sep = true;
			}
			if ($this->perms->CanPlugins)
			{
				if ($add_sep && (!isset($sbtns['plgfields']) || !isset($sbtns['plgsystem']) || !isset($sbtns['plgflexicontent'])))
					echo '<span class="fc-board-button_sep"></span>';

				if (!isset($sbtns['plgfields'])) {
					$link = 'index.php?option=com_plugins&amp;filter_folder=flexicontent_fields';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-power-cord', JText::_( 'FLEXI_PLUGINS' ). ' - Fields', 1 );
				}
				if (!isset($sbtns['plgsystem'])) {
					$link = 'index.php?option=com_plugins&amp;filter_folder=system&amp;filter_search=flexi';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-cube', JText::_( 'FLEXI_PLUGINS' ). ' - System', 1 );
				}
				if (!isset($sbtns['plgflexicontent'])) {
					$link = 'index.php?option=com_plugins&amp;filter_folder=flexicontent';
					FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-puzzle', JText::_( 'FLEXI_PLUGINS' ). ' - Flexicontent', 1 );
				}
			}
			if ( $this->perms->CanEdit )
			{
				//$link = 'index.php?option=com_content&amp;view=featured';
				//if (!isset($sbtns['featured'])) FlexicontentViewFlexicontent::quickiconButton( $link, 'icon-48-featured.png', JText::_( 'FLEXI_FEATURED' ), 1 );
			}
			?>
			</div>
		</fieldset>
		<?php endif; ?>


		<?php if ($this->params->get('support_url')): ?>
		<fieldset class="fc-board-set">
			<h3 class="fc-board-header"><?php echo JText::_( 'FLEXI_HELP' );?></h3>

			<div class="fc-board-set-inner"><?php
			$link = $this->params->get('support_url');
			FlexicontentViewFlexicontent::quickiconButton( $link, '','icon-help', JText::_( 'FLEXI_SUPPORT' ), 1 );

			// Read installation file
			/*$manifest_path = JPATH_ADMINISTRATOR .DS. 'components' .DS. 'com_flexicontent' .DS. 'flexicontent.xml';
			$com_xml = JInstaller::parseXMLInstallFile( $manifest_path );
			if (!empty($com_xml['authorUrl']))
			{
				FlexicontentViewFlexicontent::quickiconButton( $com_xml['authorUrl'], 'icon-48-dashboard.png', JText::_( 'FLEXI_ABOUT' ), 1 );
			}*/
			?>
			</div>
		</fieldset>
		<?php endif; ?>

		<?php
		// EOF -- SHOW DASHBOARD BUTTONS
		endif; ?>


		<?php
		if ( $this->params->get('show_updatecheck', 1) && $this->perms->CanConfig )
		{
			$this->document->addScriptDeclaration("
			jQuery(document).ready(function () {
				if(jQuery.trim(jQuery('#displayfversion').html())=='') {
					jQuery('#displayfversion').html('<p><img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" style=\"vertical-align: middle;\"><\/p>');
					jQuery.ajax({
						url: 'index.php?option=com_flexicontent&task=flexicontent.fcversioncompare&format=raw&". JSession::getFormToken() ."=1',
						success: function(str) {
							jQuery('#displayfversion').html(str);
							jQuery('#displayfversion').parent().css('height', 'auto');
						}
					});
				}
			});
			");
			echo '
			<fieldset class="fc-board-set">
				<h3 class="fc-board-header">'.JText::_( 'FLEXI_UPDATE_CHECK' ).'</h3>
				<div class="fc-board-set-inner">
					<div id="displayfversion" style="float: left;"></div>
				</div>
			</fieldset>
			';
		}
		?>

		</div> <!-- END OF #fc-dash-boardbtns -->



		<?php
		if (!$this->dopostinstall || !$this->allplgpublish) :

			// Make sure POST-INSTALLATION Task slider is open
			echo JHtml::_('bootstrap.startAccordion', 'fc-dash-sliders', array('active' => 'fc-dash-sliders-postinstall'));

			$title = JText::_( 'FLEXI_POST_INSTALL' );
			echo JHtml::_('bootstrap.addSlide', 'fc-dash-sliders', $title, 'fc-dash-sliders-postinstall' );
			echo $this->loadTemplate('postinstall');
			echo JHtml::_('bootstrap.endSlide');

			echo JHtml::_('bootstrap.endAccordion');


		elseif (!$skip_sliders && $config_saved) :

			echo JHtml::_('bootstrap.startAccordion', 'fc-dash-sliders', array());

			ob_start(); ?>

			<?php
				echo JHtml::_('bootstrap.addSlide', 'fc-dash-sliders', $_title, 'fc-dash-sliders-requirements' );
				echo '<div class="fc-mssg fc-note" style="margin: 24px; display: inline-block;">';
				echo '<b>PHP/DB requirements</b><br/>';
				foreach($php_lims as $type => $html) {
					echo implode('<br/>', $html);
				}
				echo JText::sprintf(
					'<br/>(you may have to contact your web hosting company for setting these for you)<br/>
					For more information on changing these limitations, please see this article: %s',
					'<a href="http://www.flexicontent.org/documentation/faq/78-installation-upgrade/591">PHP/DB Requirements</a>'
				);
				echo '</div>';
				echo JHtml::_('bootstrap.endSlide');
			?>

			<?php $fc_requirements = ob_get_clean();

			if ( isset($php_lims['warning']) ) : // Place requirements at top slider if they are failing
				echo $fc_requirements;
			endif; ?>


			<?php if (!isset($ssliders['pending'])):
				$title = JText::_( 'FLEXI_PENDING_SLIDER' ).' - <span class="badge bg-warning badge-warning">'.$this->totalrows['pending'].'</span>';
				echo JHtml::_('bootstrap.addSlide', 'fc-dash-sliders', $title, 'fc-dash-sliders-pending' );
				$show_all_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_state=PE';
			?>
			<table class="table">

				<thead>
				<tr>
					<th><?php echo JText::_('FLEXI_TITLE'); ?></th>
					<th><?php echo JText::_('FLEXI_CREATED'); ?></th>
					<th><?php echo JText::_('FLEXI_AUTHOR'); ?></th>
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
					echo JHtml::_($hlpname . '.edit_link', $row, $i, $row->canEdit, $config = array(
						'ctrl'     => 'items',
						'view'     => 'item',
						'onclick'  => 'var url = jQuery(this).attr(\'data-href\'); var the_dialog = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, fc_edit_fcitem_modal_close, {title:\'' . JText::_('FLEXI_EDIT', true) . '\', loadFunc: fc_edit_fcitem_modal_load}); return false;" ',
					));
					?>
					</td>
					<td><?php echo JHtml::_('date',  $row->created); ?></td>
					<td><?php echo $row->creator; ?></td>
				</tr>
				<?php $k = 1 - $k; } ?>
				<tr>
					<td colspan="3">
						... &nbsp;
						<?php echo '<span class="'.$btn_class.'" onclick="window.open(\''.$show_all_link.'\')" >' . JText::_('FLEXI_SHOW_ALL') . ' ( ' . JText::_('FLEXI_ITEMS_MANAGER') . ' )</span><br/>'; ?>
					</td>
				</tr>
				</tbody>

			</table>
			<?php
				echo JHtml::_('bootstrap.endSlide');
			endif; /* !isset($ssliders['pending']) */ ?>


			<?php if (!isset($ssliders['revised'])):
				$title = JText::_( 'FLEXI_REVISED_VER_SLIDER' ).' - <span class="badge bg-warning badge-warning">'.$this->totalrows['revised'].'</span>';
				echo JHtml::_('bootstrap.addSlide', 'fc-dash-sliders', $title, 'fc-dash-sliders-revised' );
				$show_all_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_state=RV';
			?>
			<table class="table">

				<thead>
				<tr>
					<th><?php echo JText::_('FLEXI_TITLE'); ?></th>
					<th><?php echo JText::_('FLEXI_MODIFIED'); ?></th>
					<th><?php echo JText::_('FLEXI_NF_MODIFIER'); ?></th>
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
					echo JHtml::_($hlpname . '.edit_link', $row, $i, $row->canEdit, $config = array(
						'ctrl'     => 'items',
						'view'     => 'item',
						'onclick'  => 'var url = jQuery(this).attr(\'data-href\'); var the_dialog = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, fc_edit_fcitem_modal_close, {title:\'' . JText::_('FLEXI_EDIT', true) . '\', loadFunc: fc_edit_fcitem_modal_load}); return false;" ',
					));
					?>
					</td>
					<td><?php echo JHtml::_('date',  $row->modified); ?></td>
					<td><?php echo $row->modifier; ?></td>
				</tr>
				<?php $k = 1 - $k; } ?>
				<tr>
					<td colspan="3">
						... &nbsp;
						<?php echo '<span class="'.$btn_class.'" onclick="window.open(\''.$show_all_link.'\')" >' . JText::_('FLEXI_SHOW_ALL') . ' ( ' . JText::_('FLEXI_ITEMS_MANAGER') . ' )</span><br/>'; ?>
					</td>
				</tr>
				</tbody>

			</table>
			<?php
				echo JHtml::_('bootstrap.endSlide');
			endif; /* !isset($ssliders['revised']) */ ?>


			<?php if (!isset($ssliders['inprogress'])): ?>
			<?php
				$title = JText::_( 'FLEXI_IN_PROGRESS_SLIDER' ).' - <span class="badge bg-info badge-info-2">'.$this->totalrows['inprogress'].'</span>';
				echo JHtml::_('bootstrap.addSlide', 'fc-dash-sliders', $title, 'fc-dash-sliders-inprogress' );
				$show_all_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_state=IP';
			?>
			<table class="table">

				<thead>
				<tr>
					<th><?php echo JText::_('FLEXI_TITLE'); ?></th>
					<th><?php echo JText::_('FLEXI_CREATED'); ?></th>
					<th><?php echo JText::_('FLEXI_AUTHOR'); ?></th>
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
					echo JHtml::_($hlpname . '.edit_link', $row, $i, $row->canEdit, $config = array(
						'ctrl'     => 'items',
						'view'     => 'item',
						'onclick'  => 'var url = jQuery(this).attr(\'data-href\'); var the_dialog = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, fc_edit_fcitem_modal_close, {title:\'' . JText::_('FLEXI_EDIT', true) . '\', loadFunc: fc_edit_fcitem_modal_load}); return false;" ',
					));
					?>
					</td>
					<td><?php echo JHtml::_('date',  $row->created); ?></td>
					<td><?php echo $row->creator; ?></td>
				</tr>
				<?php $k = 1 - $k; } ?>
				<tr>
					<td colspan="3">
						... &nbsp;
						<?php echo '<span class="'.$btn_class.'" onclick="window.open(\''.$show_all_link.'\')" >' . JText::_('FLEXI_SHOW_ALL') . ' ( ' . JText::_('FLEXI_ITEMS_MANAGER') . ' )</span><br/>'; ?>
					</td>
				</tr>
				</tbody>


			</table>
			<?php
				echo JHtml::_('bootstrap.endSlide');
			endif; /* !isset($ssliders['inprogress']) */ ?>


			<?php if (!isset($ssliders['draft'])): ?>
			<?php
				$title = JText::_( 'FLEXI_DRAFT_SLIDER' ).' - <span class="badge bg-info badge-info-2">'.$this->totalrows['draft'].'</span>';
				echo JHtml::_('bootstrap.addSlide', 'fc-dash-sliders', $title, 'fc-dash-sliders-draft' );
				$show_all_link = 'index.php?option=com_flexicontent&amp;view=items&amp;filter_state=OQ';
			?>
			<table class="table">

				<thead>
				<tr>
					<th><?php echo JText::_('FLEXI_TITLE'); ?></th>
					<th><?php echo JText::_('FLEXI_CREATED'); ?></th>
					<th><?php echo JText::_('FLEXI_AUTHOR'); ?></th>
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
					echo JHtml::_($hlpname . '.edit_link', $row, $i, $row->canEdit, $config = array(
						'ctrl'     => 'items',
						'view'     => 'item',
						'onclick'  => 'var url = jQuery(this).attr(\'data-href\'); var the_dialog = fc_showDialog(url, \'fc_modal_popup_container\', 0, 0, 0, fc_edit_fcitem_modal_close, {title:\'' . JText::_('FLEXI_EDIT', true) . '\', loadFunc: fc_edit_fcitem_modal_load}); return false;" ',
					));
					?>
					</td>
					<td><?php echo JHtml::_('date',  $row->created); ?></td>
					<td><?php echo $row->creator; ?></td>
				</tr>
				<?php $k = 1 - $k; } ?>
				<tr>
					<td colspan="3">
						... &nbsp;
						<?php echo '
						<a href="javascript:;" role="button" class="' . $btn_class . '" onclick="window.open(\''.$show_all_link.'\'); return false;" >
							' . JText::_('FLEXI_SHOW_ALL') . ' ( ' . JText::_('FLEXI_ITEMS_MANAGER') . ' )
						</a><br/>';
						?>
					</td>
				</tr>
				</tbody>

			</table>
			<?php
				echo JHtml::_('bootstrap.endSlide');
			endif; /* !isset($ssliders['draft']) */ ?>


			<?php if (!isset($ssliders['version'])): ?>
			<?php
			if ( !$this->params->get('show_updatecheck', 1) || !$this->perms->CanConfig )
			{
				$this->document->addScriptDeclaration("
				jQuery(document).ready(function () {
					jQuery('#updatecomponent').click(function(e){
						if(jQuery.trim(jQuery('#displayfversion').html())=='') {
							jQuery('#displayfversion').html('<p><img src=\"components/com_flexicontent/assets/images/ajax-loader.gif\" style=\"vertical-align: middle;\"><\/p>');
							jQuery.ajax({
								url: 'index.php?option=com_flexicontent&task=flexicontent.fcversioncompare&format=raw&". JSession::getFormToken() ."=1',
								success: function(str) {
									jQuery('#displayfversion').html(str);
									jQuery('#displayfversion').parent().css('height', 'auto');
								}
							});
						}
					});
				});
				");
				echo JHtml::_('bootstrap.addSlide', 'fc-dash-sliders', JText::_( 'FLEXI_VERSION_CHECKING' ), 'fc-dash-sliders-updatecomponent' );
				echo "<div id=\"displayfversion\" style='min-height:20px;'></div>";
				echo JHtml::_('bootstrap.endSlide');
			}
			endif; /* !isset($ssliders['version']) */ ?>


			<?php ob_start(); ?>
			<div id="fc-dash-credits">
			<?php echo !$hide_fc_license_credits ? '<fieldset class="fc-board-set"><h3 class="fc-board-header">'.JText::_( 'About FLEXIcontent' ).'</h3>' : ''; ?>
				<div class="fc-board-set-inner">
				<?php
					$logo_style = ';';
					if (!$disable_fc_logo)
					{
						echo JHtml::image('administrator/components/com_flexicontent/assets/images/logo.png', 'FLEXIcontent', ' id="fc-dash-logo" ');
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
							<span class="label text-white bg-info label-info <?php echo $tooltip_class;?>" title="Core Developer">Marvelic Engine</span><br/><br/>
							<a class="<?php echo $btn_class.' btn-small '.$tooltip_class;?>" style="" href="http://www.marvelic.co.th" target="_blank" title="<?php echo flexicontent_html::getToolTip("Marvelic Engine", "Marvelic Engine is a Joomla consultancy based in Bangkok, Thailand. Support services include consulting, Joomla implementation, training, and custom extensions development.", 0, 1); ?>">
								marvelic.co.th
							</a>
						</span>

						<span class="fc-mssg-inline fc-nobgimage fc-noborder nowrap_box">
							<span class="label <?php echo $tooltip_class;?>" title="Core Developer">Suriya Kaewmungmuang</span>
						</span>

						<span class="fc-mssg-inline fc-nobgimage fc-noborder nowrap_box">
							<span class="label <?php echo $tooltip_class;?>" title="Core Developer">Yannick Berges</span><br/><br/>
							<a class="<?php echo $btn_class.' btn-small '.$tooltip_class;?>" style="" href="http://com3elles.com/" target="_blank" title="<?php echo flexicontent_html::getToolTip("Com'3Elles", "Com'3Elles, agence de communication, conseil et formations", 0, 1); ?>">
								com3elles.com
							</a>
						</span>

						<span class="fc-mssg-inline fc-nobgimage fc-noborder nowrap_box">
							<span class="label <?php echo $tooltip_class;?>" title="Developer / Contributor">Ruben Reyes</span><br/><br/>
							<a class="<?php echo $btn_class.' btn-small '.$tooltip_class;?>" style="" href="http://www.lyquix.com" target="_blank" title="<?php echo flexicontent_html::getToolTip("Lyquix", "Lyquix - Philadelphia Marketing, Advertising, Web Design and Development Agency", 0, 1); ?>">
								lyquix.com
							</a>
						</span>
					</span>

				</div>
			<?php echo !$hide_fc_license_credits ? '</fieldset>' : ''; ?>

			</div>
			<?php $fc_logo_license = ob_get_clean(); ?>

			<?php
			// Place PHP/DB requirements at bottom if no warning found
			if ( !isset($php_lims['warning']) && $this->perms->CanConfig ) :
				echo $fc_requirements;
			endif;

			if ($hide_fc_license_credits) :
				echo JHtml::_('bootstrap.addSlide', 'fc-dash-sliders', "About FLEXIcontent", 'fc-dash-sliders-aboutflexi' );
				echo $fc_logo_license;
				echo JHtml::_('bootstrap.endSlide');
			endif;

			echo JHtml::_('bootstrap.endAccordion');

		endif; /* !$skip_sliders */ ?>


		<?php if (!$hide_fc_license_credits) echo $fc_logo_license; ?>


	<!-- Common management form fields -->
	<input type="hidden" name="option" value="com_flexicontent" />
	<input type="hidden" name="controller" value="" />
	<input type="hidden" name="view" value="" />
	<input type="hidden" name="task" value="" />
	<input type="hidden" name="fcform" value="1" />
	<?php echo JHtml::_( 'form.token' ); ?>

	<!-- fc_perf -->

	</div>  <!-- j-main-container -->
</div>  <!-- row / row-fluid-->

</form>
</div><!-- #flexicontent end -->
