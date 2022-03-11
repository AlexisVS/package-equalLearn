<?php
/*
    This file is part of the eQual framework <http://www.github.com/cedricfrancoys/equal>
    Some Rights Reserved, Cedric Francoys, 2010-2021
    Licensed under GNU LGPL 3 license <http://www.gnu.org/licenses/>
*/
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use Twig\Environment as TwigEnvironment;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extension\ExtensionInterface;

use SepaQr\Data;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;

use lodging\sale\booking\Booking;
use lodging\sale\booking\Contract;
use sale\booking\Funding;
use communication\Template;
use communication\TemplatePart;
use communication\TemplateAttachment;
use equal\data\DataFormatter;


list($params, $providers) = announce([
    'description'   => "Returns a view populated with a collection of objects and outputs it as a PDF document.",
    'params'        => [
        'id' => [
            'description'   => 'Identitifier of the contract to print.',
            'type'          => 'integer',
            'required'      => true
        ],
        'view_id' =>  [
            'description'   => 'The identifier of the view <type.name>.',
            'type'          => 'string',
            'default'       => 'print.default'
        ],
        'mode' =>  [
            'description'   => 'Mode in which document has to be rendered: simple or detailed.',
            'type'          => 'string',
            'selection'     => ['simple', 'detailed'],
            'default'       => 'detailed'
        ],
        'lang' =>  [
            'description'   => 'Language in which labels and multilang field have to be returned (2 letters ISO 639-1).',
            'type'          => 'string',
            'default'       => DEFAULT_LANG
        ]
    ],
    'access' => [
        'visibility'        => 'public',		// 'public' (default) or 'private' (can be invoked by CLI only)	
        'groups'            => ['booking.default.administrator'],// list of groups ids or names granted 
    ],
    'response'      => [
        'content-type'      => 'application/pdf',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'orm']
]);


list($context, $orm) = [$providers['context'], $providers['orm']];

/*
    Retrieve the requested template
*/

$entity = 'lodging\sale\booking\Contract';
$parts = explode('\\', $entity);
$package = array_shift($parts);
$class_path = implode('/', $parts);
$parent = get_parent_class($entity);

$file = QN_BASEDIR."/packages/{$package}/views/{$class_path}.{$params['view_id']}.html";

if(!file_exists($file)) {
    throw new Exception("unknown_view_id", QN_ERROR_UNKNOWN_OBJECT);
}


$loader = new TwigFilesystemLoader(QN_BASEDIR."/packages/{$package}/views/");

$twig = new TwigEnvironment($loader);
/**  @var ExtensionInterface **/
$extension  = new IntlExtension();
$twig->addExtension($extension);

$twigTemplate = $twig->load("{$class_path}.{$params['view_id']}.html");

// read contract
$fields = [
    'created',
    'booking_id' => [
        'name',
        'modified',
        'date_from',
        'date_to',
        'price',
        'customer_id' => [
            'partner_identity_id' => [
                'id',
                'display_name',
                'type',
                'address_street', 'address_dispatch', 'address_city', 'address_zip',
                'type',
                'phone',
                'email',
                'has_vat',
                'vat_number'
            ]
        ],
        'center_id' => [
            'name',
            'manager_id' => ['name'],
            'address_street',
            'address_city',
            'address_zip',
            'phone',
            'email',
            'bank_account_iban',
            'bank_account_bic',
            'template_category_id',
            'use_office_details',
            'center_office_id' => [
                'code',
                'address_street',
                'address_city',
                'address_zip',
                'phone',
                'email',
                'signature',
                'bank_account_iban',
                'bank_account_bic'
            ],
            'organisation_id' => [
                'id',
                'legal_name',
                'address_street', 'address_zip', 'address_city',
                'email',
                'phone',
                'fax',
                'website',
                'registration_number',
                'signature',
                'bank_account_iban',
                'bank_account_bic'
            ]
        ],
        'contacts_ids' => [
            'type',
            'partner_identity_id' => [
                'display_name',
                'phone',
                'email',
                'title'
            ]
        ],
        'fundings_ids' => [
            'due_date', 'is_paid', 'due_amount',
            'payment_reference',
            'payment_deadline_id' => ['name']
        ]
    ],
    'contract_line_groups_ids' => [
        'name',
        'is_pack',
        'description',
        'contract_line_id' => [
            'name',
            'qty',
            'unit_price',
            'discount',
            'free_qty',
            'vat_rate',
            'total',
            'price'
        ],
        'contract_lines_ids' => [
            'name',
            'qty',
            'unit_price',
            'discount',
            'free_qty',
            'vat_rate',
            'total',
            'price'
        ]
    ],
    'price',
    'total'
];


$contract = Contract::id($params['id'])->read($fields)->first();

if(!$contract) {
    throw new Exception("unknown_contract", QN_ERROR_UNKNOWN_OBJECT);
}


/*
    extract required data and compose the $value map for the twig template
*/

$booking = $contract['booking_id'];


// set header image based on the organisation of the center
$img_path = 'public/assets/img/brand/Kaleo.png';
$img_url = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=';

if($booking['center_id']['organisation_id']['id'] == 2) {
    $img_path = 'public/assets/img/brand/Villers.png';
}
else if($booking['center_id']['organisation_id']['id'] == 3) {
    $img_path = 'public/assets/img/brand/Mozaik.png';
}

if(file_exists($img_path)) {
    $img = file_get_contents($img_path);
    $img_url = "data:image/png;base64, ".base64_encode($img);
}


$values = [
    'header_img_url'        => $img_url,
    'contract_header_html'  => '',
    'contract_notice_html'  => '',
    'customer_name'         => $booking['customer_id']['partner_identity_id']['display_name'],
    'contact_name'          => '',
    'contact_phone'         => $booking['customer_id']['partner_identity_id']['phone'],
    'contact_email'         => $booking['customer_id']['partner_identity_id']['email'],
    'customer_address1'     => $booking['customer_id']['partner_identity_id']['address_street'],
    'customer_address2'     => $booking['customer_id']['partner_identity_id']['address_zip'].' '.$booking['customer_id']['partner_identity_id']['address_city'],
    'customer_has_vat'      => (int) $booking['customer_id']['partner_identity_id']['has_vat'],
    'customer_vat'          => $booking['customer_id']['partner_identity_id']['vat_number'],
    'member'                => lodging_booking_print_contract_formatMember($booking),
    'date'                  => date('d/m/Y', $contract['created']),
    'code'                  => sprintf("%03d.%03d", intval($booking['name']) / 1000, intval($booking['name']) % 1000),
    'center'                => $booking['center_id']['name'],
    'center_address1'       => $booking['center_id']['address_street'],
    'center_address2'       => $booking['center_id']['address_zip'].' '.$booking['center_id']['address_city'],
    'center_contact1'       => (isset($booking['center_id']['manager_id']['name']))?$booking['center_id']['manager_id']['name']:'',
    'center_contact2'       => DataFormatter::format($booking['center_id']['phone'], 'phone').' - '.$booking['center_id']['email'],

    // by default, we use center contact details (overridden in case Center has a management Office, see below)
    'center_phone'          => DataFormatter::format($booking['center_id']['phone'], 'phone'),
    'center_email'          => $booking['center_id']['email'],
    'center_signature'      => $booking['center_id']['organisation_id']['signature'],

    'period'                => 'du '.date('d/m/Y', $booking['date_from']).' au '.date('d/m/Y', $booking['date_to']),

    'price'                 => $contract['price'],
    'total'                 => $contract['total'],

    'company_name'          => $booking['center_id']['organisation_id']['legal_name'],
    'company_address'       => sprintf("%s %s %s", $booking['center_id']['organisation_id']['address_street'], $booking['center_id']['organisation_id']['address_zip'], $booking['center_id']['organisation_id']['address_city']),
    'company_email'         => $booking['center_id']['organisation_id']['email'],
    'company_phone'         => DataFormatter::format($booking['center_id']['organisation_id']['phone'], 'phone'),
    'company_fax'           => DataFormatter::format($booking['center_id']['organisation_id']['fax'], 'phone'),
    'company_website'       => $booking['center_id']['organisation_id']['website'],
    'company_reg_number'    => $booking['center_id']['organisation_id']['registration_number'],

    // by default, we use organisation payment details (overridden in case Center has a management Office, see below)
    'company_iban'          => DataFormatter::format($booking['center_id']['organisation_id']['bank_account_iban'], 'iban'),
    'company_bic'           => DataFormatter::format($booking['center_id']['organisation_id']['bank_account_bic'], 'bic'),

    'installment_date'      => '',
    'installment_amount'    => 0,
    'installment_reference' => '',
    'installment_qr_url'    => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAoMBgDTD2qgAAAAASUVORK5CYII=',

    'fundings'              => [],

    'lines'                 => [],
    'tax_lines'             => []
];


/*
    override contact and payment details with center's office, if set
*/
if($booking['center_id']['use_office_details']) {
    $office = $booking['center_id']['center_office_id'];
    $values['company_iban'] = DataFormatter::format($office['bank_account_iban'], 'iban');
    $values['company_bic'] = DataFormatter::format($office['bank_account_bic'], 'bic');
    $values['center_phone'] = DataFormatter::format($office['phone'], 'phone');
    $values['center_email'] = $office['email'];
    $values['center_signature'] = $booking['center_id']['organisation_id']['signature'];    
}



/*
    retrieve templates
*/
if($booking['center_id']['template_category_id']) {

    $template = Template::search([
                            ['category_id', '=', $booking['center_id']['template_category_id']],
                            ['code', '=', 'contract'],
                            ['type', '=', 'contract']
                        ])
                        ->read(['parts_ids' => ['name', 'value']])
                        ->first();

    foreach($template['parts_ids'] as $part_id => $part) {
        if($part['name'] == 'header') {
            $values['contract_header_html'] = $part['value'].$values['center_signature'];
        }
        else if($part['name'] == 'notice') {
            $values['contract_notice_html'] = $part['value'];
        }
    }

}

/*
    feed lines
*/

$lines = [];

// all lines are in groups
foreach($contract['contract_line_groups_ids'] as $contract_line_group) {

    if($contract_line_group['is_pack']) {
        // group is a bundle with own price and VAT: add a single line with details
        $line = [
            'name'          => $contract_line_group['name'],
            'price'         => $contract_line_group['contract_line_id']['price'],
            'total'         => $contract_line_group['contract_line_id']['total'],
            'unit_price'    => $contract_line_group['contract_line_id']['unit_price'],
            'vat_rate'      => $contract_line_group['contract_line_id']['vat_rate'],
            'qty'           => $contract_line_group['contract_line_id']['qty'],
            'free_qty'      => $contract_line_group['contract_line_id']['free_qty'],
            'discount'      => $contract_line_group['contract_line_id']['discount'],
            'is_group'      => false
        ];
        $lines[] = $line;
    }
    else {
        // group is a pack with no own price: add a line with no price
        $line = [
            'name'          => $contract_line_group['name'],
            'price'         => null,
            'total'         => null,
            'unit_price'    => null,
            'vat_rate'      => null,
            'qty'           => null,
            'free_qty'      => null,
            'discount'      => null,
            'is_group'      => true
        ];
        $lines[] = $line;

        $group_lines = [];

        foreach($contract_line_group['contract_lines_ids'] as $contract_line) {

            $line = [
                'name'          => $contract_line['name'],
                'price'         => $contract_line['price'],
                'total'         => $contract_line['total'],
                'unit_price'    => $contract_line['unit_price'],
                'vat_rate'      => $contract_line['vat_rate'],
                'qty'           => $contract_line['qty'],
                'discount'      => $contract_line['discount'],
                'free_qty'      => $contract_line['free_qty'],
                'is_group'      => false
            ];

            $group_lines[] = $line;
        }

        if($params['mode'] == 'detailed') {
            foreach($group_lines as $line) {
                $lines[] = $line;
            }
        }
        // group lines by VAT rate        
        else {
            $group_tax_lines = [];
            foreach($group_lines as $line) {
                $vat_rate = strval($line['vat_rate']);
                if(!isset($group_tax_lines[$vat_rate])) {
                    $group_tax_lines[$vat_rate] = 0;
                }
                $group_tax_lines[$vat_rate] += $line['total'];
            }

            if(count(array_keys($group_tax_lines)) <= 1) {
                $pos = count($lines)-1;
                foreach($group_tax_lines as $vat_rate => $total) {
                    $lines[$pos]['qty'] = 1;
                    $lines[$pos]['vat_rate'] = $vat_rate;
                    $lines[$pos]['total'] = $total;
                    $lines[$pos]['price'] = $total * (1 + $vat_rate);
                }
            }
            else {
                foreach($group_tax_lines as $vat_rate => $total) {
                    $line = [
                        'name'      => 'Services avec TVA '.($vat_rate*100).'%',                        
                        'qty'       => 1,
                        'vat_rate'  => $vat_rate,
                        'total'     => $total,
                        'price'     => $total * (1 + $vat_rate)
                    ];
                    $lines[] = $line;
                }
            }
        }
    }
}


$values['lines'] = $lines;


/*
    retrieve final VAT and group by rate
*/
foreach($lines as $line) {
    $vat_rate = $line['vat_rate'];
    $tax_label = 'TVA '.strval( intval($vat_rate * 100) ).'%';
    $vat = $line['price'] - $line['total'];
    if(!isset($values['tax_lines'][$tax_label])) {
        $values['tax_lines'][$tax_label] = 0;
    }
    $values['tax_lines'][$tax_label] += $vat;
}


/*
    retrieve contact for booking
*/
foreach($booking['contacts_ids'] as $contact) {
    if(strlen($values['contact_name']) == 0 || $contact['type'] == 'booking') {
        // overwrite data of customer with contact info
        $values['contact_name'] = str_replace(["Dr", "Ms", "Mrs", "Mr","Pr"], ["Dr","Melle", "Mme","Mr","Pr"], $contact['partner_identity_id']['title']).' '.$contact['partner_identity_id']['name'];
        $values['contact_phone'] = $contact['partner_identity_id']['phone'];
        $values['contact_email'] = $contact['partner_identity_id']['email'];
    }
}

/*
    inject expected fundings and find the first installment
*/
$installment_date = PHP_INT_MAX;
$installment_amount = 0;
$installment_ref = '';

foreach($booking['fundings_ids'] as $funding) {

    if($funding['due_date'] < $installment_date) {
        $installment_date = $funding['due_date'];
        $installment_amount = $funding['due_amount'];
        $installment_ref = $funding['payment_reference'];
    }
    $line = [
        'name'          => $funding['payment_deadline_id']['name'],
        'due_date'      => date('d/m/Y', $funding['due_date']),
        'due_amount'    => $funding['due_amount'],
        'is_paid'       => $funding['is_paid'],
        'reference'     => $funding['payment_reference']
    ];
    $values['fundings'][] = $line;
}

// no funding found
if($installment_date == PHP_INT_MAX) {
    // set default delay to 20 days
    $installment_date = time() + (60 * 60 *24 * 20);
    // set default amount to 20%
    $installment_amount = $booking['price'] * 0.2;
    // set installment reference    ('+++xxx/+++' where xxx is 150 for initial installment)
    $installment_ref = Funding::get_payment_reference(150, $booking['name']);
}

$values['installment_date'] = date('d/m/Y', $installment_date);
$values['installment_amount'] = (float) $installment_amount;
$values['installment_reference'] = DataFormatter::format($installment_ref, 'scor');

// generate a QR code
try {
    $paymentData = Data::create()
        ->setServiceTag('BCD')
        ->setIdentification('SCT')
        ->setName($values['company_name'])
        ->setIban(str_replace(' ', '', $booking['center_id']['bank_account_iban']))
        ->setBic(str_replace(' ', '', $booking['center_id']['bank_account_bic']))
        ->setRemittanceReference($values['installment_reference'])
        ->setAmount($values['installment_amount']);

    $result = Builder::create()
        ->data($paymentData)
        ->errorCorrectionLevel(new ErrorCorrectionLevelMedium()) // required by EPC standard
        ->build();

    $dataUri = $result->getDataUri();
    $values['installment_qr_url'] = $dataUri;

}
catch(Exception $exception) {
    // unknown error
}

/*
    Inject all values into the template
*/
$html = $twigTemplate->render($values);



// instantiate and use the dompdf class
$options = new DompdfOptions();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$dompdf->setPaper('A4', 'portrait');
$dompdf->loadHtml((string) $html);
$dompdf->render();

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont("helvetica", "regular");
$canvas->page_text(530, $canvas->get_height() - 35, "p. {PAGE_NUM} / {PAGE_COUNT}", $font, 9, array(0,0,0));
// $canvas->page_text(40, $canvas->get_height() - 35, "Export", $font, 9, array(0,0,0));


// get generated PDF raw binary
$output = $dompdf->output();

$context->httpResponse()
        // ->header('Content-Disposition', 'attachment; filename="document.pdf"')
        ->header('Content-Disposition', 'inline; filename="document.pdf"')
        ->body($output)
        ->send();



function lodging_booking_print_contract_formatMember($booking) {
    $id = $booking['customer_id']['partner_identity_id']['id'];
    $code = ltrim(sprintf("%3d.%03d.%03d", intval($id) / 1000000, (intval($id) / 1000) % 1000, intval($id)% 1000), '0');
    return $code.' - '.$booking['customer_id']['partner_identity_id']['display_name'];
}