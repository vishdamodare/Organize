<?php

namespace Espo\Modules\Jewellery\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\Jewellery\Tools\Conversion;

/** POST /Enquiry/:id/convertToDeal */
class PostConvertEnquiry implements Action
{
    public function __construct(private Conversion $conversion) {}

    public function process(Request $request): Response
    {
        $deal = $this->conversion->enquiryToDeal((string) $request->getRouteParam('id'));

        return ResponseComposer::json(['id' => $deal->getId(), 'name' => $deal->get('name')]);
    }
}
