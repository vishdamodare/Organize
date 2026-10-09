<?php

namespace Espo\Modules\Jewellery\Classes\Select\Enquiry\PrimaryFilters;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Utils\Config;
use Espo\Core\Select\Primary\Filter;
use Espo\ORM\Query\SelectBuilder;

/** Open enquiries whose follow-up date is today or earlier. */
class FollowUpDue implements Filter
{
    public function __construct(private Config $config) {}

    public function apply(SelectBuilder $queryBuilder): void
    {
        $tz = new DateTimeZone($this->config->get('timeZone') ?: 'UTC');
        $today = (new DateTimeImmutable('today', $tz))->format('Y-m-d');

        $queryBuilder->where(['status!=' => ['Converted', 'Lost'], 'nextFollowUpDate<=' => $today]);
    }
}
