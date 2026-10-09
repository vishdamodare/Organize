<?php

namespace Espo\Modules\Jewellery\Tools;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Utils\Config;
use Espo\Entities\User;
use Espo\ORM\EntityManager;

/**
 * Dashboard and report figures from live records.
 *
 * Every query is restricted to the records the current user may read (EspoCRM ACL,
 * applied as an `id IN (<ACL-filtered ids>)` subquery). Tenant isolation is
 * structural: each company has its own database.
 *
 * Definitions (also in docs/USER_GUIDE.md):
 *  - Order value     = sum of order grand totals, orderDate in period, status not Draft/Cancelled.
 *                      This is the only figure treated as "sales"; it is NOT payment received.
 *  - Won deal value  = sum of expected value of deals marked Won, outcomeDate in period.
 *  - Open pipeline   = expected value of deals not yet Won/Lost (current, not period-bound).
 *  - Follow-ups      = open tasks (Not Started/Started) by due date.
 */
class Reports
{
    private const OPEN_TASK = ['Not Started', 'Started'];
    private const CLOSED_STAGES = ['Closed Won', 'Closed Lost'];
    private const NOT_SALES_ORDER = ['Draft', 'Cancelled'];

    private DateTimeZone $tz;

    public function __construct(
        private EntityManager $entityManager,
        private SelectBuilderFactory $selectBuilderFactory,
        private Acl $acl,
        private User $user,
        Config $config,
    ) {
        $this->tz = new DateTimeZone($config->get('timeZone') ?: 'UTC');
    }

    /**
     * @return array{string, string} [from, to] inclusive local dates (Y-m-d)
     */
    public function resolvePeriod(?string $period, ?string $from, ?string $to): array
    {
        $today = new DateTimeImmutable('today', $this->tz);

        return match ($period ?: 'month') {
            'today' => [$today->format('Y-m-d'), $today->format('Y-m-d')],
            'week' => [$today->modify('monday this week')->format('Y-m-d'), $today->modify('sunday this week')->format('Y-m-d')],
            'month' => [$today->format('Y-m-01'), $today->format('Y-m-t')],
            'year' => [$today->format('Y-01-01'), $today->format('Y-12-31')],
            'custom' => $this->validateRange($from, $to),
            default => throw new BadRequest('Unknown period.'),
        };
    }

    public function overview(string $from, string $to): array
    {
        [$todayFrom, $todayTo] = $this->utcRange($this->today(), $this->today());
        $now = gmdate('Y-m-d H:i:s');

        return [
            'period' => ['from' => $from, 'to' => $to],
            'totalCustomers' => $this->count('Account', []),
            'newEnquiries' => $this->count('Enquiry', ['enquiryDate>=' => $from, 'enquiryDate<=' => $to]),
            'openDeals' => $this->count('Opportunity', ['stage!=' => self::CLOSED_STAGES]),
            'openPipelineValue' => $this->sum('Opportunity', 'amount', ['stage!=' => self::CLOSED_STAGES]),
            'followUpsDueToday' => $this->count('Task', ['status' => self::OPEN_TASK, 'dateEnd>=' => $todayFrom, 'dateEnd<' => $todayTo]),
            'overdueFollowUps' => $this->count('Task', ['status' => self::OPEN_TASK, 'dateEnd<' => $now]),
            'wonDeals' => $this->count('Opportunity', ['stage' => 'Closed Won', 'outcomeDate>=' => $from, 'outcomeDate<=' => $to]),
            'wonDealValue' => $this->sum('Opportunity', 'amount', ['stage' => 'Closed Won', 'outcomeDate>=' => $from, 'outcomeDate<=' => $to]),
            'orderValue' => $this->sum('CustomerOrder', 'grandTotal', $this->salesOrderWhere($from, $to)),
            'pipelineByStage' => $this->group('Opportunity', 'stage', ['stage!=' => self::CLOSED_STAGES], 'amount'),
            'upcomingTasks' => $this->upcomingTasks(),
        ];
    }

    public function summary(string $from, string $to): array
    {
        [$utcFrom, $utcTo] = $this->utcRange($from, $to);
        $now = gmdate('Y-m-d H:i:s');
        $enquiryPeriod = ['enquiryDate>=' => $from, 'enquiryDate<=' => $to];
        $outcomePeriod = ['outcomeDate>=' => $from, 'outcomeDate<=' => $to];
        $quotePeriod = ['dateIssued>=' => $from, 'dateIssued<=' => $to];
        $dueInPeriod = ['dateEnd>=' => $utcFrom, 'dateEnd<' => $utcTo, 'status!=' => ['Canceled', 'Deferred']];
        $days = (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days;

        return [
            'period' => ['from' => $from, 'to' => $to],
            'enquiriesBySource' => $this->group('Enquiry', 'source', $enquiryPeriod),
            'enquiriesByStatus' => $this->group('Enquiry', 'status', $enquiryPeriod),
            'enquiriesByCategory' => $this->group('Enquiry', 'category', $enquiryPeriod),
            'dealsByStage' => $this->group('Opportunity', 'stage', ['createdAt>=' => $utcFrom, 'createdAt<' => $utcTo], 'amount'),
            'wonVsLost' => $this->group('Opportunity', 'stage', $outcomePeriod + ['stage' => self::CLOSED_STAGES], 'amount'),
            'lostReasons' => $this->group('Opportunity', 'lostReason', $outcomePeriod + ['stage' => 'Closed Lost']),
            'followUps' => [
                'due' => $this->count('Task', $dueInPeriod),
                'completed' => $this->count('Task', $dueInPeriod + ['status' => 'Completed']),
                'overdueNow' => $this->count('Task', ['status' => self::OPEN_TASK, 'dateEnd<' => $now]),
            ],
            'quotations' => [
                'issued' => $this->count('Quotation', $quotePeriod + ['status!=' => 'Draft']),
                'accepted' => $this->count('Quotation', $quotePeriod + ['status' => 'Accepted']),
                'acceptedValue' => $this->sum('Quotation', 'grandTotal', $quotePeriod + ['status' => 'Accepted']),
                'byStatus' => $this->group('Quotation', 'status', $quotePeriod, 'grandTotal'),
            ],
            'ordersByStatus' => $this->group('CustomerOrder', 'status', ['orderDate>=' => $from, 'orderDate<=' => $to], 'grandTotal'),
            'salesOverTime' => $this->group('CustomerOrder', ($days <= 62 ? 'DAY' : 'MONTH') . ':orderDate',
                $this->salesOrderWhere($from, $to), 'grandTotal'),
            'salespeople' => $this->salespeople($from, $to, $utcFrom, $utcTo, $now),
        ];
    }

    private function salespeople(string $from, string $to, string $utcFrom, string $utcTo, string $now): array
    {
        $metrics = [
            'enquiries' => $this->group('Enquiry', 'assignedUserId', ['enquiryDate>=' => $from, 'enquiryDate<=' => $to]),
            'won' => $this->group('Opportunity', 'assignedUserId', ['stage' => 'Closed Won', 'outcomeDate>=' => $from, 'outcomeDate<=' => $to], 'amount'),
            'lost' => $this->group('Opportunity', 'assignedUserId', ['stage' => 'Closed Lost', 'outcomeDate>=' => $from, 'outcomeDate<=' => $to]),
            'orders' => $this->group('CustomerOrder', 'assignedUserId', $this->salesOrderWhere($from, $to), 'grandTotal'),
            'tasksCompleted' => $this->group('Task', 'assignedUserId', ['status' => 'Completed', 'dateCompleted>=' => $utcFrom, 'dateCompleted<' => $utcTo]),
            'tasksOverdue' => $this->group('Task', 'assignedUserId', ['status' => self::OPEN_TASK, 'dateEnd<' => $now]),
        ];

        $rows = [];

        foreach ($metrics as $metric => $groups) {
            foreach ($groups ?? [] as $g) {
                $id = $g['key'] ?: '';
                $rows[$id] ??= ['userId' => $id, 'name' => null];
                $rows[$id][$metric] = $g['count'];

                if (array_key_exists('total', $g)) {
                    $rows[$id][$metric . 'Value'] = $g['total'];
                }
            }
        }

        foreach ($rows as $id => &$row) {
            $row['name'] = $id ? ($this->entityManager->getEntityById('User', $id)?->get('name') ?? 'Unknown') : 'Unassigned';
        }

        return array_values($rows);
    }

    private function upcomingTasks(): ?array
    {
        $scope = $this->scope('Task');

        if ($scope === null) {
            return null;
        }

        $tasks = $this->entityManager->getRDBRepository('Task')
            ->select(['id', 'name', 'dateEnd', 'taskType', 'priority', 'parentType', 'parentId'])
            ->where($scope + ['status' => self::OPEN_TASK])
            ->order('dateEnd')
            ->limit(0, 8)
            ->find();

        $now = gmdate('Y-m-d H:i:s');
        $list = [];

        foreach ($tasks as $t) {
            $parent = $t->get('parentId') && $t->get('parentType') ?
                $this->entityManager->getEntityById($t->get('parentType'), $t->get('parentId')) : null;

            $list[] = [
                'id' => $t->getId(),
                'name' => $t->get('name'),
                'dateEnd' => $t->get('dateEnd'),
                'taskType' => $t->get('taskType'),
                'priority' => $t->get('priority'),
                'parentType' => $t->get('parentType'),
                'parentId' => $t->get('parentId'),
                'parentName' => $parent && $this->acl->checkEntityRead($parent) ? $parent->get('name') : null,
                'overdue' => $t->get('dateEnd') && $t->get('dateEnd') < $now,
            ];
        }

        return $list;
    }

    private function salesOrderWhere(string $from, string $to): array
    {
        return ['orderDate>=' => $from, 'orderDate<=' => $to, 'status!=' => self::NOT_SALES_ORDER];
    }

    /**
     * ACL restriction as a where-clause, or null when the user can't read the scope at all.
     */
    private function scope(string $entityType): ?array
    {
        if (!$this->acl->checkScope($entityType, Acl\Table::ACTION_READ)) {
            return null;
        }

        $ids = $this->selectBuilderFactory->create()
            ->from($entityType)
            ->forUser($this->user)
            ->withStrictAccessControl()
            ->buildQueryBuilder()
            ->select(['id'])
            ->order([])
            ->build();

        return ['id=s' => $ids];
    }

    private function count(string $entityType, array $where): ?int
    {
        $scope = $this->scope($entityType);

        if ($scope === null) {
            return null;
        }

        return $this->entityManager->getRDBRepository($entityType)->where($scope + $where)->count();
    }

    private function sum(string $entityType, string $field, array $where): ?string
    {
        $scope = $this->scope($entityType);

        if ($scope === null) {
            return null;
        }

        $query = $this->entityManager->getQueryBuilder()
            ->select()
            ->from($entityType)
            ->select([['SUM:' . $field, 'total']])
            ->where($scope + $where + ['deleted' => false])
            ->build();

        $row = $this->entityManager->getQueryExecutor()->execute($query)->fetch();

        return Money::round(Money::normalize($row['total'] ?? '0'));
    }

    /**
     * @return ?array<int, array{key: ?string, count: int, total?: string}>
     */
    private function group(string $entityType, string $expression, array $where, ?string $sumField = null): ?array
    {
        $scope = $this->scope($entityType);

        if ($scope === null) {
            return null;
        }

        $select = [[$expression, 'k'], ['COUNT:id', 'c']];

        if ($sumField) {
            $select[] = ['SUM:' . $sumField, 't'];
        }

        $query = $this->entityManager->getQueryBuilder()
            ->select()
            ->from($entityType)
            ->select($select)
            ->where($scope + $where + ['deleted' => false])
            ->group($expression)
            ->order($expression)
            ->build();

        $out = [];

        foreach ($this->entityManager->getQueryExecutor()->execute($query)->fetchAll() as $row) {
            $item = ['key' => $row['k'], 'count' => (int) $row['c']];

            if ($sumField) {
                $item['total'] = Money::round(Money::normalize($row['t'] ?? '0'));
            }

            $out[] = $item;
        }

        return $out;
    }

    private function today(): string
    {
        return (new DateTimeImmutable('today', $this->tz))->format('Y-m-d');
    }

    /**
     * Local inclusive date range → UTC [start, endExclusive) datetimes for datetime columns.
     *
     * @return array{string, string}
     */
    private function utcRange(string $from, string $to): array
    {
        $utc = new DateTimeZone('UTC');
        $start = (new DateTimeImmutable($from . ' 00:00:00', $this->tz))->setTimezone($utc);
        $end = (new DateTimeImmutable($to . ' 00:00:00', $this->tz))->modify('+1 day')->setTimezone($utc);

        return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
    }

    /**
     * @return array{string, string}
     */
    private function validateRange(?string $from, ?string $to): array
    {
        foreach ([$from, $to] as $d) {
            if (!$d || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !DateTimeImmutable::createFromFormat('!Y-m-d', $d)) {
                throw new BadRequest('Dates must be in YYYY-MM-DD format.');
            }
        }

        if ($from > $to) {
            throw new BadRequest('"From" date must be before "To" date.');
        }

        if ((new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > 3660) {
            throw new BadRequest('Date range is too long.');
        }

        return [$from, $to];
    }
}
