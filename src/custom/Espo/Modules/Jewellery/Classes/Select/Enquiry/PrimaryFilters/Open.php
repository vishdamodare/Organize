<?php

namespace Espo\Modules\Jewellery\Classes\Select\Enquiry\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

/** Primary filter: ['status!=' => ['Converted', 'Lost']]. */
class Open implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where(['status!=' => ['Converted', 'Lost']]);
    }
}
