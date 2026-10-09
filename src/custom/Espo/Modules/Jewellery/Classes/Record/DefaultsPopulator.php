<?php

namespace Espo\Modules\Jewellery\Classes\Record;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Acl;
use Espo\Core\Record\Defaults\DefaultPopulator;
use Espo\Core\Record\Defaults\Populator;
use Espo\Core\Utils\Config;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Server-side defaults for records created through the API or UI (runs before validation):
 *  - the creator becomes the assigned user when none is given, so "own" permissions work;
 *  - document dates default to today in the company's time zone;
 *  - a line item with a catalogue product takes the product name and indicative price when left blank.
 */
class DefaultsPopulator implements Populator
{
    private const TODAY_FIELDS = [
        'Enquiry' => 'enquiryDate',
        'Quotation' => 'dateIssued',
        'CustomerOrder' => 'orderDate',
    ];

    public function __construct(
        private DefaultPopulator $defaultPopulator,
        private User $user,
        private Config $config,
        private EntityManager $entityManager,
        private Acl $acl,
    ) {}

    public function populate(Entity $entity): void
    {
        $this->defaultPopulator->populate($entity);

        if ($entity->hasAttribute('assignedUserId') && !$entity->get('assignedUserId') && !$this->user->isSystem()) {
            $entity->set('assignedUserId', $this->user->getId());
        }

        if ($entity->hasAttribute('productId') && $entity->get('productId')) {
            $this->fillFromProduct($entity);
        }

        $field = self::TODAY_FIELDS[$entity->getEntityType()] ?? null;

        if ($field && !$entity->get($field)) {
            $today = new DateTimeImmutable('today', new DateTimeZone($this->config->get('timeZone') ?: 'UTC'));
            $entity->set($field, $today->format('Y-m-d'));

            if ($entity->getEntityType() === 'Quotation' && !$entity->get('validUntil')) {
                $entity->set('validUntil', $today->modify('+15 days')->format('Y-m-d'));
            }
        }
    }

    private function fillFromProduct(Entity $item): void
    {
        $product = $this->entityManager->getEntityById('JewelleryProduct', $item->get('productId'));

        if (!$product || !$this->acl->checkEntityRead($product)) {
            return; // LinkCheck rejects the save later with a proper error
        }

        if (!$item->get('name')) {
            $item->set('name', $product->get('name'));
        }

        if ($item->get('unitPrice') === null) {
            $item->set('unitPrice', $product->get('price'));
            $item->set('unitPriceCurrency', $product->get('priceCurrency'));
        }
    }
}
