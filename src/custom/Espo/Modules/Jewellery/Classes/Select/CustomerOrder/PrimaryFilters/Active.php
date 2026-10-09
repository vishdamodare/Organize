<?php

namespace Espo\Modules\Jewellery\Classes\Select\CustomerOrder\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

/** Primary filter: ['status!=' => ['Delivered', 'Cancelled']]. */
class Active implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where(['status!=' => ['Delivered', 'Cancelled']]);
    }
}
