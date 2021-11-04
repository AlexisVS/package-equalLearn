<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\BookingLine;
use lodging\sale\booking\Booking;

use core\Task;
use core\SettingValue;

list($params, $providers) = announce([
    'description'   => "Update the status of given booking to 'option'. Related consumptions are added to the planning. Auto-deprecation of the option is scheduled according to setting `sale.booking.option.validity`.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the targeted booking.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ]
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth'] 
]);


list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];


/*
    Check if rental_units assigned to the booking are still available at given dates (otherwise, generate an error).

    We perform a 2-pass operation:
        1. First, we create the consumptions
        2. Second, we try to detect an overbooking for the current booking (based on booking_id)
*/

// read booking object
$booking = Booking::id($params['id'])
                  ->read([
                      'status',
                      'booking_lines_ids'
                   ])
                  ->first();

if(!$booking) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

if($booking['status'] != 'quote') {
    throw new Exception("incompatible_status", QN_ERROR_INVALID_PARAM);
}

if(!count($booking['booking_lines_ids'])) {
    throw new Exception("empty_booking", QN_ERROR_MISSING_PARAM);
}


// check overbooking

$json = run('get', 'lodging_booking_check-overbooking', ['id' => $params['id']]);
$data = json_decode($json, true);
if(isset($data['errors'])) {

    // rollback - remove the created consumptions
    foreach($booking['booking_lines_ids'] as $lid) {
        $line = BookingLine::id($lid)->read(['consumptions_ids'])->first();
        $consumptions_ids = array_map(function($a) { return "-$a";}, $line['consumptions_ids']);
        BookingLine::id($lid)->update(['consumptions_ids' => $consumptions_ids]);
    }

    // raise an exception with returned error code
    foreach($data['errors'] as $name => $message) {
        throw new Exception($message, qn_error_code($name));
    }
}
else if(is_array($data) && count($data)) {
    // raise an exception with overbooking_detected)
    throw new Exception('overbooking_detected', QN_ERROR_CONFLICT_OBJECT);
}


/*
    Create the consumptions in order to see them in the planning (scheduled services) and to reserve related rental units.
*/

BookingLine::_createConsumptions($orm, $booking['booking_lines_ids'], DEFAULT_LANG);


/*
    Update booking status
*/

Booking::id($params['id'])->update(['status' => 'option']);

/*
    Setup a scheduled job to set back the booking to a quote according to delay set by Setting `option.validity`
*/

$limit = 10;    // default value
$setting = SettingValue::search(['name', '=', 'option.validity'])->read(['value'])->first();

if($setting) {
    $limit = $setting['value'];
}

// add a task to the CRON
Task::create([
    'name'          => "Booking {$params['id']} - option deprecation",
    'moment'        => time() + $limit * 86400,
    'controller'    => 'lodging_booking_quote',
    'params'        => '{"id": '.$params['id'].'}'
]);


$context->httpResponse()
        // ->status(204)
        ->status(200)
        ->body([])
        ->send();