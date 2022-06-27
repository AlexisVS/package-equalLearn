<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use lodging\sale\pos\Order;
use lodging\sale\booking\BookingLineGroup;
use lodging\sale\booking\BookingLine;


list($params, $providers) = announce([
    'description'   => "This will mark the order as paid, and updated fundings and bookings involved in order lines, if any.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the order that has been paid.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ]
    ],
    'access' => [
        'groups'            => ['booking.default.user', 'pos.default.user'],
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth']     
]);

list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];

// read order object
$order = Order::id($params['id'])
                    ->read([
                        'id', 'name', 'status',                            
                        'order_payments_ids' => [
                            'order_lines_ids' => [
                                'has_funding','funding_id',
                                'product_id',
                                'qty',
                                'unit_price',
                                'vat_rate',
                                'discount',
                                'free_qty'
                            ],
                            'order_payment_parts_ids' => [
                                'payment_method', 'booking_id', 'voucher_ref'
                            ]
                        ]
                    ])
                    ->first();
                  
if(!$order) {
    throw new Exception("unknown_order", QN_ERROR_UNKNOWN_OBJECT);
}

// booking already cancelled
if($order['paid']) {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}

// handle products (lines) that must be added as extra on a booking

// update the funding_id related to the paymentPart, if any
// loop through order lines to check for payment method  voucher/booking_id if any
foreach($order['order_payments_ids'] as $pid => $payment) {
    // find out if the payment relates to a booking
    $booking_id = 0;
    foreach($payment['order_payment_parts_ids'] as $oid => $part) {
        if($part['payment_method'] == 'booking' && $part['booking_id'] > 0) {
            $booking_id = $part['booking_id'];
            break;
        }
    }
    if($booking_id) {
        /* 
            add lines as extra consumption on the targeted booking
        */

        // fetch the "extra" group id , (create if does not exist yet)
        $groups_ids = BookingLineGroup::search([['booking_id', '=', $booking_id], ['is_extra', '=', true]]);
        if($groups_ids > 0 && count($groups_ids)) {
            $group_id = reset(($groups_ids));
        }
        else {
            // create extra group
            $group_id = BookingLineGroup::create(['name' => 'Suppléments', 'booking_id' => $booking_id, 'is_extra' => true]);
        }

        // create booking lines according to order lines
        foreach($payment['order_lines_ids'] as $lid => $line) {
            $line_id = BookingLine::create(['booking_id' => $booking_id, 'booking_line_group_id' => $group_id, 'product_id' => $line['product_id']]);
            // #memo - at creation booking_line qty is always set accordingly to its parent group nb_pers
            BookingLine::id($line_id)->update(['qty' => $line['qty'], 'unit_price' => $line['unit_price'], 'vat_rate' => $line['vat_rate']]);
        }
    }
}

// close the order : set status to 'paid'
Order::id($params['id'])->update(['status' => 'paid']);

$context->httpResponse()
        ->status(200)
        ->body([])
        ->send();