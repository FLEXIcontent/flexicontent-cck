<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * FLEXIcontent tags API controller.
 *
 * FLEXI tags are exposed with their Joomla tag bridge (jtag_id) when present.
 *
 * @since  5.3
 */
class TagsController extends TaxonomyController
{
    /**
     * The content type of the item.
     *
     * @var    string
     */
    protected $contentType = 'tags';

    /**
     * API resource name.
     *
     * @var    string
     */
    protected $resource = 'tags';

    /**
     * Singular model name.
     *
     * @var    string
     */
    protected $singular = 'tag';

    /**
     * The default view for the display method.
     *
     * @var    string
     */
    protected $default_view = 'tags';

    /**
     * Apply tag filters.
     *
     * Supported: filter[search], filter[state], filter[jtag].
     * filter[jtag]=1 only returns tags bridged to Joomla, 0 only unbridged tags.
     *
     * @return  static
     */
    public function displayList()
    {
        $filters = $this->input->get('filter', [], 'array');

        $this->applySearchFilter($filters, 'filter.search');
        $this->applyStateFilter($filters);
        $this->applyIntFilter($filters, 'jtag', 'filter.jtag');

        $this->applyListOrdering();

        return parent::displayList();
    }
}
