<?php
/**
 * @version 1.5 stable $Id: controller.php 1433 2012-08-13 22:20:28Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 *  * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the 
 * GNU General Public License for more details.
 */

// Check to ensure this file is within the rest of the framework
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Pagination\Pagination;

/**
 * Pagination Class. Provides a common interface for content pagination for the
 * Joomla! Platform.
 *
 * @package     Joomla.Platform
 * @subpackage  HTML
 * @since       11.1
 */
class FCPagination extends Pagination
{
    public $hideEmptyLimitstart = true;

    /**
     * Create and return the pagination result set counter string, ie. Results 1-10 of 42
     *
     * @return  string  Pagination result set counter string
     * @since   1.5
     */
    public function getResultsCounter(): string
    {
        $app = Factory::getApplication();

        if ($app->isClient('administrator')) {
            return parent::getResultsCounter();
        }

        $fromResult = $this->limitstart + 1;

        // If the limit is reached before the end of the list
        $toResult = ($this->limitstart + $this->limit < $this->total)
            ? $this->limitstart + $this->limit
            : $this->total;

        // Logic for FLEXIcontent
        $fc_view_total = $this->total;

        if ($fc_view_total > 0) {
            return Text::sprintf('JLIB_HTML_RESULTS_OF', $fromResult, $toResult, $fc_view_total);
        }

        return "\n" . Text::_('JLIB_HTML_NO_RECORDS_FOUND');
    }

    /**
     * Create and return the pagination data object.
     *
     * @return  \stdClass  Pagination data object.
     * @since   3.0.0
     */
    protected function _buildDataObject(): \stdClass
    {
        if (Factory::getApplication()->isClient('administrator')) {
            return parent::_buildDataObject();
        }

        $app   = Factory::getApplication();
        $input = $app->input;

        // Platform defaults to clean from URL
        $defaultUrlParams = [
            'format'        => 'CMD',
            'option'        => 'CMD',
            'controller'    => 'CMD',
            'view'          => 'CMD',
            'layout'        => 'STRING',
            'task'          => 'CMD',
            'template'      => 'CMD',
            'templateStyle' => 'INT',
            'tmpl'          => 'CMD',
            'tpl'           => 'CMD',
            'id'            => 'STRING',
            'Itemid'        => 'INT',
        ];
        
        foreach ($defaultUrlParams as $param => $filter) {
            $value = $input->get($param, '', $filter);
            if ($value === null || $value === '') {
                $input->set($param, null);
            }
        }

        // Call parent function for SEF compatibility
        $data = parent::_buildDataObject();

        // Workaround for URL-encoded ampersands
        $linksToFix = ['start', 'end', 'next', 'previous'];
        foreach ($linksToFix as $key) {
            if (!empty($data->$key->link)) {
                $data->$key->link = str_replace('__amp__', '%26', $data->$key->link);
            }
        }

        if (!empty($data->pages)) {
            foreach ($data->pages as $page) {
                if (isset($page->link)) {
                    $page->link = str_replace('__amp__', '%26', $page->link);
                }
            }
        }

        return $data;
    }

    /**
     * Creates a dropdown box for selecting how many records to show per page.
     *
     * @return  string  The HTML for the limit # input box.
     * @since   1.5
     */
    public function getLimitBox(): string
    {
        if (!Factory::getApplication()->isClient('administrator')) {
            return parent::getLimitBox();
        }

        $limits = [];

        // Make the option list.
        for ($i = 5; $i <= 30; $i += 5) {
            $limits[] = HTMLHelper::_('select.option', (string) $i);
        }

        $limits[] = HTMLHelper::_('select.option', '50', Text::_('J50'));
        $limits[] = HTMLHelper::_('select.option', '100', Text::_('J100'));
        $limits[] = HTMLHelper::_('select.option', '200', Text::_('J200'));
        $limits[] = HTMLHelper::_('select.option', '500', Text::_('J500'));
        $limits[] = HTMLHelper::_('select.option', '0', Text::_('JALL'));

        $selected = $this->viewall ? 0 : $this->limit;

        // Build the select list with modern CSS classes
        return HTMLHelper::_(
            'select.genericlist',
            $limits,
            $this->prefix . 'limit',
            'class="fcfield_selectval form-select" size="1" onchange="Joomla.submitform();"',
            'value',
            'text',
            $selected
        );
    }
}