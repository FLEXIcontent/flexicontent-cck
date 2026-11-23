<?php

/**
 * @package         FLEXIcontent
 * @subpackage      plg_webservices_flexicontent
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Plugin\WebServices\Flexicontent\Extension\Flexicontent;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(Flexicontent::class, function (Container $container) {
                $plugin = new Flexicontent(
                    (array) PluginHelper::getPlugin('webservices', 'flexicontent')
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }
};