<?php

namespace Espo\Modules\Jewellery\Hooks\Quotation;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Core\Hook\Hook\BeforeRemove;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Config;
use Espo\Modules\Jewellery\Tools\Totals;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\RemoveOptions;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Quotation integrity rules:
 *  - linked enquiry/deal must belong to the same customer;
 *  - once converted to an order, customer and status are frozen and it can't be deleted;
 *  - default name and terms from company settings;
 *  - deleting a quotation deletes its line items (no orphans).
 * Reused by CustomerOrder (see Hooks/CustomerOrder/Guard.php).
 */
class Guard implements BeforeSave, BeforeRemove, AfterRemove
{
    protected string $itemType = 'QuotationItem';
    protected string $itemAttr = 'quotationId';

    public function __construct(
        protected EntityManager $entityManager,
        protected Config $config,
        protected Totals $totals,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(Totals::OPTION_INTERNAL)) {
            return;
        }

        $this->assertSameCustomer($entity, 'enquiryId', 'Enquiry');
        $this->assertSameCustomer($entity, 'opportunityId', 'Opportunity');

        if ($entity->isNew()) {
            $this->applyDefaults($entity);

            return;
        }

        if ($this->isConverted($entity)) {
            if ($entity->isAttributeChanged('accountId') || $entity->isAttributeChanged('status')) {
                throw new Conflict('This quotation has been converted to an order; its customer and status are locked.');
            }
        }
    }

    public function beforeRemove(Entity $entity, RemoveOptions $options): void
    {
        if ($this->isConverted($entity)) {
            throw new Conflict('A quotation that has been converted to an order cannot be deleted.');
        }
    }

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        $items = $this->entityManager
            ->getRDBRepository($this->itemType)
            ->where([$this->itemAttr => $entity->getId()])
            ->find();

        foreach ($items as $item) {
            $this->entityManager->removeEntity($item, [Totals::OPTION_INTERNAL => true]);
        }
    }

    protected function applyDefaults(Entity $entity): void
    {
        if (!$entity->get('name') && $entity->get('accountId')) {
            $account = $this->entityManager->getEntityById('Account', $entity->get('accountId'));
            $entity->set('name', 'Quotation for ' . ($account?->get('name') ?? 'customer'));
        }

        if ($entity->get('terms') === null || $entity->get('terms') === '') {
            $entity->set('terms', $this->config->get('jewelleryQuotationTerms'));
        }

        foreach (['subtotal', 'discountAmount', 'taxAmount', 'grandTotal'] as $field) {
            $entity->set($field, '0');
            $entity->set($field . 'Currency', $this->totals->getCurrency());
        }
    }

    protected function isConverted(Entity $entity): bool
    {
        return (bool) $this->entityManager
            ->getRDBRepository('CustomerOrder')
            ->where(['quotationId' => $entity->getId()])
            ->count();
    }

    /**
     * @throws BadRequest
     */
    protected function assertSameCustomer(Entity $entity, string $attr, string $entityType): void
    {
        $id = $entity->get($attr);

        if (!$id || (!$entity->isAttributeChanged($attr) && !$entity->isAttributeChanged('accountId'))) {
            return;
        }

        $related = $this->entityManager->getEntityById($entityType, $id);

        if (!$related || $related->get('accountId') !== $entity->get('accountId')) {
            throw new BadRequest("The linked $entityType belongs to a different customer.");
        }
    }
}
