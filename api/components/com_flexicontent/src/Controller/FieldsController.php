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
 * FLEXIcontent custom fields API controller.
 *
 * Supported filters: filter[search], filter[state], filter[core],
 * filter[form], filter[type] and filter[access] (the latter is manager-only).
 * The form_* configuration pseudo-fields are hidden unless filter[form]=1.
 *
 * @since  5.3
 */
class FieldsController extends TaxonomyController
{
    /**
     * The content type of the item.
     *
     * @var    string
     */
    protected $contentType = 'fields';

    /**
     * API resource name.
     *
     * @var    string
     */
    protected $resource = 'fields';

    /**
     * Singular model name.
     *
     * @var    string
     */
    protected $singular = 'field';

    /**
     * The default view for the display method.
     *
     * @var    string
     */
    protected $default_view = 'fields';

    /**
     * Apply field filters.
     *
     * @return  static
     */
    public function displayList()
    {
        $filters = $this->input->get('filter', [], 'array');

        $this->applySearchFilter($filters, 'filter.search');
        $this->applyStateFilter($filters);
        $this->applyIntFilter($filters, 'core', 'filter.core');
        $this->applyIntFilter($filters, 'form', 'filter.form');
        $this->applyIntFilter($filters, 'type', 'filter.type_id');

        if (AccessHelper::canManage()) {
            $this->applyIntFilter($filters, 'access', 'filter.access');
        }

        $this->applyListOrdering();

        return parent::displayList();
    }
}
