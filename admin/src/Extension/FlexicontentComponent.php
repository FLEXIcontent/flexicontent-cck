<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Administrator\Extension;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Dispatcher\ApiDispatcher;
use Joomla\CMS\Dispatcher\DispatcherInterface;
use Joomla\CMS\Extension\LegacyComponent;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Component extension for com_flexicontent.
 *
 * Extends LegacyComponent on purpose: every legacy service (legacy MVC factory,
 * legacy entry-point dispatcher, SEF router, category/tag counting, ...) is
 * inherited unchanged so the existing administrator/site behaviour is preserved.
 *
 * The only deviation is for the API client: it returns the modern ApiDispatcher
 * backed by a namespaced MVC factory so that
 * Joomla\Component\Flexicontent\Api\Controller\ItemsController can be resolved.
 *
 * @since  5.3
 */
class FlexicontentComponent extends LegacyComponent
{
    /**
     * Modern MVC factory used only for the API client.
     *
     * @var    MVCFactoryInterface|null
     */
    private $apiMVCFactory;

    /**
     * Inject the modern MVC factory used by the API dispatcher.
     *
     * @param   MVCFactoryInterface  $factory  The namespaced (API) MVC factory.
     *
     * @return  void
     */
    public function setApiMVCFactory(MVCFactoryInterface $factory): void
    {
        $this->apiMVCFactory = $factory;
    }

    /**
     * Returns the dispatcher for the given application.
     *
     * Administrator and Site keep the legacy entry-point dispatcher. Only the
     * API client is routed through the modern ApiDispatcher.
     *
     * @param   CMSApplicationInterface  $application  The application.
     *
     * @return  DispatcherInterface
     */
    public function getDispatcher(CMSApplicationInterface $application): DispatcherInterface
    {
        if ($application->isClient('api') && $this->apiMVCFactory !== null) {
            return new ApiDispatcher($application, $application->getInput(), $this->apiMVCFactory);
        }

        return parent::getDispatcher($application);
    }
}