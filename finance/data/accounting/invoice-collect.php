<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2022
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/


use equal\orm\Domain;

list($params, $providers) = eQual::announce([
    'description'   => 'Advanced search for Invoices: returns a collection of Reports according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [

        'entity' =>  [
            'description'       => 'name',
            'type'              => 'string',
            'default'           => 'finance\accounting\Invoice'
        ],

        'price_min' => [
            'type'              => 'integer',
            'description'       => 'Minimal price for the invoice.'
        ],

        'price_max' => [
            'type'              => 'integer',
            'description'       => 'Maximal price for the invoice.'
        ],

        'date_from' => [
            'type'               => 'date',
            'description'        => "First date of the time interval.",
            'default'            => strtotime("-20 Years")
        ],

        'date_to' => [
            'type'              => 'date',
            'description'       => "Last date of the time interval.",
            'default'           => strtotime("+10 Years")
        ],

        'status' => [
            'type'              => 'string',
            'description'       => 'Version of the invoice.',
            'selection'         => ['all','proforma','invoice','cancelled'],
            'default'           => 'all'
        ]

    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => [ 'context', 'orm' ]
]);
/**
 * @var \equal\php\Context $context
 * @var \equal\orm\ObjectManager $orm
 */
list($context, $orm) = [ $providers['context'], $providers['orm'] ];

//   Add conditions to the domain to consider advanced parameters
$domain = $params['domain'];

if(isset($params['price_min']) && $params['price_min'] > 0) {
    $domain = Domain::conditionAdd($domain, ['price', '>=', $params['price_min']]);
}

if(isset($params['price_max']) && $params['price_max'] > 0) {
    $domain = Domain::conditionAdd($domain, ['price', '<=', $params['price_max']]);
}

if(isset($params['date_from']) && $params['date_from'] > 0) {
    $domain = Domain::conditionAdd($domain, ['emission_date', '>=', $params['date_from']]);
}

if(isset($params['date_to']) && $params['date_to'] > 0) {
    $domain = Domain::conditionAdd($domain, ['emission_date', '<=', $params['date_to']]);
}

if(isset($params['status']) && strlen($params['status']) > 0 && $params['status']!= 'all') {
    $domain = Domain::conditionAdd($domain, ['status', '=', $params['status']]);
}


$params['domain'] = $domain;
$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
