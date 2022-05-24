<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\realestate\RentalUnit;
use lodging\sale\booking\Repairing;

list($params, $providers) = announce([
    'description'   => "Create an option from the planning, by providing date range, customer and rental unit.",
    'params'        => [

        'date_from' =>  [
            'description'   => 'Identifier of the targeted booking.',
            'type'          => 'date',
            'required'      => true
        ],

        'date_to' =>  [
            'description'   => 'Identifier of the targeted booking.',
            'type'          => 'date',
            'required'      => true
        ],

        'rental_unit_id' =>  [
            'description'   => 'Identifier of the targeted booking.',
            'type'          => 'integer',
            'required'      => true
        ],

        'description' =>  [
            'description'   => 'Short description about the reason of the maintenance.',
            'type'          => 'string',
            'default'       => ''
        ]


    ],
    'access' => [
        'visibility'        => 'protected',
        'groups'            => ['booking.default.user']
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'dispatch'] 
]);

/**
 * @var \equal\php\Context                  $context
 * @var \equal\dispatch\Dispatcher          $dispatch
 */
list($context, $dispatch) = [$providers['context'], $providers['dispatch']];


/* 
    Check consistency of parameters  
*/

// retrieve rental unit and related center
$rental_unit = RentalUnit::id($params['rental_unit_id'])
                  ->read(['id', 'name', 'capacity', 'center_id'])
                  ->first();
                  
if(!$rental_unit) {
    throw new Exception("unknown_booking", QN_ERROR_UNKNOWN_OBJECT);
}

/* 
    Create a repairing group for given period and add rental unit to it
*/

Repairing::create(['center_id' => $rental_unit['center_id'], 'description' => $params['description']])
         ->update([
            'rental_units_ids'  => [ $params['rental_unit_id'] ],
            'date_from'         => $params['date_from'],
            'date_to'           => $params['date_to']
         ]);

$context->httpResponse()
        ->status(204)
        ->send();