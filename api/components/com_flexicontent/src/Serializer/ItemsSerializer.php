<?php

/**
 * @package         FLEXIcontent
 * @subpackage      com_flexicontent.API
 *
 * @copyright       Copyright (C) FLEXIcontent team
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

namespace Joomla\Component\Flexicontent\Api\Serializer;

use Joomla\CMS\Router\Route;
use Joomla\CMS\Serializer\JoomlaSerializer;
use Joomla\CMS\Uri\Uri;
use Tobscure\JsonApi\Collection;
use Tobscure\JsonApi\Relationship;
use Tobscure\JsonApi\Resource;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Items JSON:API serializer.
 *
 * Relationships use Joomla's own resource types: FLEXI categories are Joomla
 * categories (com_content extension on existing sites), and FLEXI tags are
 * bridged to Joomla tags through #__flexicontent_tags.jtag_id. The type is kept
 * as a simple attribute until a /v1/flexicontent/types route exists.
 *
 * @since  5.3
 */
class ItemsSerializer extends JoomlaSerializer
{
    /**
     * Build the FLEXI tags relationship, using the Joomla tag ids.
     *
     * @param   object  $model  The item.
     *
     * @return  Relationship
     */
    public function tags($model): Relationship
    {
        $serializer = new JoomlaSerializer('tags');
        $resources  = [];

        foreach ((array) ($model->flexi_tags ?? []) as $jtagId => $tagName) {
            $resources[] = (new Resource((int) $jtagId, $serializer))
                ->addLink('self', $this->apiLink('tags/' . (int) $jtagId));
        }

        return new Relationship(new Collection($resources, $serializer));
    }

    /**
     * Build the author relationship.
     *
     * @param   object  $model  The item.
     *
     * @return  ?Relationship
     */
    public function createdBy($model): ?Relationship
    {
        return $this->userRelationship((int) $model->created_by);
    }

    /**
     * Build the last modifier relationship.
     *
     * @param   object  $model  The item.
     *
     * @return  ?Relationship
     */
    public function modifiedBy($model): ?Relationship
    {
        return !empty($model->modified_by) ? $this->userRelationship((int) $model->modified_by) : null;
    }

    /**
     * Build the main category relationship.
     *
     * @param   object  $model  The item.
     *
     * @return  Relationship
     */
    public function category($model): Relationship
    {
        $serializer = new JoomlaSerializer('categories');
        $resource   = (new Resource((int) $model->catid, $serializer))
            ->addLink('self', $this->apiLink('content/categories/' . (int) $model->catid));

        return new Relationship($resource);
    }

    /**
     * Build the additional categories relationship (FLEXI secondary categories).
     *
     * @param   object  $model  The item.
     *
     * @return  Relationship
     */
    public function categories($model): Relationship
    {
        $serializer = new JoomlaSerializer('categories');
        $resources  = [];

        foreach (array_unique(array_map('intval', (array) ($model->category_ids ?? []))) as $categoryId) {
            if ($categoryId > 0) {
                $resources[] = (new Resource($categoryId, $serializer))
                    ->addLink('self', $this->apiLink('content/categories/' . $categoryId));
            }
        }

        return new Relationship(new Collection($resources, $serializer));
    }

    /**
     * Build a relationship to a Joomla user.
     *
     * @param   integer  $userId  The user id.
     *
     * @return  ?Relationship
     */
    private function userRelationship(int $userId): ?Relationship
    {
        if ($userId <= 0) {
            return null;
        }

        $serializer = new JoomlaSerializer('users');
        $resource   = (new Resource($userId, $serializer))
            ->addLink('self', $this->apiLink('users/' . $userId));

        return new Relationship($resource);
    }

    /**
     * Build an absolute API link.
     *
     * @param   string  $path  The path after /api/index.php/v1/.
     *
     * @return  string
     */
    private function apiLink(string $path): string
    {
        return Route::link('site', Uri::root() . 'api/index.php/v1/' . $path);
    }
}
