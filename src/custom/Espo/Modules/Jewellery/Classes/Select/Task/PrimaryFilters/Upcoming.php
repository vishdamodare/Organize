<?php

namespace Espo\Modules\Jewellery\Classes\Select\Task\PrimaryFilters;

use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

/** Open tasks due from now on. */
class Upcoming implements Filter
{
    public function apply(SelectBuilder $queryBuilder): void
    {
        $queryBuilder->where(['status' => ['Not Started', 'Started'], 'dateEnd>=' => gmdate('Y-m-d H:i:s')]);
    }
}
