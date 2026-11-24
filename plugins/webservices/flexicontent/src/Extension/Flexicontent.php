<?php

/**
 * @package         FLEXIcontent
 * @subpackage      plg_webservices_flexicontent
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Plugin\WebServices\Flexicontent\Extension;

use Joomla\CMS\Event\Application\BeforeApiRouteEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Web Services adapter for com_flexicontent.
 *
 * Registers the FLEXIcontent API routes in the API application router.
 *
 * @since  1.0.0
 */
final class Flexicontent extends CMSPlugin implements SubscriberInterface
{
    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeApiRoute' => 'onBeforeApiRoute',
        ];
    }

    /**
     * Registers com_flexicontent API routes.
     *
     * Read-only resources: categories, tags, types and fields. Write operations
     * are registered by createCRUDRoutes but refused by their controllers.
     * Item writes are handled by ItemsController.
     *
     * @param   BeforeApiRouteEvent  $event  The event object.
     *
     * @return  void
     */
    public function onBeforeApiRoute(BeforeApiRouteEvent $event): void
    {
        $router = $event->getRouter();

        $router->createCRUDRoutes(
            'v1/flexicontent/items',
            'items',
            ['component' => 'com_flexicontent']
        );

        $router->createCRUDRoutes('v1/flexicontent/categories', 'categories', ['component' => 'com_flexicontent']);
        $router->createCRUDRoutes('v1/flexicontent/tags', 'tags', ['component' => 'com_flexicontent']);
        $router->createCRUDRoutes('v1/flexicontent/types', 'types', ['component' => 'com_flexicontent']);
        $router->createCRUDRoutes('v1/flexicontent/fields', 'fields', ['component' => 'com_flexicontent']);

        // File/image uploads. Only POST is implemented by MediaController; the
        // other CRUD verbs are not registered for this resource.
        $router->addRoutes([
            new \Joomla\Router\Route(
                ['POST'],
                'v1/flexicontent/media',
                'media.add',
                [],
                ['component' => 'com_flexicontent']
            ),
        ]);
    }
}
