<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Legacy;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Loader for the legacy FLEXIcontent models in an API request.
 *
 * FLEXIcontent is a classic MVC component: its models are not namespacables and
 * rely on relative includes, helper files, a category-tree global and a site
 * menu object. This class recreates the minimum of the administrator/site
 * bootstrap needed by those models, without changing their business logic.
 *
 * @since  5.3
 */
final class LegacyBootstrap
{
    /**
     * Already booted flag.
     *
     * @var    boolean
     */
    private static $booted = false;

    /**
     * Load the legacy helpers, globals and model classes.
     *
     * @return  void
     */
    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$booted = true;

        // The API application does not define the component path constants used
        // by legacy helpers (notably FCPagination).
        \defined('JPATH_COMPONENT_SITE') || \define('JPATH_COMPONENT_SITE', JPATH_SITE . '/components/com_flexicontent');
        \defined('JPATH_COMPONENT_ADMINISTRATOR') || \define('JPATH_COMPONENT_ADMINISTRATOR', JPATH_ADMINISTRATOR . '/components/com_flexicontent');

        $app     = Factory::getApplication();
        $input   = $app->input;
        $fcSite  = JPATH_SITE . '/components/com_flexicontent';
        $fcAdmin = JPATH_ADMINISTRATOR . '/components/com_flexicontent';

        Table::addIncludePath($fcAdmin . '/tables');

        // Do not let legacy models read/write admin filter values from the API
        // session: all filters are provided explicitly by the API controller.
        if ((int) $input->get('fcform', 0) === 0) {
            $input->set('fcform', 1);
        }

        require_once $fcAdmin . '/defineconstants.php';
        require_once $fcSite . '/classes/flexicontent.helper.php';
        require_once $fcSite . '/classes/flexicontent.categories.php';
        require_once $fcSite . '/classes/flexicontent.fields.php';
        require_once $fcSite . '/helpers/permission.php';
        require_once $fcSite . '/helpers/route.php';

        // Site item logic needs the shared FLEXI category tree. System plugins
        // usually set it, but API requests are not guaranteed to have it.
        global $globalcats;

        if (!\is_array($globalcats) && is_file(JPATH_PLUGINS . '/system/flexisystem/flexisystem.php')) {
            require_once JPATH_PLUGINS . '/system/flexisystem/flexisystem.php';
            $globalcats = \plgSystemFlexisystem::getCategoriesTree();
        }

        // Legacy getItem() calls getMenu() without a client name; in the API app
        // that resolves to client 'api', which has no menu class. Reuse the site
        // menu under that key so the legacy code finds a usable menu object.
        if ($app instanceof CMSApplication) {
            try {
                $siteMenu     = $app->getMenu('site');
                $menus        = new \ReflectionProperty(CMSApplication::class, 'menus');
                $menus->setAccessible(true);
                $currentMenus = (array) $menus->getValue($app);

                if (!isset($currentMenus['api'])) {
                    $currentMenus['api'] = $siteMenu;
                    $menus->setValue($app, $currentMenus);
                }
            } catch (\Throwable $e) {
                // A missing menu is not fatal for the list endpoint.
            }
        }

        // The two legacy item model classes have the same name in admin and
        // site. Load admin for lists, site for the single record (it adds
        // decideLayout() and the frontend viewing-access checks).
        require_once $fcAdmin . '/models/items.php';
        require_once $fcSite . '/models/item.php';
    }
}
