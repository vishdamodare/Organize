<?php

namespace Espo\Modules\Jewellery\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\Jewellery\Tools\Reports;

/** GET /JewelleryReport/overview?period=today|week|month|year|custom&from=&to= — dashboard; every user, ACL-scoped. */
class GetOverview implements Action
{
    public function __construct(private Reports $reports) {}

    public function process(Request $request): Response
    {
        [$from, $to] = $this->reports->resolvePeriod(
            $request->getQueryParam('period'),
            $request->getQueryParam('from'),
            $request->getQueryParam('to')
        );

        return ResponseComposer::json($this->reports->overview($from, $to));
    }
}
