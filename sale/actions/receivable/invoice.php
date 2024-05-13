<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use sale\accounting\invoice\Invoice;
use sale\accounting\invoice\InvoiceLine;
use sale\accounting\invoice\InvoiceLineGroup;
use sale\receivable\Receivable;

list($params, $providers) = announce([
    'description'   => 'Invoice given receivables.',
    'help'          => 'A default invoice can be selected, all receivables from that invoice\'s customer will be added to it.',
    'params'        => [
        'ids' =>  [
            'description'       => 'Identifier of the targeted reports.',
            'type'              => 'one2many',
            'foreign_object'    => 'sale\receivable\Receivable',
            'required'          => true
        ],

        'invoice_id' => [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\accounting\invoice\Invoice',
            'description'       => 'If left empty a new invoice proforma will be created.',
            'domain'            => ['status', '=', 'proforma'],
        ],

        'invoice_line_group_name' =>  [
            'description'       => 'Name of the invoice line group.',
            'type'              => 'string',
            'default'           => 'Additional Services ('.date('Y-m-d').')',
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context']
]);

/** @var \equal\php\Context $context */
$context = $providers['context'];

if(empty($params['ids'])) {
    throw new Exception('empty_ids', QN_ERROR_INVALID_PARAM);
}

$receivables = Receivable::search([
    ['id', 'in', $params['ids']],
    ['status', '=', 'pending']
])
    ->read([
        'id',
        'description',
        'customer_id',
        'product_id',
        'price_id',
        'unit_price',
        'vat_rate',
        'qty',
        'free_qty',
        'discount'
    ])
    ->get();

if(count($params['ids']) !== count($receivables)) {
    throw new Exception('unknown_receivable', QN_ERROR_UNKNOWN_OBJECT);
}

$default_invoice = null;
if(isset($params['invoice_id'])) {
    $default_invoice = Invoice::id($params['invoice_id'])
        ->read(['id', 'status', 'customer_id'])
        ->first();

    if(!isset($default_invoice)) {
        throw new Exception('unknown_invoice', QN_ERROR_UNKNOWN_OBJECT);
    }
    elseif($default_invoice['status'] !== 'proforma') {
        throw new Exception('invoice_must_be_proforma', QN_ERROR_INVALID_PARAM);
    }
}

foreach($receivables as $receivable) {
    $invoice = null;
    if(isset($default_invoice) && $receivable['customer_id'] === $default_invoice['customer_id']) {
        $invoice = $default_invoice;
    }
    else {
        $invoice = Invoice::search([
            ['customer_id', '=', $receivable['customer_id']],
            ['status', '=', 'proforma']
        ])
            ->read(['status'])
            ->first();

        if(!isset($invoice)) {
            $invoice = Invoice::create([
                'customer_id' => $receivable['customer_id']
            ])
                ->first();
        }
    }

    $invoice_line_group = InvoiceLineGroup::search([
        ['invoice_id', '=', $invoice['id']],
        ['name', '=', $params['invoice_line_group_name']]
    ])
        ->read(['id'])
        ->first();

    if(!isset($invoice_line_group)) {
        $invoice_line_group = InvoiceLineGroup::create([
            'invoice_id' => $invoice['id'],
            'name'       => $params['invoice_line_group_name']
        ])
            ->first();
    }

    $invoice_line = InvoiceLine::create([
        'description'           => $receivable['description'],
        'invoice_line_group_id' => $invoice_line_group['id'],
        'invoice_id'            => $invoice['id'],
        'product_id'            => $receivable['product_id'],
        'price_id'              => $receivable['price_id'],
        'unit_price'            => $receivable['unit_price'],
        'vat_rate'              => $receivable['vat_rate'],
        'qty'                   => $receivable['qty'],
        'free_qty'              => $receivable['free_qty'],
        'discount'              => $receivable['discount'],
        'receivable_id'         => $receivable['id']
    ])
        ->do('reset_invoice_prices')
        ->first();

    Receivable::id($receivable['id'])
        ->update([
            'invoice_id'      => $invoice['id'],
            'invoice_line_id' => $invoice_line['id'],
            'status'          => 'invoiced'
        ]);
}

$context->httpResponse()
        ->status(204)
        ->send();
