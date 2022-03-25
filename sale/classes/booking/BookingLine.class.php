<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;
use equal\orm\Model;

class BookingLine extends Model {

    public static function getName() {
        return "Booking line";
    }

    public static function getDescription() {
        return "Booking lines describe the products and quantities that are part of a booking.";
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Line name relates to its product.',
                'function'          => 'sale\booking\BookingLine::getDisplayName',
                'store'             => true
            ],

            'booking_line_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingLineGroup',
                'description'       => 'Group the line relates to (in turn, groups relate to their booking).',
                'ondelete'          => 'cascade',        // delete line when parent group is deleted
                'required'          => true              // must be set at creation
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Booking',
                'description'       => 'The booking the line relates to (for consistency, lines should be accessed using the group they belong to).',
                'required'          => true
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Product',
                'description'       => 'The product (SKU) the line relates to.'
            ],

            'price_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\price\Price',
                'description'       => 'The price the line relates to (retrieved by price list).',
                'onchange'          => 'sale\booking\BookingLine::onchangePriceId'
            ],

            'consumptions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Consumption',
                'foreign_field'     => 'booking_line_id',
                'description'       => 'Consumptions related to the booking line.',
                'ondetach'          => 'delete'
            ],

            'price_adapters_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingPriceAdapter',
                'foreign_field'     => 'booking_line_id',
                'description'       => 'All price adapters: auto and manual discounts applied on the line.',
                'onchange'          => 'sale\booking\BookingLine::onchangePriceAdaptersIds'
            ],

            'auto_discounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingPriceAdapter',
                'foreign_field'     => 'booking_line_id',
                'domain'            => ['is_manual_discount', '=', false],
                'description'       => 'Price adapters relating to auto discounts only.'
            ],

            'manual_discounts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingPriceAdapter',
                'foreign_field'     => 'booking_line_id',
                'domain'            => ['is_manual_discount', '=', true],
                'description'       => 'Price adapters relating to manual discounts only.'
            ],

            'qty' => [
                'type'              => 'float',
                'description'       => 'Quantity of product items for the line.',
                'default'           => 1.0,
                'onchange'          => 'sale\booking\BookingLine::onchangeQty'
            ],

            'has_own_qty' => [
                'type'              => 'boolean',
                'description'       => 'Set according to related pack line.',
                'default'           => false
            ],

            'has_own_duration' => [
                'type'              => 'boolean',
                'description'       => 'Set according to related pack line.',
                'default'           => false
            ],

            'own_duration' => [
                'type'              => 'integer',
                'description'       => "Self assigned duration, in days (from pack line).",
                'visible'           => ['has_own_duration', '=', true]
            ],

            'order' => [
                'type'              => 'integer',
                'description'       => 'Order by which the line have to be sorted when presented visually.',
                'default'           => 1
            ],

            'payment_mode' => [
                'type'              => 'string',
                'selection'         => [
                    'invoice',                  // consumption has to be added to an invoice
                    'cash',                     // consumption is paid in cash (money or bank transfer)
                    'free'                      // related consumption is a gift
                ],
                'default'           => 'invoice',
                'description'       => 'The way the line is intended to be paid.',
            ],

            'is_contractual' => [
                'type'              => 'boolean',
                'description'       => 'Is the line part of the original contract (or added afterward)?',
                'default'           => false
            ],

            'free_qty' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Free quantity.',
                'function'          => 'sale\booking\BookingLine::getFreeQty',
                'store'             => true
            ],

            'discount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => 'Total amount of discount to apply, if any.',
                'function'          => 'sale\booking\BookingLine::getDiscount',
                'store'             => true
            ],

            'unit_price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Tax-excluded unit price (with automated discounts applied).',
                'function'          => 'sale\booking\BookingLine::getUnitPrice',
                'store'             => true,
                'onchange'          => 'sale\booking\BookingLine::onchangeUnitPrice'
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Total tax-excluded price of the line (computed).',
                'function'          => 'sale\booking\BookingLine::getTotal',
                'store'             => true
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Final tax-included price (computed).',
                'function'          => 'sale\booking\BookingLine::getPrice',
                'store'             => true
            ],

            'vat_rate' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'description'       => 'VAT rate that applies to this line.',
                'function'          => 'sale\booking\BookingLine::getVatRate',
                'store'             => true,
                'onchange'          => 'sale\booking\BookingLine::onchangeVatRate'
            ]
        ];
    }


    /**
     * For BookingLines the display name is the name of the product it relates to.
     *
     */
    public static function getDisplayName($om, $oids, $lang) {
        $result = [];
        $res = $om->read(get_called_class(), $oids, ['product_id.name'], $lang);
        foreach($res as $oid => $odata) {
            $result[$oid] = $odata['product_id.name'];
        }
        return $result;
    }

    public static function onchangeQty($om, $oids, $lang) {
        $om->write(__CLASS__, $oids, ['price' => null, 'total' => null]);
    }

    public static function onchangeUnitPrice($om, $oids, $lang) {
        $om->write(__CLASS__, $oids, ['price' => null, 'total' => null]);
    }

    public static function onchangeVatRate($om, $oids, $lang) {
        $om->write(__CLASS__, $oids, ['price' => null]);
    }

    public static function onchangePriceId($om, $oids, $lang) {
        // reset computed fields related to price
        $om->write(__CLASS__, $oids, ['unit_price' => null, 'price' => null, 'vat_rate' => null, 'total' => null, 'discount' => null]);
    }

    public static function onchangePriceAdaptersIds($om, $oids, $lang) {
        // reset computed fields related to price
        $om->write(__CLASS__, $oids, ['unit_price' => null, 'price' => null, 'vat_rate' => null, 'total' => null, 'discount' => null, 'free_qty' => null ]);
    }


    /**
     * This method is called upon change on: qty
     */
    public static function _createConsumptions($om, $oids, $lang) {
        trigger_error("QN_DEBUG_ORM::calling sale\booking\BookingLine:_createConsumptions", QN_REPORT_DEBUG);
        // #todo
    }

    /**
     * Compute the VAT excl. unit price of the line, with automated discounts applied.
     *
     */
    public static function getUnitPrice($om, $oids, $lang) {
        $result = [];
        $lines = $om->read(get_called_class(), $oids, [
                    'price_id.price',
                    'auto_discounts_ids'
                ]);
        foreach($lines as $oid => $odata) {
            $price = 0;
            if($odata['price_id.price']) {
                $price = (float) $odata['price_id.price'];
            }
            $disc_percent = 0.0;
            $disc_value = 0.0;
            if($odata['auto_discounts_ids']) {
                $adapters = $om->read('sale\booking\BookingPriceAdapter', $odata['auto_discounts_ids'], ['type', 'value', 'discount_id.discount_list_id.rate_max']);
                if($adapters > 0) {
                    foreach($adapters as $aid => $adata) {
                        if($adata['type'] == 'amount') {
                            $disc_value += $adata['value'];
                        }
                        else if($adata['type'] == 'percent') {
                            if($adata['discount_id.discount_list_id.rate_max'] && ($disc_percent + $adata['value']) > $adata['discount_id.discount_list_id.rate_max']) {
                                $disc_percent = $adata['discount_id.discount_list_id.rate_max'];
                            }
                            else {
                                $disc_percent += $adata['value'];
                            }
                        }
                    }
                }
            }
            $result[$oid] = round(($price * (1-$disc_percent)) - $disc_value, 2);
        }
        return $result;
    }


    public static function getFreeQty($om, $oids, $lang) {
        $result = [];
        $lines = $om->read(get_called_class(), $oids, ['auto_discounts_ids','manual_discounts_ids']);

        foreach($lines as $oid => $odata) {
            $free_qty = 0;

            $adapters = $om->read('sale\booking\BookingPriceAdapter', $odata['auto_discounts_ids'], ['type', 'value']);
            foreach($adapters as $aid => $adata) {
                if($adata['type'] == 'freebie') {
                    $free_qty += $adata['value'];
                }
            }
            // check additional manual discounts
            $discounts = $om->read('sale\booking\BookingPriceAdapter', $odata['manual_discounts_ids'], ['type', 'value']);
            foreach($discounts as $aid => $adata) {
                if($adata['type'] == 'freebie') {
                    $free_qty += $adata['value'];
                }
            }
            $result[$oid] = $free_qty;
        }
        return $result;
    }

    public static function getDiscount($om, $oids, $lang) {
        $result = [];
        $lines = $om->read(get_called_class(), $oids, ['total', 'unit_price', 'qty']);

        foreach($lines as $oid => $line) {
            $price = $line['unit_price'] * $line['qty'];
            $result[$oid] = ($price)?(1-$line['total']/$price):0;
        }
        return $result;
    }

    /**
     * Get final tax-included price of the line.
     *
     */
    public static function getPrice($om, $oids, $lang) {
        $result = [];

        $lines = $om->read(get_called_class(), $oids, ['total','vat_rate']);

        foreach($lines as $oid => $odata) {
            $price = (float) $odata['total'];
            $vat = (float) $odata['vat_rate'];

            $result[$oid] = round( $price  * (1.0 + $vat), 2);
        }
        return $result;
    }

    /**
     * Get total tax-excluded price of the line, with all discounts applied.
     *
     */
    public static function getTotal($om, $oids, $lang) {
        $result = [];
        $lines = $om->read(get_called_class(), $oids, [
                    'qty',
                    'unit_price',
                    'auto_discounts_ids',
                    'manual_discounts_ids',
                    'payment_mode',
                    'booking_line_group_id'
                ]);

        $booking_line_groups_ids = [];

        foreach($lines as $oid => $odata) {
            $booking_line_groups_ids[] = $odata['booking_line_group_id'];

            if($odata['payment_mode'] == 'free') {
                $result[$oid] = 0;
                continue;
            }

            $price = (float) $odata['unit_price'];
            $disc_percent = 0.0;
            $disc_value = 0.0;
            $qty = intval($odata['qty']);
            // apply freebies from auto-discounts
            $adapters = $om->read('sale\booking\BookingPriceAdapter', $odata['auto_discounts_ids'], ['type', 'value']);
            foreach($adapters as $aid => $adata) {
                // amount and percent discounts have been applied in ::getUnitPrice()
                if($adata['type'] == 'freebie') {
                    $qty -= $adata['value'];
                }
            }
            // apply additional manual discounts
            $discounts = $om->read('sale\booking\BookingPriceAdapter', $odata['manual_discounts_ids'], ['type', 'value']);
            foreach($discounts as $aid => $adata) {
                if($adata['type'] == 'amount') {
                    $disc_value += $adata['value'];
                }
                else if($adata['type'] == 'percent') {
                    $disc_percent += $adata['value'];
                }
                else if($adata['type'] == 'freebie') {
                    $qty -= $adata['value'];
                }
            }
            // apply discount amount VAT excl.
            $price = ($price * (1.0-$disc_percent)) - $disc_value;

            $result[$oid] = $price * $qty;
        }

        // reset parent group total price
        $om->write('sale\booking\BookingLineGroup', array_unique($booking_line_groups_ids), ['total' => null, 'price' => null]);

        return $result;
    }


    public static function getVatRate($om, $oids, $lang) {
        $result = [];
        $lines = $om->read(get_called_class(), $oids, ['price_id.accounting_rule_id.vat_rule_id.rate']);
        foreach($lines as $oid => $odata) {
            $result[$oid] = floatval($odata['price_id.accounting_rule_id.vat_rule_id.rate']);
        }
        return $result;
    }

    public static function getConstraints() {
        return [
            'qty' =>  [
                'lte_zero' => [
                    'message'       => 'Quantity must be a positive value.',
                    'function'      => function ($qty, $values) {
                        return ($qty > 0);
                    }
                ]
            ]

        ];
    }
}