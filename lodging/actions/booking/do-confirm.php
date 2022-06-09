<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\sale\booking\Booking;
use sale\pay\PaymentPlan;
use lodging\sale\booking\BookingLine;
use lodging\sale\booking\Contract;
use lodging\sale\booking\ContractLine;
use lodging\sale\booking\ContractLineGroup;
use lodging\sale\booking\Funding;


list($params, $providers) = announce([
    'description'   => "Sets booking as confirmed, create contract and generate payment plan.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the booking to mark as confirmed.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ],
    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'cron', 'dispatch']
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\orm\ObjectManager            $orm
 * @var \equal\cron\Scheduler               $cron
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $orm, $cron, $dispatch) = [$providers['context'], $providers['orm'], $providers['cron'], $providers['dispatch']];

// read booking object
$booking = Booking::id($params['id'])
                  ->read([
                        'status',
                        'is_price_tbc',
                        'type_id',
                        'date_from',
                        'date_to',
                        'price',                                  // total price VAT incl.
                        'contracts_ids',
                        'center_id' => ['center_office_id'],
                        'customer_id' => ['id', 'rate_class_id'],
                        'booking_lines_groups_ids' => [
                            'name',
                            'date_from',
                            'date_to',
                            'has_pack',
                            'is_locked',
                            'pack_id' => ['id', 'display_name'],
                            'vat_rate',
                            'unit_price',
                            'fare_benefit',
                            'rate_class_id',
                            'qty',
                            'nb_nights',
                            'nb_pers',
                            'booking_lines_ids' => [
                                'product_id',
                                'unit_price',
                                'vat_rate',
                                'qty',
                                'price_adapters_ids' => ['type', 'value', 'is_manual_discount']
                            ]
                        ]
                  ])
                  ->first();

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

if($booking['status'] != 'option') {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}

if($booking['is_price_tbc']) {
    throw new Exception("unconfirmed_price", QN_ERROR_INVALID_PARAM);
}

// remove any existing CRON tasks for reverting the booking to quote
$cron->cancel("booking.option.deprecation.{$params['id']}");


/*
    Generate the contract
*/

// remember all booking lines involved
$booking_lines_ids = [];

// #memo - generated contracts are kept for history (we never delete them)
// mark existing contracts as expired    
Contract::ids($booking['contracts_ids'])->update(['status' => 'cancelled']);


// create contract and contract lines
$contract = Contract::create([
        'date'          => time(),
        'booking_id'    => $params['id'],
        'status'        => 'pending',
        'valid_until'   => time() + (30 * 86400),
        'customer_id'   => $booking['customer_id']['id']
    ])
    ->first();

foreach($booking['booking_lines_groups_ids'] as $group_id => $group) {
    $group_label = $group['name'].' : ';

    if($group['date_from'] == $group['date_to']) {
        $group_label .= date('d/m/y', $group['date_from']);
    }
    else {
        $group_label .= date('d/m/y', $group['date_from']).' - '.date('d/m/y', $group['date_to']);
    }

    $group_label .= ' - '.$group['nb_pers'].' p.';

    if($group['has_pack'] && $group['is_locked'] ) {
        // create a contract group based on the booking group

        $contract_line_group = ContractLineGroup::create([
            'name'              => $group_label,
            'is_pack'           => true,
            'contract_id'       => $contract['id'],
            'fare_benefit'      => $group['fare_benefit'],
            'rate_class_id'     => $group['rate_class_id']
        ])->first();

        // create a line based on the group
        $c_line = [
            'contract_id'               => $contract['id'],
            'contract_line_group_id'    => $contract_line_group['id'],
            'product_id'                => $group['pack_id']['id'],
            'vat_rate'                  => $group['vat_rate'],
            'unit_price'                => $group['unit_price'],
            'qty'                       => $group['qty']
        ];

        $contract_line = ContractLine::create($c_line)->first();
        ContractLineGroup::ids($contract_line_group['id'])->update([ 'contract_line_id' => $contract_line['id'] ]);
    }
    else {
        $contract_line_group = ContractLineGroup::create([
            'name'              => $group_label,
            'is_pack'           => false,
            'contract_id'       => $contract['id'],
            'fare_benefit'      => $group['fare_benefit'],
            'rate_class_id'     => $group['rate_class_id']
        ])->first();
    }

    // create as many lines as the group booking_lines
    foreach($group['booking_lines_ids'] as $lid => $line) {
        $booking_lines_ids[] = $lid;

        $c_line = [
            'contract_id'               => $contract['id'],
            'contract_line_group_id'    => $contract_line_group['id'],
            'product_id'                => $line['product_id'],
            'vat_rate'                  => $line['vat_rate'],
            'unit_price'                => $line['unit_price'],
            'qty'                       => $line['qty']
        ];

        $disc_value = 0;
        $disc_percent = 0;
        $free_qty = 0;
        foreach($line['price_adapters_ids'] as $aid => $adata) {
            if($adata['is_manual_discount']) {
                if($adata['type'] == 'amount') {
                    $disc_value += $adata['value'];
                }
                else if($adata['type'] == 'percent') {
                    $disc_percent += $adata['value'];
                }
                else if($adata['type'] == 'freebie') {
                    $free_qty += $adata['value'];
                }
            }
            // auto granted freebies are displayed as manual discounts
            else {
                if($adata['type'] == 'freebie') {
                    $free_qty += $adata['value'];
                }
            }
        }
        // convert discount value to a percentage
        $disc_value = $disc_value / (1 + $line['vat_rate']);
        $price = $line['unit_price'] * $line['qty'];
        $disc_value_perc = ($price) ? ($price - $disc_value) / $price : 0;
        $disc_percent += (1-$disc_value_perc);

        $c_line['free_qty'] = $free_qty;
        $c_line['discount'] = $disc_percent;
        ContractLine::create($c_line);
    }

}

// mark all booking lines as contractual
BookingLine::ids($booking_lines_ids)->update(['is_contractual' => true]);

// update booking status
Booking::id($params['id'])->update(['status' => 'confirmed']);

// remove messages about readyness for this booking, if any
$dispatch->cancel('lodging.booking.ready', 'lodging\sale\booking\Booking', $params['id']);


/*
    Pre-fill composition with customer details as first line (ease for single booking)
*/
try {
    eQual::run('do', 'lodging_composition_generate', ['booking_id' => $params['id']]);
}
catch(Exception $e) {
    // ignore errors at this stage
}


/*
    Genarate the payment plan (expected fundings of the booking)
*/

// set rate class default to 'general public'

$rate_class_id = 4;

if($booking['customer_id']['rate_class_id']) {
    $rate_class_id = $booking['customer_id']['rate_class_id'];
}


// retrieve existing payment plans
$payment_plans = PaymentPlan::search([])->read(['rate_class_id', 'booking_type_id', 'payment_deadlines_ids' => ['delay_from_event','delay_from_event_offset','delay_count','type','is_balance_invoice','amount_share']])->get();

if(!$payment_plans) {
    throw new Exception("missing_payment_plan", QN_ERROR_INVALID_CONFIG);
}

$payment_plan = -1;
// payment plan assignment is based on booking type and customer's rate class
foreach($payment_plans as $pid => $plan) {
    // double match: keep plan and stop
    if($plan['rate_class_id'] == $rate_class_id && $plan['booking_type_id'] == $booking['type_id'] ) {        
        $payment_plan = $plan;
        break;
    }
    // either rate_class or booking_type, keep plan (will be discarded if better match is found)
    if($plan['rate_class_id'] == $rate_class_id || $plan['booking_type_id'] == $booking['type_id'] ) {    
        if($payment_plan < 0) {
            $payment_plan = $plan;
        }
    }
}

if($payment_plan < 0) {
    throw new Exception("cannot_read_object", QN_ERROR_UNKNOWN_OBJECT);
}

$funding_order = 0;
foreach($payment_plan['payment_deadlines_ids'] as $deadline_id => $deadline) {

    // special case: immediate creation of balance invoice with no funding
    if($deadline['type'] == 'invoice' && $deadline['is_balance_invoice']) {
        // create balance invoice and do not create funding (raise Exception on failure)
        eQual::run('do', 'lodging_invoice_generate', ['id' => $params['id']]);
        break;
    }

    $funding = [
        'payment_deadline_id'   => $deadline_id,
        'booking_id'            => $params['id'],
        'center_office_id'      => $booking['center_id']['center_office_id'],
        'due_amount'            => round($booking['price'] * $deadline['amount_share'], 2),
        'amount_share'          => $deadline['amount_share'],
        'is_paid'               => false,
        'type'                  => 'installment',
        'order'                 => $funding_order
    ];

    $date = time();         // default delay is starting today (at confirmation time / equivalent to 'booking')
    switch($deadline['delay_from_event']) {
        case 'booking':
            $date = time();
            break;
        case 'checkin':
            $date = $booking['date_from'];
            break;
        case 'checkout':
            $date = $booking['date_to'];
            break;
    }
    $funding['issue_date'] = $date + $deadline['delay_from_event_offset'];
    $funding['due_date'] = min($booking['date_from'], $date + $deadline['delay_from_event_offset'] + ($deadline['delay_count'] * 86400));

    // request funding creation
    try {
        Funding::create($funding)->read(['name'])->get();
    }
    catch(Exception $e) {
        // ignore duplicates (not created)
    }

    ++$funding_order;
}

$context->httpResponse()
        // ->status(204)
        ->status(200)
        ->body([])
        ->send();