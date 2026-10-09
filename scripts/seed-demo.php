<?php
/**
 * DEMO / DEVELOPMENT ONLY. Fills a company instance with realistic sample jewellery data.
 * Refuses to run if the instance already has more than 5 customers.
 *
 * Usage: php scripts/seed-demo.php <instance-dir> <salesperson-password>
 */

if (PHP_SAPI !== 'cli' || $argc < 3) {
    fwrite(STDERR, "Usage: php seed-demo.php <instance-dir> <salesperson-password>\n");
    exit(1);
}

[$_, $instanceDir, $spPassword] = $argv;
chdir(realpath($instanceDir) ?: exit("Instance not found\n"));
require 'bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\PasswordHash;
use Espo\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();
$c = $app->getContainer();
/** @var EntityManager $em */
$em = $c->getByClass(EntityManager::class);
$factory = $c->getByClass(Espo\Core\InjectableFactory::class);
$hash = $factory->create(PasswordHash::class);

if ($em->getRDBRepository('Account')->count() > 5) {
    exit("Refusing: this instance already has customers. Demo data is for empty demo instances only.\n");
}

mt_srand(42);
$tz = new DateTimeZone('Asia/Kolkata');
$day = fn(int $offset) => (new DateTimeImmutable("today $offset days", $tz))->format('Y-m-d');
$at = fn(int $offsetHours) => gmdate('Y-m-d H:i:s', time() + $offsetHours * 3600);
$pick = fn(array $a) => $a[mt_rand(0, count($a) - 1)];
$save = function (string $type, array $data) use ($em) {
    $e = $em->getNewEntity($type);
    $e->set($data);
    $em->saveEntity($e);

    return $e;
};

$owner = $em->getRDBRepository('User')->where(['type' => 'admin'])->findOne();
$role = $em->getRDBRepository('Role')->where(['name' => 'Salesperson'])->findOne();
$users = [$owner];

foreach ([['priya', 'Priya', 'Sharma'], ['arjun', 'Arjun', 'Mehta']] as [$u, $f, $l]) {
    $user = $em->getRDBRepository('User')->where(['userName' => $u])->findOne() ?? $save('User', [
        'userName' => $u, 'firstName' => $f, 'lastName' => $l, 'type' => 'regular', 'isActive' => true,
        'password' => $hash->hash($spPassword), 'emailAddress' => "$u@example.com",
    ]);
    $em->getRDBRepository('User')->getRelation($user, 'roles')->relate($role);
    $users[] = $user;
}

$products = [];
foreach ([
    ['22K Kundan Bridal Necklace Set', 'BR-KN-101', 'Bridal Jewellery', 'Gold', '22K', '285000'],
    ['18K Diamond Solitaire Ring', 'DM-SR-204', 'Diamond Jewellery', 'White Gold', '18K', '145000'],
    ['22K Temple Jhumka Earrings', 'GJ-JH-033', 'Gold Jewellery', 'Gold', '22K', '62000'],
    ['925 Silver Oxidised Anklet (pair)', 'SV-AN-012', 'Silver Jewellery', 'Silver', '925 Sterling', '3800'],
    ['Natural Burmese Ruby (per carat)', 'GS-RB-007', 'Gemstones', '', '', '42000'],
    ['22K Gold Bangle (per gram)', 'GJ-BG-001', 'Gold Jewellery', 'Gold', '22K', '6650'],
    ['18K Rose Gold Diamond Pendant', 'DM-PD-118', 'Diamond Jewellery', 'Rose Gold', '18K', '58000'],
    ['Polki Choker - Custom Design', 'CD-PL-501', 'Custom Design', 'Gold', '22K', '320000'],
] as [$name, $sku, $cat, $metal, $purity, $price]) {
    $products[] = $save('JewelleryProduct', ['name' => $name, 'sku' => $sku, 'category' => $cat, 'metalType' => $metal,
        'purity' => $purity, 'price' => $price, 'priceCurrency' => 'INR', 'status' => 'Active',
        'unit' => str_contains($name, 'per gram') ? 'Gram' : (str_contains($name, 'per carat') ? 'Carat' : 'Piece')]);
}

$customers = [];
foreach ([
    ['Shree Ganesh Jewellers', 'Retail Jewellery Shop', 'Ramesh Soni', 'Mumbai', 'Maharashtra', '27AAPFU0939F1ZV'],
    ['Kalyan Gems & Co.', 'Wholesaler', 'Suresh Kalyan', 'Jaipur', 'Rajasthan', '08AABCK1234L1Z5'],
    ['Anita Desai', 'Individual Buyer', '', 'Pune', 'Maharashtra', ''],
    ['Rajesh Agarwal', 'Individual Buyer', '', 'Delhi', 'Delhi', ''],
    ['Surat Diamond Works', 'Manufacturer', 'Hitesh Patel', 'Surat', 'Gujarat', '24AAACS5678P1Z2'],
    ['Lakshmi Gold Palace', 'Retail Jewellery Shop', 'Venkat Rao', 'Hyderabad', 'Telangana', '36AAFCL4321M1Z9'],
    ['Meera Iyer', 'Individual Buyer', '', 'Chennai', 'Tamil Nadu', ''],
    ['Bombay Bullion Traders', 'Supplier', 'Imran Shaikh', 'Mumbai', 'Maharashtra', '27AAHCB9876Q1Z3'],
    ['Pooja & Vikram Kapoor', 'Individual Buyer', '', 'Chandigarh', 'Punjab', ''],
    ['Heritage Silver House', 'Wholesaler', 'Neha Jain', 'Kolkata', 'West Bengal', '19AAJCH2468R1Z1'],
    ['Corporate Gifts India', 'Other Business', 'Amit Verma', 'Bengaluru', 'Karnataka', '29AAECC1357S1Z6'],
    ['Farah Khan', 'Individual Buyer', '', 'Lucknow', 'Uttar Pradesh', ''],
] as $i => [$name, $type, $contact, $city, $state, $gstin]) {
    $customers[] = $save('Account', [
        'name' => $name, 'type' => $type, 'contactPerson' => $contact ?: null,
        'phoneNumber' => '+9198' . str_pad((string) (20000000 + $i * 731), 8, '0', STR_PAD_LEFT),
        'emailAddress' => strtolower(trim(preg_replace('/[^a-z]+/i', '.', $name), '.')) . '@example.com',
        'billingAddressCity' => $city, 'billingAddressState' => $state, 'billingAddressCountry' => 'India',
        'gstin' => $gstin ?: null, 'customerSource' => $pick(['Walk-in', 'Referral', 'Exhibition', 'WhatsApp', 'Website']),
        'customerStatus' => $i % 5 === 4 ? 'Prospect' : 'Active', 'preferredContactMethod' => $pick(['Phone', 'WhatsApp', 'Email']),
        'tags' => $type === 'Individual Buyer' ? ['Bridal'] : ($i < 2 ? ['VIP', 'Repeat Buyer'] : []),
        'assignedUserId' => $users[$i % 3]->getId(),
    ]);
}

$enquiryPlan = [
    ['Bridal necklace set for December wedding', 2, 'Bridal Jewellery', 'Gold', '22K', 300000, 'In Progress'],
    ['Solitaire engagement ring, 1 ct', 3, 'Diamond Jewellery', 'White Gold', '18K', 180000, 'Contacted'],
    ['Bulk order: 50 silver anklets', 9, 'Silver Jewellery', 'Silver', '925 Sterling', 175000, 'New'],
    ['Ruby set for anniversary', 6, 'Gemstones', '', '', 120000, 'New'],
    ['Restock temple jhumkas (20 pairs)', 0, 'Gold Jewellery', 'Gold', '22K', 1100000, 'Contacted'],
    ['Corporate Diwali gifts - 100 pendants', 10, 'Diamond Jewellery', 'Rose Gold', '18K', 2500000, 'In Progress'],
    ['Custom polki choker', 8, 'Custom Design', 'Gold', '22K', 350000, 'New'],
    ['Gold bangles 40 g', 11, 'Gold Jewellery', 'Gold', '22K', 270000, 'New'],
    ['Diamond pendants for store display', 5, 'Diamond Jewellery', 'Rose Gold', '18K', 600000, 'In Progress'],
];

$deals = [];
foreach ($enquiryPlan as $i => [$title, $ci, $cat, $metal, $purity, $budget, $status]) {
    $enq = $save('Enquiry', [
        'name' => $title, 'accountId' => $customers[$ci]->getId(), 'enquiryDate' => $day(-mt_rand(0, 25)), 'category' => $cat,
        'metalType' => $metal, 'purity' => $purity, 'budget' => (string) $budget, 'budgetCurrency' => 'INR',
        'source' => $pick(['Walk-in', 'Phone', 'WhatsApp', 'Referral', 'Exhibition']), 'status' => $status,
        'priority' => $pick(['Normal', 'Normal', 'High']), 'quantity' => 1, 'nextFollowUpDate' => $day(mt_rand(-2, 7)),
        'expectedPurchaseDate' => $day(mt_rand(10, 60)), 'assignedUserId' => $customers[$ci]->get('assignedUserId'),
    ]);

    if ($i < 6) {
        $stage = ['Qualified', 'Quotation Sent', 'Negotiation', 'Contacted', 'Closed Won', 'Closed Lost'][$i];
        $deal = $save('Opportunity', [
            'name' => $title, 'accountId' => $enq->get('accountId'), 'enquiryId' => $enq->getId(), 'stage' => $stage,
            'amount' => (string) $budget, 'amountCurrency' => 'INR', 'closeDate' => $day(mt_rand(5, 45)),
            'lostReason' => $stage === 'Closed Lost' ? 'Price too high' : '', 'assignedUserId' => $enq->get('assignedUserId'),
        ]);
        $enq->set('status', 'Converted');
        $em->saveEntity($enq);
        $deals[] = $deal;
    }
}

// Quotations: one accepted & converted to an order, one sent, one draft.
$ownerAcl = $c->getByClass(Espo\Core\AclManager::class)->createUserAcl($owner);
$conversion = $factory->createWith(Espo\Modules\Jewellery\Tools\Conversion::class, ['user' => $owner, 'acl' => $ownerAcl]);

foreach ([[0, 'Accepted', [[0, 1, 0, 3]]], [1, 'Sent', [[1, 1, 2, 3]]], [4, 'Draft', [[2, 20, 3, 3]]]] as [$di, $status, $lines]) {
    $deal = $deals[$di];
    $q = $save('Quotation', ['accountId' => $deal->get('accountId'), 'opportunityId' => $deal->getId(),
        'enquiryId' => $deal->get('enquiryId'), 'status' => 'Draft', 'dateIssued' => $day(-3), 'validUntil' => $day(12),
        'assignedUserId' => $deal->get('assignedUserId')]);

    foreach ($lines as $n => [$prodIdx, $qty, $disc, $tax]) {
        $p = $products[$prodIdx];
        $save('QuotationItem', ['quotationId' => $q->getId(), 'productId' => $p->getId(), 'name' => $p->get('name'),
            'quantity' => (string) $qty, 'unitPrice' => $p->get('price'), 'discountPercent' => (string) $disc,
            'taxPercent' => (string) $tax, 'sortOrder' => $n]);
    }

    $q = $em->getEntityById('Quotation', $q->getId());
    $q->set('status', $status);
    $em->saveEntity($q);

    if ($status === 'Accepted') {
        $order = $conversion->quotationToOrder($q->getId());
        $order->set(['status' => 'Confirmed', 'expectedDeliveryDate' => $day(20)]);
        $em->saveEntity($order);
    }
}

// Follow-ups: overdue, due today, upcoming, completed.
foreach ([
    ['Call Anita about necklace design options', 2, -26, 'Not Started', 'Phone Call'],
    ['Share solitaire certificate (GIA) copy', 3, 3, 'Not Started', 'Customer Follow-up'],
    ['Visit Shree Ganesh for restock order', 0, 26, 'Not Started', 'Meeting'],
    ['Follow up on sent quotation', 3, 50, 'Not Started', 'Quotation Follow-up'],
    ['Confirm silver anklet sizes', 9, -50, 'Completed', 'Phone Call'],
    ['Send ruby set photos on WhatsApp', 6, 5, 'Started', 'Customer Follow-up'],
] as [$name, $ci, $hours, $status, $type]) {
    $save('Task', ['name' => $name, 'taskType' => $type, 'status' => $status, 'priority' => 'Normal',
        'parentType' => 'Account', 'parentId' => $customers[$ci]->getId(), 'dateEnd' => $at($hours),
        'assignedUserId' => $customers[$ci]->get('assignedUserId')]);
}

echo "Demo data created: " . count($customers) . " customers, " . count($products) . " products, " .
    count($enquiryPlan) . " enquiries, " . count($deals) . " deals, 3 quotations, 1 order, 6 tasks.\n";
echo "Salesperson logins: priya / arjun (password as given).\n";
