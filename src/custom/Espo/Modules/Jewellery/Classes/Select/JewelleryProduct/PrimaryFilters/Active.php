<?php

namespace Espo\Modules\Jewellery\Classes\Select\JewelleryProduct\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

/** Primary filter: ['status' => 'Active']. */
class Active implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where(['status' => 'Active']);
    }
}
