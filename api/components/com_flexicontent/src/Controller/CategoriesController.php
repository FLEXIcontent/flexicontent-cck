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
 * Read-only FLEXIcontent categories API controller.
 *
 * FLEXIcontent stores its categories in #__categories using the extension
 * configured in the component parameters (com_content by default).
 *
 * @since  5.3
 */
class CategoriesController extends ReadOnlyController
{
    /**
     * The content type of the item.
     *
     * @var    string
     */
    protected $contentType = 'categories';

    /**
     * The default view for the display method.
     *
     * @var    string
     */
    protected $default_view = 'categories';

    /**
     * Apply category filters.
     *
     * Supported: filter[search], filter[state], filter[parent_id],
     * filter[level], filter[language]. filter[access] is manager-only.
     *
     * @return  static
     */
    public function displayList()
    {
        $filters = $this->input->get('filter', [], 'array');

        $this->applySearchFilter($filters, 'filter.search');
        $this->applyStateFilter($filters);
        $this->applyIntFilter($filters, 'parent_id', 'filter.parent_id');
        $this->applyIntFilter($filters, 'level', 'filter.level');
        $this->applyStringFilter($filters, 'language', 'filter.language');

        if (AccessHelper::canManage()) {
            $this->applyIntFilter($filters, 'access', 'filter.access');
        }

        $this->applyListOrdering();

        return parent::displayList();
    }
}
