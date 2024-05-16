<?php
/*
    This file is part of the Discope property management software.
    Author: Yesbabylon SRL, 2020-2024
    License: GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;

list($params, $providers) = eQual::announce([
    'description'   => 'Advanced search for Subscription Entry: returns a collection of Subscription Entries according to extra parameters.',
    'extends'       => 'core_model_collect',
    'params'        => [

        'entity' =>  [
            'type'              => 'string',
            'description'       => 'Full name (including namespace) of the class to return.',
            'default'           => 'sale\SaleEntry'
        ],

        'customer_id'=> [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\customer\Customer',
            'description'       => 'The Customer to who refers the item.',
            'min'               => 1
        ],

        'product_id'=> [
            'type'              => 'many2one',
            'foreign_object'    => 'sale\catalog\Product',
            'description'       => 'Product of the catalog sale.',
            'min'               => 1
        ],

        'is_billable' => [
            'type'              => 'boolean',
            'description'       => 'Can be billed to the customer.',
            'default'           => null
        ],

        'has_receivable' => [
            'type'              => 'boolean',
            'description'       => 'The entry is linked to a receivable entry.',
            'default'           => null
        ],

        'date_from' => [
            'type'              => 'date',
            'description'       => 'Last date of the time interval.',
            'default'           => strtotime('-20 Years')
        ],

        'date_to' => [
            'type'              => 'date',
            'description'       => 'First date of the time interval.',
            'default'           => strtotime('+10 Years')
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

$domain = [];

if(isset($params['customer_id'])) {
    $domain[] = ['customer_id', '=', $params['customer_id']];
}

if(isset($params['product_id'])) {
    $domain[] = ['product_id', '=', $params['product_id']];
}

if(!is_null($params['is_billable'])) {
    $domain[] = ['is_billable', '=', $params['is_billable']];
}

if(!is_null($params['has_receivable'])) {
    $domain[] = ['has_receivable', '=', $params['has_receivable']];
}

if(isset($params['date_from'], $params['date_to']) && $params['date_from'] > 0 && $params['date_to'] > 0) {
    $domain[] = ['date_to', '>=', $params['date_from']];
    $domain[] = ['date_from', '<=', $params['date_to']];
}
else {
    if(isset($params['date_from']) && $params['date_from'] > 0) {
        $domain[] = ['date_from', '>=', $params['date_from']];
    }

    if(isset($params['date_to']) && $params['date_to'] > 0) {
        $domain[] = ['date_to', '<=', $params['date_to']];
    }
}

$params['domain'] = (new Domain($params['domain']))
    ->merge(new Domain($domain))
    ->toArray();

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
