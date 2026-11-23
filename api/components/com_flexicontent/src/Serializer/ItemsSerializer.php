<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Serializer;

use Joomla\CMS\Serializer\JoomlaSerializer;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Items JSON:API serializer.
 *
 * MVP spike: attributes only. Relationships (category, type, tags, author)
 * and FLEXI custom field values are added in later phases.
 *
 * @since  5.3
 */
class ItemsSerializer extends JoomlaSerializer
{
}