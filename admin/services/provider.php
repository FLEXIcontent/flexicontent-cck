<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 *
 * Minimal modern service provider.
 *
 * FLEXIcontent is a legacy MVC component. This provider exists ONLY so the
 * Joomla API application can resolve namespaced API MVC classes
 * (Joomla\Component\Flexicontent\Api\...). It deliberately keeps the legacy
 * component services intact: FlexicontentComponent extends LegacyComponent and
 * only overrides the dispatcher for the API client. Administrator and Site
 * dispatching, routing, categories and tags keep using the legacy code paths.
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Component\Flexicontent\Administrator\Extension\FlexicontentComponent;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     */
    public function register(Container $container)
    {
        // Resolves an ApiMVCFactory for the API client (and a regular MVCFactory otherwise).
        $container->registerServiceProvider(new MVCFactory('\\Joomla\\Component\\Flexicontent'));

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $component = new FlexicontentComponent('com_flexicontent');

                // The modern factory is used only by the API dispatcher.
                $component->setApiMVCFactory($container->get(MVCFactoryInterface::class));

                return $component;
            }
        );
    }
};