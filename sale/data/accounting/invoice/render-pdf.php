<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use core\setting\Setting;
use Dompdf\Dompdf;
use Dompdf\Options as DompdfOptions;
use sale\accounting\invoice\Invoice;

list($params, $providers) = eQual::announce([
    'description'   => 'Generate a pdf view of given invoice.',
    'params'        => [
        'id' => [
            'description' => 'Identifier of the targeted invoice.',
            'type'        => 'integer',
            'min'         => 1,
            'required'    => true
        ],
        'mode' => [
            'description' => 'Mode in which document has to be rendered: simple, grouped or detailed.',
            'help'        => 'Modes: "simple" displays all lines without groups, "detailed" displays all lines by group and "grouped" displays only groups by vat rate.',
            'type'        => 'string',
            'selection'   => ['simple', 'grouped', 'detailed'],
            'default'     => 'simple'
        ],
        'filename' => [
            'description' => 'Name given to the generated pdf file.',
            'type'        => 'string',
            'default'     => 'invoice'
        ],
        'lang' =>  [
            'description' => 'Language in which labels and multilang field have to be returned (2 letters ISO 639-1).',
            'type'        => 'string',
            'default'     => constant('DEFAULT_LANG')
        ],
        'debug' => [
            'type'        => 'boolean',
            'default'     => false
        ]
    ],
    'access'        => [
        'visibility' => 'protected',
        'groups'     => ['sale.default.users'],
    ],
    'response'      => [
        'accept-origin' => '*',
        'content-type'  => 'application/pdf'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

$invoice = Invoice::id($params['id'])
    ->read(['id'])
    ->first();

if(empty($invoice)) {
    throw new Exception('invoice_unknown', QN_ERROR_UNKNOWN_OBJECT);
}

$html = eQual::run('get', 'sale_accounting_invoice_render-html', [
    'id'      => $params['id'],
    'mode'    => $params['mode'],
    'view_id' => 'print.default',
    'lang'    => $params['lang'],
    'debug'   => $params['debug']
]);

$options = new DompdfOptions();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);

$dompdf->setPaper('A4');
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->render();

$page_label = Setting::get_value('sale', 'invoice', 'labels.pdf-page', 'p. {PAGE_NUM} / {PAGE_COUNT}', [], $params['lang']);

$canvas = $dompdf->getCanvas();
$font = $dompdf->getFontMetrics()->getFont('helvetica', 'regular');
$canvas->page_text(530, $canvas->get_height() - 35, $page_label, $font, 9);

$output = $dompdf->output();

$context->httpResponse()
        ->header('Content-Disposition', 'inline; filename="'.$params['filename'].'.pdf"')
        ->body($output)
        ->send();
