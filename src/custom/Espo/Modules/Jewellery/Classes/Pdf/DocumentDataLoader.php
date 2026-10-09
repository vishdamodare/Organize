<?php

namespace Espo\Modules\Jewellery\Classes\Pdf;

use Espo\Core\Utils\Config;
use Espo\Modules\Jewellery\Tools\Totals;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\Tools\Pdf\Data\DataLoader;
use Espo\Tools\Pdf\Params;
use NumberFormatter;
use stdClass;

/**
 * Extra data for the Quotation / Order PDF templates: company letterhead,
 * customer block, and pre-formatted line items and totals.
 */
class DocumentDataLoader implements DataLoader
{
    public function __construct(
        private EntityManager $entityManager,
        private Config $config,
        private Totals $totals,
    ) {}

    public function load(Entity $entity, Params $params): stdClass
    {
        $isQuotation = $entity->getEntityType() === 'Quotation';
        $itemType = $isQuotation ? 'QuotationItem' : 'CustomerOrderItem';
        $attr = Totals::ITEM_PARENT[$itemType][1];

        $items = $this->entityManager->getRDBRepository($itemType)
            ->where([$attr => $entity->getId()])
            ->order('sortOrder')
            ->find();

        $itemList = [];
        $i = 0;

        foreach ($items as $item) {
            $itemList[] = [
                'no' => ++$i,
                'name' => $item->get('name'),
                'quantity' => rtrim(rtrim((string) $item->get('quantity'), '0'), '.'),
                'unitPrice' => $this->money($item->get('unitPrice')),
                'discountPercent' => (float) $item->get('discountPercent') ? rtrim(rtrim((string) $item->get('discountPercent'), '0'), '.') . '%' : '—',
                'taxPercent' => (float) $item->get('taxPercent') ? rtrim(rtrim((string) $item->get('taxPercent'), '0'), '.') . '%' : '—',
                'lineTotal' => $this->money($item->get('lineTotal')),
            ];
        }

        $account = $entity->get('accountId') ?
            $this->entityManager->getEntityById('Account', $entity->get('accountId')) : null;

        return (object) [
            'documentTitle' => $isQuotation ? 'QUOTATION' : 'ORDER CONFIRMATION',
            'itemList' => $itemList,
            'hasItems' => count($itemList) > 0,
            'subtotalFormatted' => $this->money($entity->get('subtotal')),
            'discountFormatted' => $this->money($entity->get('discountAmount')),
            'taxFormatted' => $this->money($entity->get('taxAmount')),
            'grandTotalFormatted' => $this->money($entity->get('grandTotal')),
            'currencyCode' => $this->totals->getCurrency(),
            'companyName' => $this->config->get('jewelleryCompanyName') ?: $this->config->get('applicationName'),
            'companyAddress' => $this->config->get('jewelleryCompanyAddress'),
            'companyPhone' => $this->config->get('jewelleryCompanyPhone'),
            'companyEmail' => $this->config->get('jewelleryCompanyEmail'),
            'companyGstin' => $this->config->get('jewelleryCompanyGstin'),
            'companyLogoId' => $this->config->get('companyLogoId'),
            'customerName' => $account?->get('name'),
            'customerContact' => $account?->get('contactPerson'),
            'customerPhone' => $account?->get('phoneNumber'),
            'customerEmail' => $account?->get('emailAddress'),
            'customerAddress' => $account ? implode(', ', array_filter([
                $account->get('billingAddressStreet'),
                $account->get('billingAddressCity'),
                $account->get('billingAddressState'),
                $account->get('billingAddressPostalCode'),
            ])) : null,
            'customerGstin' => $account?->get('gstin'),
            'companyAddressHtml' => $this->html($this->config->get('jewelleryCompanyAddress')),
            'termsHtml' => $isQuotation ? $this->html($entity->get('terms')) : null,
            'notesHtml' => $this->html($entity->get('description')),
        ];
    }

    /** Escaped text with line breaks kept, for triple-brace output in the template. */
    private function html(?string $text): ?string
    {
        return $text ? nl2br(htmlspecialchars($text, ENT_QUOTES)) : null;
    }

    /** Indian digit grouping (12,34,567.00) with the ISO code, e.g. "INR 12,34,567.00". */
    private function money(mixed $value): string
    {
        $formatter = new NumberFormatter('en_IN', NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 2);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 2);

        return $this->totals->getCurrency() . ' ' . $formatter->format((float) ($value ?? 0));
    }
}
