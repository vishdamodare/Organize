<?php

namespace Espo\Modules\Jewellery\Tools;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Acl;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Config;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Controlled conversions. Both run in a transaction with a row lock on the source
 * record, so double-clicks or two users converting at once can't create duplicates.
 */
class Conversion
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private User $user,
        private Config $config,
        private Totals $totals,
    ) {}

    /**
     * Enquiry → Deal. The enquiry is kept (status Converted) and linked, so history is preserved.
     */
    public function enquiryToDeal(string $id): Entity
    {
        $this->loadForEdit('Enquiry', $id);

        if (!$this->acl->checkScope('Opportunity', Acl\Table::ACTION_CREATE)) {
            throw new Forbidden('No permission to create deals.');
        }

        return $this->entityManager->getTransactionManager()->run(function () use ($id) {
            $enquiry = $this->lock('Enquiry', $id);

            $existing = $this->entityManager->getRDBRepository('Opportunity')
                ->where(['enquiryId' => $id])
                ->findOne();

            if ($existing) {
                throw new Conflict('This enquiry has already been converted to a deal.');
            }

            $deal = $this->entityManager->getNewEntity('Opportunity');
            $deal->set([
                'name' => $enquiry->get('name'),
                'accountId' => $enquiry->get('accountId'),
                'enquiryId' => $enquiry->getId(),
                'stage' => 'Qualified',
                'probability' => 40,
                'amount' => $enquiry->get('budget') ?? '0',
                'amountCurrency' => $this->totals->getCurrency(),
                'closeDate' => $enquiry->get('expectedPurchaseDate') ?? $this->today('+30 days'),
                'description' => $enquiry->get('description'),
                'assignedUserId' => $enquiry->get('assignedUserId') ?? $this->user->getId(),
                'teamsIds' => $enquiry->getLinkMultipleIdList('teams'),
            ]);
            $this->entityManager->saveEntity($deal);

            $enquiry->set('status', 'Converted');
            $this->entityManager->saveEntity($enquiry);

            return $deal;
        });
    }

    /**
     * Accepted quotation → Order. Items are copied, so the order is a snapshot of
     * what the customer accepted; the quotation is then locked (see Quotation Guard).
     */
    public function quotationToOrder(string $id): Entity
    {
        $this->loadForEdit('Quotation', $id);

        if (!$this->acl->checkScope('CustomerOrder', Acl\Table::ACTION_CREATE)) {
            throw new Forbidden('No permission to create orders.');
        }

        return $this->entityManager->getTransactionManager()->run(function () use ($id) {
            $quotation = $this->lock('Quotation', $id);

            if ($quotation->get('status') !== 'Accepted') {
                throw new Conflict('Only an Accepted quotation can be converted to an order.');
            }

            $existing = $this->entityManager->getRDBRepository('CustomerOrder')
                ->where(['quotationId' => $id])
                ->findOne();

            if ($existing) {
                throw new Conflict('This quotation has already been converted to order ' . $existing->get('number') . '.');
            }

            if (!$this->entityManager->getRDBRepository('QuotationItem')->where(['quotationId' => $id])->count()) {
                throw new Conflict('Add at least one line item before converting the quotation to an order.');
            }

            $order = $this->entityManager->getNewEntity('CustomerOrder');
            $order->set([
                'accountId' => $quotation->get('accountId'),
                'quotationId' => $quotation->getId(),
                'opportunityId' => $quotation->get('opportunityId'),
                'status' => 'Draft',
                'orderDate' => $this->today(),
                'description' => $quotation->get('description'),
                'assignedUserId' => $quotation->get('assignedUserId') ?? $this->user->getId(),
                'teamsIds' => $quotation->getLinkMultipleIdList('teams'),
            ]);
            $this->entityManager->saveEntity($order);

            $items = $this->entityManager->getRDBRepository('QuotationItem')
                ->where(['quotationId' => $id])
                ->order('sortOrder')
                ->find();

            foreach ($items as $item) {
                $copy = $this->entityManager->getNewEntity('CustomerOrderItem');
                $copy->set([
                    'customerOrderId' => $order->getId(),
                    'productId' => $item->get('productId'),
                    'name' => $item->get('name'),
                    'quantity' => $item->get('quantity'),
                    'unitPrice' => $item->get('unitPrice'),
                    'discountPercent' => $item->get('discountPercent'),
                    'taxPercent' => $item->get('taxPercent'),
                    'sortOrder' => $item->get('sortOrder'),
                ]);
                $this->entityManager->saveEntity($copy, [Totals::OPTION_INTERNAL => true]);
            }

            $this->totals->recalculate($order);

            return $order;
        });
    }

    /**
     * Not found and not readable look identical to the caller (no ID probing).
     */
    private function loadForEdit(string $entityType, string $id): Entity
    {
        $entity = $this->entityManager->getEntityById($entityType, $id);

        if (!$entity || !$this->acl->checkEntityRead($entity)) {
            throw new NotFound();
        }

        if (!$this->acl->checkEntityEdit($entity)) {
            throw new Forbidden();
        }

        return $entity;
    }

    private function lock(string $entityType, string $id): Entity
    {
        $entity = $this->entityManager->getRDBRepository($entityType)
            ->forUpdate()
            ->where(['id' => $id])
            ->findOne();

        if (!$entity) {
            throw new NotFound();
        }

        return $entity;
    }

    private function today(string $modify = 'now'): string
    {
        $tz = new DateTimeZone($this->config->get('timeZone') ?: 'UTC');

        return (new DateTimeImmutable($modify, $tz))->format('Y-m-d');
    }
}
