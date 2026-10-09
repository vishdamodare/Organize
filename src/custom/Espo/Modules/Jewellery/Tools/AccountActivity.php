<?php

namespace Espo\Modules\Jewellery\Tools;

use Espo\ORM\EntityManager;

/**
 * Keeps Account.nextFollowUpAt / lastInteractionAt in sync with activities.
 *  next follow-up   = earliest due date of the customer's open tasks
 *  last interaction = latest completed task / held call / held meeting
 * Written with a direct UPDATE so it doesn't touch modifiedAt or the stream.
 */
class AccountActivity
{
    private const OPEN_TASK = ['Not Started', 'Started'];

    public function __construct(private EntityManager $entityManager) {}

    public function refresh(?string $accountId): void
    {
        if (!$accountId) {
            return;
        }

        $next = $this->value('Task', 'MIN:dateEnd', ['accountId' => $accountId, 'status' => self::OPEN_TASK]);

        $last = max(array_filter([
            $this->value('Task', 'MAX:dateCompleted', ['accountId' => $accountId, 'status' => 'Completed']),
            $this->value('Call', 'MAX:dateStart', ['accountId' => $accountId, 'status' => 'Held']),
            $this->value('Meeting', 'MAX:dateStart', ['accountId' => $accountId, 'status' => 'Held']),
        ]) ?: [null]);

        $query = $this->entityManager->getQueryBuilder()
            ->update()
            ->in('Account')
            ->set(['nextFollowUpAt' => $next, 'lastInteractionAt' => $last])
            ->where(['id' => $accountId])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($query);
    }

    private function value(string $entityType, string $expression, array $where): ?string
    {
        $query = $this->entityManager->getQueryBuilder()
            ->select()
            ->from($entityType)
            ->select([[$expression, 'v']])
            ->where($where + ['deleted' => false])
            ->build();

        $row = $this->entityManager->getQueryExecutor()->execute($query)->fetch();

        return $row['v'] ?? null;
    }
}
