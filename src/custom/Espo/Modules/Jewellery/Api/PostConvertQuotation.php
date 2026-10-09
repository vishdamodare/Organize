<?php

namespace Espo\Modules\Jewellery\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\Jewellery\Tools\Conversion;

/** POST /Quotation/:id/convertToOrder */
class PostConvertQuotation implements Action
{
    public function __construct(private Conversion $conversion) {}

    public function process(Request $request): Response
    {
        $order = $this->conversion->quotationToOrder((string) $request->getRouteParam('id'));

        return ResponseComposer::json(['id' => $order->getId(), 'number' => $order->get('number')]);
    }
}
