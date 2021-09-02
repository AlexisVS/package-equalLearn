<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;
use equal\orm\Model;

class BookingLineGroup extends Model {

    public static function getName() {
        return "Booking line group";
    }

    public static function getDescription() {
        return "Booking line groups are related to a booking and describe one or more sojourns and their related consumptions.";
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'string',
                'description'       => 'Mnemo for the group.',
                'default'           => ''
            ],

            'order' => [
                'type'              => 'integer',
                'description'       => 'Order of the group in the list.',
                'default'           => 1
            ],

            'has_pack' => [
                'type'              => 'boolean',
                'description'       => 'Does the group relates to a pack?',
                'default'           => false
            ],

            'pack_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\catalog\Product',
                'description'       => 'Pack (product) the group relates to, if any.',
                'visible'           => ['has_pack', '=', true],
                'onchange'          => 'sale\booking\BookingLineGroup::onchangePackId'
            ],

            'price_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\price\Price',
                'description'       => 'The price (retrieved by price list) the pack relates to.',
                'visible'           => ['has_pack', '=', true]
            ],

            'is_locked' => [
                'type'              => 'boolean',
                'description'       => 'Are modifications disabled for the group?',
                'default'           => false
            ],

            'date_from' => [
                'type'              => 'datetime',
                'description'       => "Time of arrival.",
                'onchange'          => 'sale\booking\BookingLineGroup::onchangeDateFrom',
                'default'           => time()
            ],

            'date_to' => [
                'type'              => 'datetime',
                'description'       => "Time of departure.",
                'default'           => time()
            ],

            'nb_nights' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Amount of nights of the sojourn.',
                'function'          => 'sale\booking\BookingLineGroup::getNbNights',
                'store'             => true
            ],

            'nb_pers' => [
                'type'              => 'integer',
                'description'       => 'Amount of persons this group is about.',
                'default'           => 1,
                'onchange'          => 'sale\booking\BookingLineGroup::onchangeNbPers'
            ],

            /* a booking can be split into several groups on which distinct rate classes apply, by default the rate_class of the customer is used */
            'rate_class_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\RateClass',
                'description'       => "The rate class that applies to the group.",
                'required'          => true,
                'onchange'          => 'sale\booking\BookingLineGroup::onchangeRateClassId'
            ],

            'booking_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingLine',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => 'Booking lines that belong to the group.',
                'ondetach'          => 'delete'
            ],

            'price_adapters_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingPriceAdapter',
                'foreign_field'     => 'booking_line_group_id',
                'description'       => 'Price adapters that apply to all lines of the group (based on group settings).'
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => 'Booking the line relates to (for consistency, lines should be accessed using the group they belong to).',
                'required'          => true
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => 'Final (computed) price for all lines.',
                'function'          => 'sale\booking\BookingLineGroup::getPrice'
// #todo - set store to true
            ]

        ];
    }

    public static function getNbNights($om, $oids, $lang) {
        $result = [];
        $groups = $om->read(__CLASS__, $oids, ['date_from', 'date_to']);
        foreach($groups as $gid => $group) {
            trigger_error("QN_DEBUG_ORM::calling run method for {$group['date_to']} - {$group['date_from']}", QN_REPORT_DEBUG);
            $result[$gid] = floor( ($group['date_to'] - $group['date_from']) / (60*60*24) );
        }
        return $result;
    }

    public static function getPrice($om, $oids, $lang) {
        $result = [];
        $groups = $om->read(__CLASS__, $oids, ['booking_lines_ids', 'is_locked', 'has_pack', 'price_id', 'pack_id']);

        if($groups > 0 && count($groups)) {
            foreach($groups as $gid => $group) {
                $has_own_price = false;
                // if the group relates to a pack and the product_model targeted by the pack has its own Price, then this is the one to return                 
                if($group['has_pack'] && $group['is_locked']) {
                    $result[$gid] = 0;
                    $res = $om->read(__CLASS__, $gid, ['pack_id.product_model_id.has_own_price', 'price_id.price', 'price_id.accounting_rule_id.vat_rule_id.rate']);
                    if($res > 0 && count($res)) {
                        if($res[$gid]['pack_id.product_model_id.has_own_price']) {
                            $group = array_merge($group, $res[$gid]);
                            $has_own_price = true;
                        }                        
                    }                    
                }
                if($has_own_price) {
                    $result[$gid] = round($group['price_id.price'] * (1 + $group['price_id.accounting_rule_id.vat_rule_id.rate']), 2);
                }
                // otherwise, price is the sum of bokingLines
                else {
                    $lines = $om->read('sale\booking\BookingLine', $group['booking_lines_ids'], ['price']);
                    $result[$gid] = 0.0;
                    if($lines > 0 && count($lines)) {
                        foreach($lines as $line) {
                            $result[$gid] += $line['price'];
                        }
                        $result[$gid] = round($result[$gid], 2);
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Update is_locked field according to selected pack (pack_id).
     * This is done when pack_id is changed, but can be manually set by the user afterward.
     */
    public static function onchangePackId($om, $oids, $lang) {
        $groups = $om->read(__CLASS__, $oids, [
            'booking_id', 'booking_lines_ids', 'nb_pers', 'nb_nights',
            'pack_id.is_locked',  
            'pack_id.product_model_id.pack_lines_ids', 'pack_id.product_model_id.has_own_price'
        ]);
    
        $groups_update_price_id = [];
        foreach($groups as $gid => $group) {

            /*
                Update is_locked field
            */
            // if targeted product model has its own price, set price_id accordingly
            if($group['pack_id.product_model_id.has_own_price']) {
                $om->write(__CLASS__, $gid, ['is_locked' => true], $lang);
                $groups_update_price_id[] = $gid;
            }
            else {
                $om->write(__CLASS__, $gid, ['is_locked' => $group['pack_id.is_locked'] ], $lang);
            }

            /*
                Reset booking_lines
            */            
            $om->write(__CLASS__, $gid, ['booking_lines_ids' => array_map(function($a) { return "-$a";}, $group['booking_lines_ids'])]);
            
            /*
                Create booking lines according to pack composition
            */
            $pack_lines_ids = $group['pack_id.product_model_id.pack_lines_ids'];
            $pack_lines = $om->read('sale\catalog\PackLine', $pack_lines_ids, ['child_product_id']);
            $products_ids = array_map( function ($a) {return $a['child_product_id'];}, $pack_lines);
            $order = 1;
            foreach($products_ids as $product_id) {                
                $lid = $om->create('sale\booking\BookingLine', [
                    'order'                     => $order,
                    'booking_id'                => $group['booking_id'],
                    'booking_line_group_id'     => $gid,
                    'product_id'                => $product_id,
                    'qty'                       => $group['nb_pers']
                ]);
                if($lid > 0) {
                    $om->write(__CLASS__, $gid, ['booking_lines_ids' => ["+$lid"] ]);                    
                }
                ++$order;
            }            
        }

        /*
            Update price for groups having a pack with own price
        */
        self::_updatePriceId($om, $groups_update_price_id, $lang);

        /*
            Update price adapters
        */
        self::_updatePriceAdapters($om, $oids, $lang);

        //#memo - consumptions are updated by the bookingLines        
    }

    public static function onchangeRateClassId($om, $oids, $lang) {
        self::_updatePriceAdapters($om, $oids, $lang);
    }

    public static function onchangeDateFrom($om, $oids, $lang) {
        $om->write(__CLASS__, $oids, ['nb_nights' => null ]);
        self::_updatePriceAdapters($om, $oids, $lang);
        $booking_lines_ids = $om->read(__CLASS__, $oids, ['booking_lines_ids']);
        if($booking_lines_ids > 0 && count($booking_lines_ids)) {
            BookingLine::_updatePriceId($om, $booking_lines_ids, $lang);
        }
    }

    public static function onchangeDateTo($om, $oids, $lang) {
        $om->write(__CLASS__, $oids, ['nb_nights' => null ]);
        self::_updatePriceAdapters($om, $oids, $lang);
    }

    public static function onchangeNbPers($om, $oids, $lang) {
        self::_updatePriceAdapters($om, $oids, $lang);
    }

    /**
     * Create Price adapters according to group settings.
     *
     * _updatePriceAdapters is called upon booking_id.customer_id change
     */
    public static function _updatePriceAdapters($om, $oids, $lang) {
        /*
            Remove all previous price adapters that were automatically created
        */
        $price_adapters_ids = $om->search('sale\booking\BookingPriceAdapter', [ ['booking_line_group_id', 'in', $oids], ['is_manual_discount','=', false]]);
        $om->remove('sale\booking\BookingPriceAdapter', $price_adapters_ids, true);

        $line_groups = $om->read(__CLASS__, $oids, ['rate_class_id', 'date_from', 'date_to', 'nb_pers', 'booking_id',
                                                    'booking_id.customer_id.count_booking_24',
                                                    'booking_id.center_id.season_category_id',
                                                    'booking_id.center_id.discount_list_category_id']);
        foreach($line_groups as $group_id => $group) {
            /*
                Find the first Discount List that matches the booking dates
            */
            $discount_lists_ids = $om->search('sale\discount\DiscountList', [
                ['rate_class_id', '=', $group['rate_class_id']],
                ['discount_list_category_id', '=', $group['booking_id.center_id.discount_list_category_id']],
                ['valid_from', '<=', $group['date_from']],
                ['valid_until', '>=', $group['date_from']]
            ]);

            $discount_lists = $om->read('sale\discount\DiscountList', $discount_lists_ids, ['id', 'discounts_ids']);
            $discount_list_id = 0;
            if($discount_lists > 0 && count($discount_lists)) {
                $discount_list_id = array_keys($discount_lists)[0];
            }
            /*
                Search for matching Discounts within the found Discount List
            */
            if($discount_list_id) {
                $operands = [];
                $operands['count_booking_24'] = $group['booking_id.customer_id.count_booking_24'];
                $operands['duration'] = ($group['date_to']-$group['date_from'])/(60*60*24);     // duration in nights
                $operands['nb_pers'] = $group['nb_pers'];                                       // number of participants

                $season_category = $group['booking_id.center_id.season_category_id'];
                $date = $group['date_from'];
                /*
                    Pick up the first season period that matches the year and the season category of the center
                */
                $year = date('Y', $date);
                $seasons_ids = $om->search('sale\season\SeasonPeriod', [
                    ['season_category_id', '=', $group['booking_id.center_id.season_category_id']],
                    ['date_from', '<=', $group['date_from']],
                    ['date_to', '>=', $group['date_from']],
                    ['year', '=', $year]
                ]);

                $periods = $om->read('sale\season\SeasonPeriod', $seasons_ids, ['id', 'season_type_id.name']);
                if($periods > 0 && count($periods)){
                    $period = array_shift($periods);
                    $operands['season'] = $period['season_type_id.name'];
                }

                $discounts_ids = $om->search('sale\discount\DiscountList', [
                    ['discount_list_category_id', '=', $group['booking_id.center_id.discount_list_category_id']],
                    ['valid_from', '<=', $group['date_from']],
                    ['valid_until', '>=', $group['date_from']]
                ]);

                $discounts = $om->read('sale\discount\Discount', $discount_lists[$discount_list_id]['discounts_ids'], ['value', 'type', 'conditions_ids']);

                foreach($discounts as $discount_id => $discount) {
                    $conditions = $om->read('sale\discount\Condition', $discount['conditions_ids'], ['operand', 'operator', 'value']);
                    $valid = true;
                    foreach($conditions as $c_id => $condition) {
                        if(!in_array($condition['operator'], ['>', '>=', '<', '<=', '='])) {
                            // unknown operator
                            continue;
                        }
                        $operator = $condition['operator'];
                        if($operator == '=') {
                            $operator = '==';
                        }
                        if(!isset($operands[$condition['operand']])) {
                            $valid = false;
                            break;
                        }
                        $operand = $operands[$condition['operand']];
                        $value = $condition['value'];
                        if(!is_numeric($operand)) {
                            $operand = "'$operand'";
                        }
                        if(!is_numeric($value)) {
                            $value = "'$value'";
                        }
                        trigger_error(" testing {$operand} {$operator} {$value}", QN_REPORT_DEBUG);
                        $valid = $valid && (bool) eval("return ( {$operand} {$operator} {$value});");
                    }
                    if($valid) {
                        // current discount must be applied : create a price adpter with
                        $price_adapters_ids = $om->create('sale\booking\BookingPriceAdapter', [
                            'is_manual_discount'    => false,
                            'booking_id'            => $group['booking_id'],
                            'booking_line_group_id' => $group_id,
                            'discount_id'           => $discount_id,
                            'type'                  => $discount['type'],
                            'value'                 => $discount['value']
                        ]);
                    }
                }
            }
            else {
                $date = date('Y-m-d', $group['date_from']);
                trigger_error("QN_DEBUG_ORM::no matching discount list found for date {$date}", QN_REPORT_DEBUG);
            }
        }
    }

    /**
     * Find and set price list according to group settings.
     * This only applies when group targets a Pack with own price.
     */
    public static function _updatePriceId($om, $oids, $lang) {
        $groups = $om->read(__CLASS__, $oids, [
            'date_from',
            'pack_id',
            'booking_id.center_id.price_list_category_id'
        ]);

        foreach($groups as $gid => $group) {
            /*
                Find the first Price List that matches the criteria from the booking
            */
            $price_lists_ids = $om->search('sale\price\PriceList', [
                                                                       ['price_list_category_id', '=', $group['booking_id.center_id.price_list_category_id']],
                                                                       ['date_from', '<=', $group['date_from']],
                                                                       ['date_to', '>=', $group['date_from']]
                                                                   ]);
            $price_lists = $om->read('sale\price\PriceList', $price_lists_ids, ['id']);
            $price_list_id = 0;
            if($price_lists > 0 && count($price_lists)) {
                $price_list_id = array_keys($price_lists)[0];
            }
            /*
                Search for a matching Price within the found Price List
            */
            if($price_list_id) {
                // there should be exactly one matching price
                $prices_ids = $om->search('sale\price\Price', [ ['price_list_id', '=', $price_list_id], ['product_id', '=', $group['pack_id']] ]);
                if($prices_ids > 0 && count($prices_ids)) {
                    /*
                        Assign found Price to current group
                    */
                    $om->write(__CLASS__, $gid, ['price_id' => $prices_ids[0]]);
                }
                else {
                    $om->write(__CLASS__, $gid, ['price_id' => null, 'vat_rate' => 0, 'unit_price' => 0, 'price' => 0]);
                    trigger_error("QN_DEBUG_ORM::no matching price found for product {$group['product_id']} in price_list $price_list_id", QN_REPORT_ERROR);
                }
            }
            else {
                $om->write(__CLASS__, $gid, ['price_id' => null, 'vat_rate' => 0, 'unit_price' => 0, 'price' => 0]);
                $date = date('Y-m-d', $group['booking_line_group_id.date_from']);
                trigger_error("QN_DEBUG_ORM::no matching price list found for date {$date}", QN_REPORT_ERROR);
            }
        }
    }    
}