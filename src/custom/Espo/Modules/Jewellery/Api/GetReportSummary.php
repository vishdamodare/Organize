<?php

namespace Espo\Modules\Jewellery\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\Jewellery\Tools\Reports;

/** GET /JewelleryReport/summary — Reports page; requires the "Reports" role permission. */
class GetReportSummary implements Action
{
    public function __construct(private Reports $reports, private Acl $acl) {}

    public function process(Request $request): Response
    {
        if (!$this->acl->checkScope('JewelleryReport')) {
            throw new Forbidden();
        }

        [$from, $to] = $this->reports->resolvePeriod(
            $request->getQueryParam('period'),
            $request->getQueryParam('from'),
            $request->getQueryParam('to')
        );

        return ResponseComposer::json($this->reports->summary($from, $to));
    }
}
