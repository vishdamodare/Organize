<?php
/**
 * Configure a freshly installed EspoCRM instance as a Organize company workspace.
 * Idempotent: safe to re-run after module upgrades.
 *
 * Usage: php scripts/setup-company.php <instance-dir> "<Company Name>"
 */

if (PHP_SAPI !== 'cli' || $argc < 3) {
    fwrite(STDERR, "Usage: php setup-company.php <instance-dir> \"<Company Name>\"\n");
    exit(1);
}

[$_, $instanceDir, $companyName] = $argv;
$templateHtml = file_get_contents(__DIR__ . '/../src/templates/document.html');

chdir(realpath($instanceDir) ?: exit("Instance not found: $instanceDir\n"));
require 'bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
$config = $container->getByClass(Espo\Core\Utils\Config::class);
$writer = $container->getByClass(Espo\Core\InjectableFactory::class)->create(ConfigWriter::class);

$dashlet = fn(string $id, string $name, int $x, int $y, int $w, int $h) =>
    (object) ['id' => $id, 'name' => $name, 'x' => $x, 'y' => $y, 'width' => $w, 'height' => $h];

$settings = [
    // SaaS hardening: company admins cannot install extensions, upgrade, or change system paths.
    'restrictedMode' => true,
    'adminUpgradeDisabled' => true,
    'adminExtensionUpload' => false,

    'applicationName' => 'Organize',
    'jewelleryCompanyName' => $config->get('jewelleryCompanyName') ?: $companyName,
    'jewelleryDefaultTaxPercent' => $config->get('jewelleryDefaultTaxPercent') ?? 3,
    'jewelleryQuotationTerms' => $config->get('jewelleryQuotationTerms') ?? implode("\n", [
        '1. Prices are indicative and valid until the date shown; metal-linked prices may change with market rates.',
        '2. Final weight, purity and stone details are confirmed on the delivery invoice.',
        '3. Custom designs require an advance; delivery timelines are estimates.',
        '4. Taxes as applicable.',
    ]),

    'defaultCurrency' => 'INR',
    'baseCurrency' => 'INR',
    'currencyList' => ['INR'],
    'timeZone' => $config->get('timeZone') ?: 'Asia/Kolkata',
    'dateFormat' => 'DD/MM/YYYY',
    'timeFormat' => 'hh:mm A',
    'weekStart' => 1,
    'thousandSeparator' => ',',
    'decimalMark' => '.',

    'tabList' => [
        'Account', 'Enquiry', 'Opportunity', 'Task', 'JewelleryProduct', 'Quotation', 'CustomerOrder',
        'JewelleryReport',
        (object) ['type' => 'url', 'text' => 'Team', 'url' => '#User', 'iconClass' => 'fas fa-users', 'color' => null,
            'aclScope' => null, 'onlyAdmin' => true, 'id' => 'jw-team'],
        (object) ['type' => 'url', 'text' => 'Settings', 'url' => '#Admin/jewellerySettings', 'iconClass' => 'fas fa-cog',
            'color' => null, 'aclScope' => null, 'onlyAdmin' => true, 'id' => 'jw-settings'],
    ],
    'quickCreateList' => ['Account', 'Enquiry', 'Task', 'Quotation'],
    'globalSearchEntityList' => ['Account', 'Enquiry', 'Opportunity', 'Quotation', 'CustomerOrder', 'JewelleryProduct'],
    'calendarEntityList' => ['Task', 'Meeting', 'Call'],
    'activitiesEntityList' => ['Meeting', 'Call'],
    'historyEntityList' => ['Meeting', 'Call'],

    'dashboardLayout' => [(object) [
        'name' => 'Overview',
        'layout' => [
            $dashlet('jw-overview', 'JewelleryOverview', 0, 0, 4, 7),
            $dashlet('jw-tasks', 'Tasks', 0, 7, 2, 4),
            $dashlet('jw-stream', 'Stream', 2, 7, 2, 4),
        ],
    ]],
    'dashletsOptions' => (object) [
        'jw-overview' => (object) ['title' => 'Business Overview'],
        'jw-tasks' => (object) ['title' => 'My Follow-ups'],
        'jw-stream' => (object) ['title' => 'Recent Activity'],
    ],

    // Security
    'passwordStrengthLength' => 10,
    'passwordStrengthLetterCount' => 1,
    'passwordStrengthNumberCount' => 1,
    'authMaxFailedAttemptNumber' => 10,
    'authFailedAttemptsPeriod' => '60 seconds',
    'cleanupActionHistoryPeriod' => '6 months',
    'cleanupAuthLogPeriod' => '6 months',
];

$writer->setMultiple($settings);
$writer->save();
echo "Settings saved.\n";

// Salesperson role. Record-level rules: read company records, edit own, no delete,
// no import/export/mass update. Owners are EspoCRM admins (restricted mode).
$roleData = [
    'Account' => ['create' => 'yes', 'read' => 'all', 'edit' => 'own', 'delete' => 'no', 'stream' => 'all'],
    'Enquiry' => ['create' => 'yes', 'read' => 'all', 'edit' => 'own', 'delete' => 'no', 'stream' => 'all'],
    'Opportunity' => ['create' => 'yes', 'read' => 'all', 'edit' => 'own', 'delete' => 'no', 'stream' => 'all'],
    'Quotation' => ['create' => 'yes', 'read' => 'all', 'edit' => 'own', 'delete' => 'no', 'stream' => 'all'],
    'QuotationItem' => ['create' => 'yes', 'read' => 'all', 'edit' => 'all', 'delete' => 'all'],
    'CustomerOrder' => ['create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no', 'stream' => 'all'],
    'CustomerOrderItem' => ['create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no'],
    'JewelleryProduct' => ['create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no'],
    'Task' => ['create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'own'],
    'Call' => ['create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'own'],
    'Meeting' => ['create' => 'yes', 'read' => 'own', 'edit' => 'own', 'delete' => 'own'],
    'Template' => ['create' => 'no', 'read' => 'all', 'edit' => 'no', 'delete' => 'no'],
    'Calendar' => true,
    'JewelleryReport' => false,
    'Import' => false,
    'Contact' => false, 'Lead' => false, 'Case' => false, 'Document' => false, 'Campaign' => false,
    'KnowledgeBaseArticle' => false, 'TargetList' => false, 'Email' => false,
];

$role = $em->getRDBRepository('Role')->where(['name' => 'Salesperson'])->findOne() ?? $em->getNewEntity('Role');
$role->set([
    'name' => 'Salesperson',
    'data' => (object) $roleData,
    'fieldData' => (object) [],
    'assignmentPermission' => 'all',
    'userPermission' => 'all',
    'exportPermission' => 'no',
    'massUpdatePermission' => 'no',
    'dataPrivacyPermission' => 'no',
    'auditPermission' => 'no',
    'portalPermission' => 'no',
    'groupEmailAccountPermission' => 'no',
]);
$em->saveEntity($role);
echo "Role 'Salesperson' ready ({$role->getId()}).\n";

// PDF templates (native EspoCRM "Print to PDF").
foreach (['Quotation' => 'Quotation', 'CustomerOrder' => 'Order Confirmation'] as $entityType => $name) {
    $template = $em->getRDBRepository('Template')->where(['entityType' => $entityType, 'name' => $name])->findOne()
        ?? $em->getNewEntity('Template');

    $template->set([
        'name' => $name,
        'entityType' => $entityType,
        'status' => 'Active',
        'body' => $templateHtml,
        'header' => '',
        'footer' => '<p style="text-align:center;font-size:8pt;color:#777">Page {pageNumber}</p>',
        'printFooter' => true,
        'printHeader' => false,
        'pageFormat' => 'A4',
        'pageOrientation' => 'Portrait',
        'leftMargin' => 12, 'rightMargin' => 12, 'topMargin' => 12, 'bottomMargin' => 18,
        'filename' => '{{number}}',
    ]);
    $em->saveEntity($template);
}
echo "PDF templates ready.\n";

// Role/ACL changes are cached per user; drop the cache so they apply now.
$container->getByClass(Espo\Core\DataManager::class)->clearCache();
echo "Cache cleared.\n";
