<?php

namespace Espo\Modules\Jewellery\Tools;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Utils\Config;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Server-side line and document totals for quotations and orders.
 * Client-submitted totals are always overwritten (fields are read-only too).
 */
class Totals
{
    /** item entity type => [parent entity type, parent id attribute] */
    public const ITEM_PARENT = [
        'QuotationItem' => ['Quotation', 'quotationId'],
        'CustomerOrderItem' => ['CustomerOrder', 'customerOrderId'],
    ];

    /** Option set on internal saves so guards and recalculation don't re-trigger. */
    public const OPTION_INTERNAL = 'jewelleryInternal';

    private const MONEY_FIELDS = ['subtotal', 'discountAmount', 'taxAmount', 'grandTotal'];

    public function __construct(
        private EntityManager $entityManager,
        private Config $config,
    ) {}

    public function getCurrency(): string
    {
        return $this->config->get('defaultCurrency') ?? 'INR';
    }

    public function getParent(Entity $item): ?Entity
    {
        [$parentType, $attr] = self::ITEM_PARENT[$item->getEntityType()];
        $id = $item->get($attr);

        return $id ? $this->entityManager->getEntityById($parentType, $id) : null;
    }

    /**
     * Quotation items are editable only while Draft/Sent. Order items only while
     * the order is Draft. Accepted quotations are frozen so the order created from
     * them always matches what the customer accepted.
     *
     * @throws Conflict
     */
    public function assertParentEditable(Entity $parent): void
    {
        if ($parent->getEntityType() === 'Quotation') {
            if (!in_array($parent->get('status'), ['Draft', 'Sent'], true)) {
                throw new Conflict('Line items are locked because the quotation is ' . $parent->get('status') .
                    '. Change the status back to Draft or create a new quotation.');
            }

            return;
        }

        if ($parent->get('status') !== 'Draft') {
            throw new Conflict('Order items can only be changed while the order is in Draft status.');
        }
    }

    public function applyLine(Entity $item): void
    {
        if ($item->get('taxPercent') === null) {
            $item->set('taxPercent', (string) ($this->config->get('jewelleryDefaultTaxPercent') ?? 0));
        }

        if ($item->get('discountPercent') === null) {
            $item->set('discountPercent', '0');
        }

        $line = Money::line(
            $item->get('quantity'),
            $item->get('unitPrice'),
            $item->get('discountPercent'),
            $item->get('taxPercent')
        );

        $item->set('lineTotal', $line['total']);
        $item->set('lineTotalCurrency', $this->getCurrency());
        $item->set('unitPriceCurrency', $this->getCurrency());
    }

    public function recalculate(Entity $parent): void
    {
        $itemType = $parent->getEntityType() === 'Quotation' ? 'QuotationItem' : 'CustomerOrderItem';
        $attr = self::ITEM_PARENT[$itemType][1];

        $sum = ['subtotal' => '0', 'discountAmount' => '0', 'taxAmount' => '0', 'grandTotal' => '0'];

        $items = $this->entityManager
            ->getRDBRepository($itemType)
            ->where([$attr => $parent->getId()])
            ->find();

        foreach ($items as $item) {
            $line = Money::line(
                $item->get('quantity'),
                $item->get('unitPrice'),
                $item->get('discountPercent'),
                $item->get('taxPercent')
            );

            $sum['subtotal'] = bcadd($sum['subtotal'], $line['gross'], 2);
            $sum['discountAmount'] = bcadd($sum['discountAmount'], $line['discount'], 2);
            $sum['taxAmount'] = bcadd($sum['taxAmount'], $line['tax'], 2);
            $sum['grandTotal'] = bcadd($sum['grandTotal'], $line['total'], 2);
        }

        foreach (self::MONEY_FIELDS as $field) {
            $parent->set($field, $sum[$field]);
            $parent->set($field . 'Currency', $this->getCurrency());
        }

        $this->entityManager->saveEntity($parent, [self::OPTION_INTERNAL => true]);
    }
}
