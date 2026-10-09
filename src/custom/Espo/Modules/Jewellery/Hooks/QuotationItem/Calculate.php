<?php

namespace Espo\Modules\Jewellery\Hooks\QuotationItem;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Hook\Hook\BeforeRemove;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\Jewellery\Tools\Totals;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Line total on save; parent totals after any change; lock when parent is final.
 * Reused by CustomerOrderItem.
 */
class Calculate implements BeforeSave, AfterSave, BeforeRemove, AfterRemove
{
    public function __construct(private Totals $totals) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $parent = $this->totals->getParent($entity);

        if (!$parent) {
            throw new BadRequest('Line item must belong to an existing record.');
        }

        if (!$options->get(Totals::OPTION_INTERNAL)) {
            $this->totals->assertParentEditable($parent);
        }

        $this->totals->applyLine($entity);
    }

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(Totals::OPTION_INTERNAL)) {
            return; // caller recalculates once at the end
        }

        $parent = $this->totals->getParent($entity);

        if ($parent) {
            $this->totals->recalculate($parent);
        }
    }

    public function beforeRemove(Entity $entity, RemoveOptions $options): void
    {
        $parent = $this->totals->getParent($entity);

        if ($parent && !$options->get(Totals::OPTION_INTERNAL)) {
            $this->totals->assertParentEditable($parent);
        }
    }

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        if ($options->get(Totals::OPTION_INTERNAL)) {
            return;
        }

        $parent = $this->totals->getParent($entity);

        if ($parent) {
            $this->totals->recalculate($parent);
        }
    }
}
