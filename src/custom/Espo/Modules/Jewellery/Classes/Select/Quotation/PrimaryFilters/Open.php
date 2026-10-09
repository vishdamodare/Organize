<?php

namespace Espo\Modules\Jewellery\Classes\Select\Quotation\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

/** Primary filter: ['status' => ['Draft', 'Sent']]. */
class Open implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where(['status' => ['Draft', 'Sent']]);
    }
}
