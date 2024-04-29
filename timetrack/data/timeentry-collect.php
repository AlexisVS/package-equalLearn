<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

use equal\orm\Domain;
use timetrack\TimeEntry;

$filters = [
    'description' => [
        'description'    => 'Display only entries matching the given description',
        'type'           => 'string'
    ],
    'user_id' => [
        'description'    => 'Display only entries of selected user',
        'type'           => 'many2one',
        'foreign_object' => 'core\User'
    ],
    'show_filter_date' => [
        'type'           => 'boolean',
        'default'        => false
    ],
    'date' => [
        'description'    => 'Display only entries that occurred on selected date',
        'type'           => 'date',
        'default'        => strtotime('today')
    ],
    'customer_id' => [
        'description'    => 'Display only entries of selected customer',
        'type'           => 'many2one',
        'foreign_object' => 'sale\customer\Customer'
    ],
    'project_id' => [
        'description'    => 'Display only entries of selected project',
        'type'           => 'many2one',
        'foreign_object' => 'timetrack\Project'
    ],
    'origin' => [
        'description'    => 'Display only entries of selected origin',
        'type'           => 'string',
        'selection'      => [
            'all',
            'project',
            'backlog',
            'email',
            'support'
        ],
        'default'        => 'all'
    ],
    'has_receivable' => [
        'description'    => 'Filter entries on has receivable',
        'type'           => 'boolean',
    ],
    'is_billable' => [
        'description'    => 'Filter entries on is billable',
        'type'           => 'boolean',
    ],
    'status' => [
        'description'    => 'Filter entries on status',
        'type'           => 'string',
        'selection'      => array_merge(['all'], array_keys(TimeEntry::STATUS_MAP)),
        'default'        => 'all'
    ]
];

list($params, $providers) = eQual::announce([
    'description' => 'Advanced search for Time Entries: returns a collection of Reports according to extra parameters.',
    'extends'     => 'core_model_collect',
    'params'      => array_merge(
        [
            'entity' => [
                'description' => 'Full name (including namespace) of the class to return.',
                'type'        => 'string',
                'default'     => 'timetrack\TimeEntry'
            ],
        ],
        $filters
    ),
    'response'    => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'   => ['context']
]);

/**
 * @var \equal\php\Context $context
 */
$context = $providers['context'];

if(!$params['show_filter_date']) {
    unset($filters['date']);
}
unset($filters['show_filter_date']);

$domain = [];
foreach($filters as $field => $field_config) {
    if(!isset($params[$field])) {
        continue;
    }

    $value = $params[$field];
    $type = $field_config['type'];
    if($type === 'string' && !empty($field_config['selection'])) {
        $type = 'selection';
    }

    if($type === 'string' && strlen($value) > 0) {
        $domain[] = [$field, 'ilike', '%'.$value.'%'];
    }
    elseif(
        ($type === 'many2one' && !empty($value))
        || ($type === 'selection' && $value !== 'all')
        || (in_array($type, ['boolean', 'date']))
    ) {
        $domain[] = [$field, '=', $value];
    }
}

$params['domain'] = (new Domain($params['domain']))
    ->merge(new Domain($domain))
    ->toArray();

$result = eQual::run('get', 'model_collect', $params, true);

$context->httpResponse()
        ->body($result)
        ->send();
