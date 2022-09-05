<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Consumption;
use lodging\sale\booking\Booking;
use lodging\sale\booking\BookingLine;
use lodging\sale\booking\BookingLineRentalUnitAssignement;
use lodging\realestate\RentalUnit;
use equal\orm\Domain;


list($params, $providers) = announce([
    'description'   => "Retrieve the list of available rental units for a given center, during a specific timerange.",
    'params'        => [
        'booking_line_id' =>  [
            'description'   => 'Specific line for which availability list is requested.',
            'type'          => 'integer'
        ],
        'domain' =>  [
            'description'   => 'Dommain for additional filtering.',
            'type'          => 'array',
            'default'       => []
        ],
    ],
    'access' => [
        'groups'            => ['booking.default.user']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'cron']
]);


list($context, $orm, $cron) = [$providers['context'], $providers['orm'], $providers['cron']];

$result = [];

// retrieve booking line data
$line = BookingLine::id($params['booking_line_id'])
    ->read([
        'product_id',
        'booking_id' => [
            'center_id'
        ],
        'booking_line_group_id' => [
            'date_from',
            'date_to',
            'time_from',
            'time_to'
        ]
    ])
    ->first();

if($line) {

    $date_from = $line['booking_line_group_id']['date_from'] + $line['booking_line_group_id']['time_from'];
    $date_to = $line['booking_line_group_id']['date_to'] + $line['booking_line_group_id']['time_to'];
    // retrieve available rental units based on schedule and product_id
    $rental_units_ids = Consumption::getAvailableRentalUnits($orm, $line['booking_id']['center_id'], $line['product_id'], $date_from, $date_to);

    // append rental units from own booking consumptions (use case: come and go between 'draft' and 'option', where units are already attached to consumptions)
    // #memo - this leads to an edge case: quote -> option -> quote, update nb_pers or time_from (list is not accurate and might return units that are not free)
    $booking = Booking::id($line['booking_id']['id'])->read(['consumptions_ids' => ['rental_unit_id']])->first();
    if($booking) {
        foreach($booking['consumptions_ids'] as $consumption) {
            $rental_units_ids[] = $consumption['rental_unit_id'];
        }
    }

    // remove units already assigned (to prevent providing wrong choices)
    if($params['booking_line_id']) {
        $assignments = BookingLineRentalUnitAssignement::search(['booking_id', '=', $line['booking_id']['id']])->read(['rental_unit_id', 'booking_line_id'])->get();
        $used_rental_units_ids = [];
        foreach($assignments as $assignment) {
            $used_rental_units_ids[] = $assignment['rental_unit_id'];
        }
        $rental_units_ids = array_diff($rental_units_ids, $used_rental_units_ids);
    }

    $rental_units = RentalUnit::ids($rental_units_ids)->read(['id', 'name', 'capacity'])->adapt('txt')->get(true);

    $domain = new Domain($params['domain']);

    // filter results
    foreach($rental_units as $index => $rental_unit) {
        if($domain->evaluate($rental_unit)) {
            $result[] = $rental_unit;
        }
    }
}

$context->httpResponse()
        ->body($result)
        ->send();