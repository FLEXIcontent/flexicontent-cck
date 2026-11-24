<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Controller;

use Joomla\Component\Flexicontent\Api\Helper\AccessHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * FLEXIcontent content types API controller.
 *
 * @since  5.3
 */
class TypesController extends TaxonomyController
{
    /**
     * The content type of the item.
     *
     * @var    string
     */
    protected $contentType = 'types';

    /**
     * API resource name.
     *
     * @var    string
     */
    protected $resource = 'types';

    /**
     * Singular model name.
     *
     * @var    string
     */
    protected $singular = 'type';

    /**
     * The default view for the display method.
     *
     * @var    string
     */
    protected $default_view = 'types';

    /**
     * Apply type filters.
     *
     * Supported: filter[search], filter[state]. filter[access] is manager-only.
     *
     * @return  static
     */
    public function displayList()
    {
        $filters = $this->input->get('filter', [], 'array');

        $this->applySearchFilter($filters, 'filter.search');
        $this->applyStateFilter($filters);

        if (AccessHelper::canManage()) {
            $this->applyIntFilter($filters, 'access', 'filter.access');
        }

        $this->applyListOrdering();

        return parent::displayList();
    }
}
