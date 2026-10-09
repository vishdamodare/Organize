<?php

namespace Espo\Modules\Jewellery\Hooks\Task;

use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Core\Hook\Hook\AfterSave;
use Espo\Modules\Jewellery\Tools\AccountActivity as Tracker;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;
use Espo\ORM\Repository\Option\SaveOptions;

/** Refresh the customer's next follow-up / last interaction. Reused by Call and Meeting. */
class AccountActivity implements AfterSave, AfterRemove
{
    public function __construct(private Tracker $tracker) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        $this->tracker->refresh($entity->get('accountId'));

        if ($entity->isAttributeChanged('accountId') && $entity->getFetched('accountId')) {
            $this->tracker->refresh($entity->getFetched('accountId'));
        }
    }

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        $this->tracker->refresh($entity->get('accountId'));
    }
}
