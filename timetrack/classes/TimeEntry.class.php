<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace timetrack;

use DateTime;
use DateTimeZone;
use sale\SaleEntry;
use core\setting\Setting;
use Exception;

class TimeEntry extends SaleEntry {

    public static function getName(): string {
        return 'Time entry';
    }

    public static function getDescription(): string {
        return 'Time entries are used to log the tasks performed by employees on customers projects, and the duration spent on it.';
    }

    public static function getColumns(): array {

        return [

            /**
             * Override SaleEntry columns
             */

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Short readable identifier of the entry.',
                'store'             => true,
                'function'          => 'calcName'
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Short description of the task performed.',
                'dependents'        => ['name']
            ],

            'project_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'timetrack\Project',
                'description'    => 'Identifier of the Project the sale entry originates from.',
                'dependents'     => ['name', 'ticket_link', 'product_id', 'price_id', 'unit_price'],
                'onupdate'       => 'onupdateProjectId'
            ],

            'inventory_product_id' => [
                'type'            => 'computed',
                'result_type'     => 'many2one',
                'foreign_object'  => 'inventory\Product',
                'description'     => 'The product the the time entry refers to, if any.',
                'function'        => 'calcInventoryProductId',
                'store'           => true
            ],

            'customer_id' => [
                'type'           => 'computed',
                'result_type'    => 'many2one',
                'foreign_object' => 'sale\customer\Customer',
                'description'    => 'Customer this time entry was created for.',
                'function'       => 'calcCustomerId',
                'store'          => true,
                'instant'        => true,
                'readonly'       => true
            ],

            'object_class' => [
                'type'           => 'string',
                'description'    => 'Class of the object object_id points to.',
                'default'        => 'timetrack\Project',
                'dependents'     => ['project_id']
            ],

            'product_id' => [
                'type'           => 'computed',
                'result_type'    => 'many2one',
                'foreign_object' => 'sale\catalog\Product',
                'description'    => 'Product of the sale catalog.',
                'help'           => 'This field references a Product from the catalog. This field is not to be mistaken with the Product (software) of the customer.',
                'function'       => 'calcProductId',
                'store'          => true
            ],

            'price_id' => [
                'type'           => 'computed',
                'result_type'    => 'many2one',
                'foreign_object' => 'sale\price\Price',
                'description'    => 'Price of the sale.',
                'function'       => 'calcPriceId',
                'store'          => true,
                'dependents'     => ['unit_price']
            ],

            'unit_price' => [
                'type'           => 'computed',
                'result_type'    => 'float',
                'usage'          => 'amount/money:4',
                'description'    => 'Unit price of the product related to the entry.',
                'function'       => 'calcUnitPrice',
                'store'          => true
            ],

            'qty' => [
                'type'           => 'computed',
                'result_type'    => 'float',
                'description'    => 'The final amount of time to invoice.',
                'description'    => 'Quantity is expressed in hours, rounded to the quarter hour, and based on the billable duration.',
                'function'       => 'calcQty',
                'store'          => true
            ],

            /**
             * Specific TimeEntry columns
             */

            'time_start' => [
                'type'           => 'time',
                'description'    => 'Start time of the entry.',
                'default'        => function () { return self::getTimeZoneCurrentHour() * 3600; },
                'dependents'     => ['duration', 'qty', 'billable_amount']
            ],

            'time_end' => [
                'type'           => 'time',
                'description'    => 'End time of the entry.',
                'default'        => function () { return (self::getTimeZoneCurrentHour() + 1) * 3600; },
                'dependents'     => ['duration', 'qty', 'billable_amount']
            ],

            'is_full_day' => [
                'type'           => 'boolean',
                'description'    => 'The task of the entry was performed for a whole day.',
                'default'        => false
            ],

            'duration' => [
                'type'           => 'computed',
                'result_type'    => 'time',
                'description'    => 'Duration of the entry.',
                'function'       => 'calcDuration',
                'store'          => true,
                'instant'        => true,
                'onupdate'       => 'onupdateDuration'
            ],

            'billable_duration' => [
                'type'           => 'time',
                'description'    => 'Duration that can be actually invoiced.',
                'help'           => 'The duration (part of the entry) that can be billed to the Customer according to the related requested Task. By default has the same value as duration. Unlike duration, billable duration is meant to be set manually.',
                'dependents'     => ['qty', 'billable_amount']
            ],

            'user_id' => [
                'type'           => 'many2one',
                'foreign_object' => 'core\User',
                'description'    => 'User the time entry was performed by.',
                'default'        => 'defaultUserId'
            ],

            'origin' => [
                'type'           => 'string',
                'selection'      => [
                    'project',
                    'backlog',
                    'email',
                    'support'
                ],
                'dependents'     => ['name'],
                'description'    => 'Origin of the time entry: what the task performed is a response to.',
                'help'           => "Project: refers to a Project Management task.\n
                                     Backlog: refers to one (or more) entry from the backlog associated with the project.\n
                                     E-mail: refers to a specific email conversation.\n
                                     Support: refers to a specific support ticket.",
                'default'        => 'project'
            ],

            'ticket_id' => [
                'type'           => 'integer',
                'description'    => 'Identifier of the support ticket (number).',
                'dependents'     => ['name', 'ticket_link'],
                'onupdate'       => 'onupdateTicketId',
                'visible'        => ['origin', '=', 'support']
            ],

            'ticket_link' => [
                'type'           => 'computed',
                'result_type'    => 'string',
                'description'    => 'Support ticket link for quick access.',
                'usage'          => 'uri/url',
                'function'       => 'calcTicketLink',
                'store'          => true,
                'visible'        => ['origin', '=', 'support']
            ],

            'reference' => [
                'type'           => 'string',
                'dependents'     => ['name'],
                'description'    => 'Reference completing the origin.'
            ],

            'billable_amount' => [
                'type'           => 'computed',
                'result_type'    => 'float',
                'usage'          => 'amount/money',
                'function'       => 'calcBillableAmount',
                'description'    => 'Amount that will be invoiced to the Customer.',
                'help'           => 'This field is just an indicator. The final value will be computed when creating the receivable.',
                'store'          => true
            ]

        ];
    }

    private static function getTimeZoneCurrentHour(): int {
        $current_hour = (int) date('H');

        $time_zone = Setting::get_value('core', 'locale', 'time_zone');
        if(!is_null($time_zone)) {
            try {
                $timezone = new DateTimeZone($time_zone);
                $dateTime = new DateTime('now', $timezone);
                $current_hour = (int) $dateTime->format('H');
            }
            catch(Exception $e) {
                trigger_error('PHP::error getting time zone current hour', EQ_REPORT_WARNING);
            }
        }

        return $current_hour;
    }

    private static function computeTicketLink($url, $ticket_id): string {
        $result = $url ?? '';
        if(substr($result, -1) !== '/') {
            $result .= '/';
        }
        $result .= 'support/#/ticket/'.$ticket_id;
        return $result;
    }

    public static function defaultUserId($auth) {
        return $auth->userId();
    }

    public static function canupdate($self, $values): array {
        $self->read(['status']);

        foreach($self as $id => $entry) {
            if(in_array($entry['status'], ['pending', 'ready'])) {
                continue;
            }

            $editable_fields = ['description', 'detailed_description', 'status'];
            $sale_fields = ['product_id', 'price_id', 'unit_price', 'is_billable'];

            if($entry['status'] === 'validated') {
                $editable_fields = array_merge($editable_fields, $sale_fields);
            }

            foreach($values as $field => $value) {
                if(!in_array($field, $editable_fields)) {
                    return [
                            $field => [
                                'non_editable' => sprintf(
                                    'Time entry %s can only be updated from %s to %s.',
                                    $field,
                                    'pending',
                                    !in_array($field, $sale_fields) ? 'ready' : 'validated'
                                )
                            ]
                        ];
                }
            }
        }

        return parent::canupdate($self, $values);
    }

    public static function onchange($event, $values): array {
        $result = [];

        if(isset($event['origin'])) {
            if($event['origin'] != 'support') {
                $result['ticket_id'] = null;
                $result['ticket_link'] = null;
            }
        }

        if(isset($event['ticket_id'])) {
            $project = Project::id($values['project_id'])->read(['product_id' => 'url'])->first();
            $result['ticket_link'] = self::computeTicketLink($project['product_id']['url'], $event['ticket_id']);
        }

        if(isset($event['project_id'])) {
            $project = Project::id($event['project_id'])
                ->read(['customer_id' => ['name']])
                ->first();

            $result['customer_id'] = $project['customer_id'];
        }

        if(isset($event['time_start'], $values['time_end'])
                || isset($event['time_end'], $values['time_start']) ) {
            $time_start = $event['time_start'] ?? $values['time_start'];
            $time_end = $event['time_end'] ?? $values['time_end'];

            if($time_end < $time_start) {
                $result['time_end'] = $time_start + ($values['duration'] ?? 0);
            }
            else {
                $diff = $time_end - $time_start;
                $result['duration'] = ( ceil($diff / 60 / 15) * 15 ) * 60;
                $result['billable_duration'] = $result['duration'];
            }
        }
        elseif(isset($event['duration'], $values['time_start'])) {
            $result['time_end'] = $values['time_start'] + $event['duration'];
            $result['billable_duration'] = $event['duration'];
        }

        if(isset($event['is_full_day']) && $event['is_full_day']) {
            $result['time_start'] = 9 * 3600;
            $result['time_end'] = 17 * 3600;
            $result['duration'] = 7.5 * 3600;
            $result['billable_duration'] = 7 * 3600;
        }

        return $result;
    }

    public static function onupdateProjectId($self): void {
        $self->read(['object_id', 'object_class', 'project_id']);
        foreach($self as $id => $entry) {
            if($entry['object_id'] != $entry['project_id'] || $entry['object_class'] != Project::getType()) {
                self::id($id)->update([
                        'object_id'     => $entry['project_id'],
                        'object_class'  => Project::getType()
                    ]);
            }
        }
    }

    public static function onupdateTicketId($self) : void {
        $self->read(['ticket_id']);
        foreach($self as $id => $entry) {
            self::id($id)->update(['reference' => 'ticket '.$entry['ticket_id']]);
        }
    }

    public static function calcBillableAmount($self) {
        $result = [];
        $self->read(['qty', 'unit_price', 'is_billable', 'inventory_product_id' => ['is_internal']]);
        foreach($self as $id => $entry) {
            $is_billable = ($entry['inventory_product_id']['is_internal']) ? false : $entry['is_billable'];
            $result[$id] = $is_billable ? round($entry['qty'] * $entry['unit_price'], 2) : 0.0;
        }
        return $result;
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['project_id' => ['name'], 'origin', 'reference', 'description']);
        foreach($self as $id => $entry) {
            $result[$id] = $entry['project_id']['name'];
            if(isset($entry['reference']) && strlen($entry['reference']) > 0) {
                $result[$id] .=  ' ['.$entry['reference'].']';
            }
            else {
                $result[$id] .= ' - '.ucfirst($entry['origin']);
            }
            if(isset($entry['description']) && strlen($entry['description']) > 0) {
                $result[$id] .= ' - '.$entry['description'];
            }
        }
        return $result;
    }

    public static function calcProductId($self): array {
        $result = [];
        $self->read(['project_id' => ['time_entry_sale_model_id' => 'product_id']]);
        foreach($self as $id => $entry) {
            $result[$id] = $entry['project_id']['time_entry_sale_model_id']['product_id'] ?? null;
        }
        return $result;
    }

    public static function calcInventoryProductId($self) {
        $result = [];
        $self->read(['project_id' => ['product_id']]);
        foreach($self as $id => $entry) {
            $result[$id] = $entry['project_id']['product_id'] ?? null;
        }
        return $result;
    }

    public static function calcPriceId($self): array {
        $result = [];
        $self->read(['project_id' => ['time_entry_sale_model_id' => 'price_id']]);
        foreach($self as $id => $entry) {
            $result[$id] = $entry['project_id']['time_entry_sale_model_id']['price_id'] ?? null;
        }
        return $result;
    }

    public static function calcUnitPrice($self): array {
        $result = [];
        $self->read(['project_id' => ['time_entry_sale_model_id' => 'unit_price']]);
        foreach($self as $id => $entry) {
            $result[$id] = $entry['project_id']['time_entry_sale_model_id']['unit_price'] ?? null;
        }
        return $result;
    }

    public static function calcDuration($self): array {
        $result = [];
        $self->read(['is_full_day', 'time_start', 'time_end', 'billable_duration']);
        foreach($self as $id => $entry) {
            if(!isset($entry['time_start'], $entry['time_end'])) {
                continue;
            }
            if($entry['is_full_day']) {
                $result[$id] = 7.5 * 3600;
                if(!$entry['billable_duration']) {
                    self::id($id)->update(['billable_duration' => 7 * 3600]);
                }
            }
            else {
                $result[$id] = $entry['time_end'] - $entry['time_start'];
                if(!$entry['billable_duration']) {
                    self::id($id)->update(['billable_duration' => $result[$id]]);
                }
            }
        }
        return $result;
    }

    public static function calcQty($self): array {
        $result = [];
        $self->read(['billable_duration']);
        foreach($self as $id => $entry) {
            $hours = floatval($entry['billable_duration']) / 3600;
            $result[$id] = round($hours * 4) / 4;
        }
        return $result;
    }

    public static function onupdateDuration($self): void {
        $self->read(['is_full_day', 'time_start', 'time_end', 'duration', 'qty']);
        foreach($self as $id => $entry) {
            $values = [];
            if(!$entry['is_full_day']) {
                if(isset($entry['duration'], $entry['time_start'], $entry['time_end'])
                        && $entry['duration'] !== ($entry['time_end'] - $entry['time_start'])) {
                    $values['time_end'] = $entry['time_start'] + $entry['duration'];
                }
                else {
                    $values['billable_duration'] = $entry['duration'];
                }
            }

            self::id($id)->update($values);
        }
    }

    public static function calcCustomerId($self): array {
        $result = [];
        $self->read(['project_id' => ['customer_id']]);
        foreach($self as $id => $entry) {
            if(!isset($entry['project_id']['customer_id'])) {
                continue;
            }

            $result[$id] = $entry['project_id']['customer_id'];
        }

        return $result;
    }

    public static function calcTicketLink($self): array {
        $result = [];
        $self->read(['origin', 'ticket_id', 'project_id' => ['product_id' => ['url']]]);
        foreach($self as $id => $entry) {
            if($entry['origin'] == 'support') {
                $result[$id] = self::computeTicketLink($entry['project_id']['product_id']['url'], $entry['ticket_id']);
            }
        }
        return $result;
    }

    public static function policyReadyForValidation($self): array {
        $result = [];
        $self->read(['project_id', 'user_id', 'origin', 'duration']);
        foreach($self as $id => $entry) {
            if( !isset($entry['project_id'], $entry['user_id'], $entry['origin'], $entry['duration'])
                    || $entry['duration'] <= 0 ) {
                $result[$id] = false;
            }
        }

        return $result;
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Time entry is still a draft and waiting to be completed.',
                'icon' => 'edit',
                'transitions' => [
                    'submit' => [
                        'description' => 'Sets time entry as ready for validation.',
                        'policies' => [
                            'ready-for-validation',
                        ],
                        'status' => 'ready',
                    ],
                ],
            ],
            'ready' => [
                'description' => 'Time entry required information are waiting for approval.',
                'help' => 'Specific information about time entry (project, user, origin and duration) have been completed and time entry is waiting for approval.',
                'icon' => 'pending',
                'transitions' => [
                    'refuse' => [
                        'description' => 'Refuse time entry, sets its status back to pending.',
                        'status' => 'pending',
                    ],
                    'validate' => [
                        'description' => 'Validate time entry.',
                        'status' => 'validated',
                    ],
                ],
            ],
            'validated' => [
                'description' => 'Sale information must be completed to bill the sale entry.',
                'help' => 'Time entry information have been validated, product and prices information must be completed to be billable.',
                'icon' => 'check_circled',
                'transitions' => [
                    'bill' => [
                        'description' => 'Create receivable, from time entry, who will be billed to the customer.',
                        'onafter' => 'addReceivable',
                        'policies' => [
                            'billable',
                        ],
                        'status' => 'billed',
                    ],
                ],
            ],
            'billed' => [
                'description' => 'A receivable was generated, it can be invoiced to the customer.',
                'icon' => 'receipt_long',
                'transitions' => [
                ],
            ],
        ];
    }
}
