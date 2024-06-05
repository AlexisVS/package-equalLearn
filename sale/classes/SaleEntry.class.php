<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale;

use eQual;
use equal\orm\Model;
use sale\price\Price;
use sale\price\PriceList;

class SaleEntry extends Model {

    public static function getDescription() {
        return "Sale entries are used to describe sales (the action of selling a good or a service).
            In addition, this class is meant to be used as an OOP interface for entities meant to describe something that can be sold.";
    }

    public static function getColumns() {

        return [

            'code' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Entry code',
                'function'          => 'calcCode'
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Description of the entry.',
                'dependents'        => ['name']
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Short readable identifier of the entry.',
                'store'             => true,
                'function'          => 'calcName'
            ],

            'detailed_description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Detailed description of the entry.'
            ],

            'has_receivable' => [
                'type'              => 'boolean',
                'description'       => 'The entry is linked to a receivable entry.',
                'default'           => false
            ],

            'receivable_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\receivable\Receivable',
                'description'       => 'The receivable entry the sale entry is linked to.',
                'visible'           => ['has_receivable', '=', true]
            ],

            'is_billable' => [
                'type'              => 'boolean',
                'description'       => 'Flag telling if the entry can be billed to the customer.',
                'help'              => 'Under certain circumstances, a task relates to a customer but cannot be billed (from a commercial perspective). Most of the time this cannot be known in advance and this flag is intended to be set manually.',
                'default'           => true
            ],

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'description'       => 'The Customer to who refers the item.'
            ],

            'product_id'=> [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Product',
                'description'       => 'Product of the catalog sale.'
            ],

            'price_id'=> [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\price\Price',
                'description'       => 'Price of the sale.',
                'dependencies'      => ['unit_price']
            ],

            'unit_price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Unit price of the product related to the entry.',
                'function'          => 'calcUnitPrice',
                'store'             => true
            ],

            'qty' => [
                'type'              => 'float',
                'description'       => 'Quantity of product.',
                'default'           => 1.0
            ],

            'free_qty' => [
                'type'              => 'integer',
                'description'       => 'Free quantity of product, if any.',
                'default'           => 0
            ],

            // #memo - important: to allow maximum flexibility, percent values can hold 4 decimal digits (must not be rounded, except for display)
            'discount' => [
                'type'              => 'float',
                'usage'             => 'amount/rate',
                'description'       => 'Total amount of discount to apply, if any.',
                'default'           => 0.0
            ],

            'object_class' => [
                'type'              => 'string',
                'description'       => 'Class of the object object_id points to.',
                'dependencies'      => ['subscription_id', 'project_id']
            ],

            'object_id' => [
                'type'              => 'integer',
                'description'       => 'Identifier of the object the sale entry originates from.',
                'dependents'        => ['subscription_id', 'project_id']
            ],

            'subscription_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'sale\subscription\Subscription',
                'function'          => 'calcSubscriptionId',
                'description'       => 'Identifier of the subscription the sale entry originates from.',
                'store'             => true
            ],

            'project_id' => [
                'type'              => 'computed',
                'result_type'       => 'many2one',
                'foreign_object'    => 'timetrack\Project',
                'function'          => 'calcProjectId',
                'description'       => 'Identifier of the Project the sale entry originates from.',
                'store'             => true
            ],

            'receivable_name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Name given to the generated receivable.',
                'function'          => 'calcReceivableName',
                'store'             => false
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'ready',
                    'validated',
                    'billed'
                ],
                'description'       => 'Status of the sale entry.',
                'default'           => 'pending'
            ]

        ];
    }

    public static function getPolicies(): array {
        return [
            'ready-for-validation' => [
                'description' => 'Verifies that the sale entry can enter validation process (Used in timetrack\TimeEntry).',
                'function'    => 'policyReadyForValidation'
            ],
            'billable' => [
                'description' => 'Verifies that sale entry holds all information required for billing.',
                'function'    => 'policyBillable'
            ]
        ];
    }

    /**
     * Method to override when extending class, by default no validation process for sale entry
     *
     * @param $self
     * @return array
     */
    public static function policyReadyForValidation($self): array {
        $result = [];
        $self->read(['id']);
        foreach($self as $id => $entry) {
            $result[$id] = false;
        }

        return $result;
    }

    public static function policyBillable($self): array {
        $result = [];
        $self->read(['status', 'object_class', 'customer_id', 'product_id', 'price_id', 'unit_price', 'qty', 'is_billable']);
        foreach($self as $id => $entry) {
            if( ($entry['object_class'] === 'timetrack\Project' && $entry['status'] !== 'validated')
                || !isset($entry['customer_id'], $entry['product_id'], $entry['price_id'], $entry['unit_price'])
                || $entry['qty'] <= 0
                || !$entry['is_billable'] ) {
                $result[$id] = false;
            }
        }

        return $result;
    }

    public static function addReceivable($self): void {
        $self->read(['id']);
        foreach($self as $entry) {
            try {
                eQual::run('do', 'sale_saleentry_add-receivable', ['id' => $entry['id']]);
            }
            catch(\Exception $e) {
                trigger_error("PHP::Failed adding receivable for sale entry {$entry['id']}", EQ_REPORT_ERROR);
            }
        }
    }

    public static function getWorkflow() {
        return [
            'pending' => [
                'description' => 'Sale entry is still a draft and waiting to be completed.',
                'icon' => 'edit',
                'transitions' => [
                    'request-validation' => [
                        'description' => 'Sets sale entry as ready for validation.',
                        'help' => 'Can only be applied if sale\\SaleEntry has a validation process.',
                        'policies' => [
                            'ready-for-validation',
                        ],
                        'status' => 'ready',
                    ],
                    'bill' => [
                        'description' => 'Create receivable, from sale entry, who will be invoiced to the customer.',
                        'help' => 'Can only be applied if sale\\SaleEntry does not have a validation process.',
                        'onafter' => 'addReceivable',
                        'policies' => [
                            'billable',
                        ],
                        'status' => 'billed',
                    ],
                ],
            ],
            'ready' => [
                'description' => 'Sale entry submitted for approval.',
                'help' => 'This status can be used by children of this class to check the completed specific information (Used by timetrack\\TimeEntry).',
                'icon' => 'pending',
                'transitions' => [
                    'refuse' => [
                        'description' => 'Refuse sale entry, sets its status back to pending.',
                        'status' => 'pending',
                    ],
                    'validate' => [
                        'description' => 'Validate sale entry.',
                        'status' => 'validated',
                    ],
                ],
            ],
            'validated' => [
                'description' => 'Sale entry validated, now sale information must be completed to bill the sale entry.',
                'help' => 'To bill the sale entry the sale information (product, price, unit price) must be completed.',
                'icon' => 'check_circle',
                'transitions' => [
                    'bill' => [
                        'description' => 'Create receivable, from sale entry, who will be invoiced to the customer.',
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
                'help' => 'Sale entry life cycle is over, its data cannot be modified.',
                'icon' => 'receipt_long',
                'transitions' => [
                ],
            ],
        ];
    }

    public static function onchange($event, $values) {
        $result = [];

        if(isset($event['product_id'])) {
            $price_lists_ids = PriceList::search([
                    [
                        ['date_from', '<=', time()],
                        ['date_to', '>=', time()],
                        ['status', '=', 'published'],
                    ]
                ])
                ->ids();

            $result['price_id'] = Price::search([
                    ['product_id', '=', $event['product_id']],
                    ['price_list_id', 'in', $price_lists_ids]
                ])
                ->read(['id', 'name', 'price', 'vat_rate'])
                ->first();

            if(isset($result['price_id']['price'])) {
                $result['unit_price'] = $result['price_id']['price'];
            }
        }

        return $result;
    }

    public static function calcCode($self) {
        $result = [];
        foreach($self->ids() as $id) {
            $result[$id] = str_pad($id, 5, '0', STR_PAD_LEFT);
        }
        return $result;
    }

    public static function calcName($self) {
        $result = [];
        $self->read(['code', 'description']);
        foreach($self as $id => $entry) {
            $result[$id] = '['.$entry['code'].']';
            if(isset($entry['description'])
                && strlen($entry['description']) > 0) {
                $result[$id] .= ' '.$entry['description'];
            }
        }
        return $result;
    }

    public static function calcUnitPrice($self) {
        $result = [];
        $self->read(['price_id' => ['price']]);
        foreach($self as $id => $entry) {
            if(!isset($entry['price_id']['price'])) {
                continue;
            }

            $result[$id] = $entry['price_id']['price'];
        }
        return $result;
    }

    public static function calcSubscriptionId($self) {
        $result = [];
        $self->read(['object_class', 'object_id']);
        foreach($self as $id => $entry) {
            $result[$id] = null;
            if($entry['object_class'] == 'sale\subscription\Subscription') {
                $result[$id] = $entry['object_id'];
            }
        }
        return $result;
    }

    public static function calcProjectId($self) {
        $result = [];
        $self->read(['object_class', 'object_id']);
        foreach($self as $id => $entry) {
            $result[$id] = null;
            if($entry['object_class'] == 'timetrack\Project') {
                $result[$id] = $entry['object_id'];
            }
        }
        return $result;
    }

    /**
     * #todo - unsure that this field is necessary
     */
    public static function calcReceivableName($self): array {
        $result = [];
        $self->read(['object_class', 'name', 'product_id' => ['name']]);

        foreach($self as $id => $entry) {
            $receivable_name = $entry['product_id']['name'];
            if($entry['object_class'] === 'timetrack\Project') {
                $receivable_name = $entry['name'];
            }

            $result[$id] = trim($receivable_name);
        }

        return $result;
    }

    public static function canupdate($self, $values) {
        $self->read(['has_receivable']);
        foreach($self as $sale_entry) {
            if($sale_entry['has_receivable']) {
                return ['has_receivable' => ['non_editable' => 'Billed sale entry cannot be modified.']];
            }
        }

        return parent::canupdate($self, $values);
    }
}
