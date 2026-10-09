<?php

namespace Espo\Modules\Jewellery\Hooks\CustomerOrder;

use Espo\Core\Exceptions\Conflict;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Same rules as quotations, except: orders are never "converted", and an order
 * created from a quotation keeps that quotation's customer.
 */
class Guard extends \Espo\Modules\Jewellery\Hooks\Quotation\Guard
{
    protected string $itemType = 'CustomerOrderItem';
    protected string $itemAttr = 'customerOrderId';

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (!$entity->isNew() && $entity->get('quotationId') && $entity->isAttributeChanged('accountId')) {
            throw new Conflict('The customer of an order created from a quotation cannot be changed.');
        }

        parent::beforeSave($entity, $options);
    }

    protected function applyDefaults(Entity $entity): void
    {
        if (!$entity->get('name') && $entity->get('accountId')) {
            $account = $this->entityManager->getEntityById('Account', $entity->get('accountId'));
            $entity->set('name', 'Order for ' . ($account?->get('name') ?? 'customer'));
        }

        foreach (['subtotal', 'discountAmount', 'taxAmount', 'grandTotal'] as $field) {
            $entity->set($field, '0');
            $entity->set($field . 'Currency', $this->totals->getCurrency());
        }
    }

    protected function isConverted(Entity $entity): bool
    {
        return false;
    }
}
