<?php

namespace Espo\Modules\Jewellery\Hooks\Opportunity;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Config;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Won/Lost bookkeeping. A Won deal is a sales outcome only — it does not create
 * an order or imply payment (orders come from accepted quotations).
 */
class Outcome implements BeforeSave
{
    private const CLOSED = ['Closed Won', 'Closed Lost'];

    public function __construct(private Config $config) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $stage = $entity->get('stage');

        if ($stage === 'Closed Lost' && !$entity->get('lostReason')) {
            throw new BadRequest('Please select a lost reason when marking a deal as Lost.');
        }

        if (!$entity->isNew() && !$entity->isAttributeChanged('stage')) {
            return;
        }

        if (in_array($stage, self::CLOSED, true)) {
            $tz = new DateTimeZone($this->config->get('timeZone') ?: 'UTC');
            $entity->set('outcomeDate', (new DateTimeImmutable('now', $tz))->format('Y-m-d'));
        } else {
            $entity->set('outcomeDate', null);
        }

        if ($stage !== 'Closed Lost') {
            $entity->set('lostReason', '');
        }
    }
}
