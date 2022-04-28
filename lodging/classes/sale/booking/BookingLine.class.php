<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking;


class BookingLine extends \sale\booking\BookingLine {

    public static function getName() {
        return "Booking line";
    }

    public static function getDescription() {
        return "Booking lines describe the products and quantities that are part of a booking.";
    }

    public static function getColumns() {
        return [

            'qty' => [
                'type'              => 'float',
                'description'       => 'Quantity of product items for the line.',
                'onchange'          => 'lodging\sale\booking\BookingLine::onchangeQty',
                'default'           => 1.0
            ],

            'is_accomodation' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Line relates to an accomodation (from product_model).',
                'function'          => 'lodging\sale\booking\BookingLine::getIsAccomodation',
                'store'             => true
            ],

            'is_meal' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'Line relates to a meal (from product_model).',
                'function'          => 'lodging\sale\booking\BookingLine::getIsMeal',
                'store'             => true
            ],

            'qty_accounting_method' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => 'Quantity accounting method (from product_model).',
                'function'          => 'lodging\sale\booking\BookingLine::getQtyAccountingMethod',
                'store'             => true
            ],

            'qty_vars' => [
                'type'              => 'string',
                'description'       => 'JSON array holding qty variation deltas (for \'by person\' products), if any.',
                'onchange'          => 'lodging\sale\booking\BookingLine::onchangeQtyVars'
            ],

            'is_autosale' => [
                'type'              => 'boolean',
                'description'       => 'Does the line relate to an autosale product?',
                'default'           => false
            ],

            'booking_line_group_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\BookingLineGroup',
                'description'       => 'Group the line relates to (in turn, groups relate to their booking).',
                'required'          => true,             // must be set at creation
                'onchange'          => 'lodging\sale\booking\BookingLine::onchangeBookingLineGroupId',
                'ondelete'          => 'cascade'         // delete line when parent group is deleted
            ],

            'booking_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\booking\Booking',
                'description'       => 'The booking the line relates to (for consistency, lines should be accessed using the group they belong to).',
                'required'          => true,
                'ondelete'          => 'cascade'         // delete line when parent booking is deleted
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\sale\catalog\Product',
                'description'       => 'The product (SKU) the line relates to.',
                'onchange'          => 'lodging\sale\booking\BookingLine::onchangeProductId'
            ],

            'consumptions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\Consumption',
                'foreign_field'     => 'booking_line_id',
                'description'       => 'Consumptions related to the booking line.',
                'ondetach'          => 'delete'
            ],

            'rental_unit_assignments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\BookingLineRentalUnitAssignement',
                'foreign_field'     => 'booking_line_id',
                'description'       => "The rental units the line is assigned to.",
                'ondetach'          => 'delete',
                'visible'           => ['is_accomodation', '=', true]
            ],

            'price_adapters_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\BookingPriceAdapter',
                'foreign_field'     => 'booking_line_id',
                'description'       => 'Price adapters holding the manual discounts applied on the line.',
                'onchange'          => 'sale\booking\BookingLine::onchangePriceAdaptersIds'
            ]

        ];
    }

    public static function getIsAccomodation($om, $oids, $lang) {
        trigger_error("QN_DEBUG_ORM::calling lodging\sale\booking\BookingLine:getIsAccomodation", QN_REPORT_DEBUG);

        $result = [];
        $lines = $om->read(__CLASS__, $oids, [
            'product_id.product_model_id.is_accomodation'
        ]);
        if($lines > 0 && count($lines)) {
            foreach($lines as $oid => $odata) {
                $result[$oid] = $odata['product_id.product_model_id.is_accomodation'];
            }
        }
        return $result;
    }

    public static function getIsMeal($om, $oids, $lang) {
        trigger_error("QN_DEBUG_ORM::calling lodging\sale\booking\BookingLine:getIsMeal", QN_REPORT_DEBUG);

        $result = [];
        $lines = $om->read(__CLASS__, $oids, [
            'product_id.product_model_id.is_meal'
        ]);
        if($lines > 0 && count($lines)) {
            foreach($lines as $oid => $odata) {
                $result[$oid] = $odata['product_id.product_model_id.is_meal'];
            }
        }
        return $result;
    }

    public static function getQtyAccountingMethod($om, $oids, $lang) {
        trigger_error("QN_DEBUG_ORM::calling lodging\sale\booking\BookingLine:getQtyAccountingMethod", QN_REPORT_DEBUG);

        $result = [];
        $lines = $om->read(__CLASS__, $oids, [
            'product_id.product_model_id.qty_accounting_method'
        ]);
        if($lines > 0 && count($lines)) {
            foreach($lines as $oid => $odata) {
                $result[$oid] = $odata['product_id.product_model_id.qty_accounting_method'];
            }
        }
        return $result;
    }

    /**
     *
     * New group assignement : should (only) be called upon creation
     * Adapt quantity based on product type and parent group config
     */
    public static function onchangeBookingLineGroupId($om, $oids, $lang) {
        trigger_error("QN_DEBUG_ORM::calling lodging\sale\booking\BookingLine:onchangeBookingLineGroupId", QN_REPORT_DEBUG);
        $lines = $om->read(__CLASS__, $oids, ['booking_line_group_id.has_pack', 'product_id.product_model_id.qty_accounting_method'], $lang);

        foreach($lines as $lid => $line) {
            //#todo - is this necessary ?
        }

    }

    /**
     * Update the price_id according to booking line settings.
     *
     * This is called at booking line creation.
     */
    public static function onchangeProductId($om, $oids, $lang) {
        trigger_error("QN_DEBUG_ORM::calling lodging\sale\booking\BookingLine:onchangeProductId", QN_REPORT_DEBUG);

        // reset computed fields related to product model
        $om->write(__CLASS__, $oids, ['name' => null, 'qty_accounting_method' => null, 'is_accomodation' => null, 'is_meal' => null]);

        // resolve price_id for new product_id
        $om->call(__CLASS__, '_updatePriceId', $oids, $lang);

        // we might change the product_id but not the quantity : we cannot know if qty is changed during the same operation
        // #memo - in ORM, a check is performed on the onchange methods to prevent handling same event multiple times

        // quantity might depends on the product model AND the sojourn (nb_pers, nb_nights)
        $lines = $om->read(__CLASS__, $oids, [
            'product_id.product_model_id.booking_type',
            'booking_id',
            'qty',
            'has_own_qty',
            'booking_line_group_id.nb_pers',
            'booking_line_group_id.nb_nights',
            'is_accomodation',
            'is_meal',
            'qty_accounting_method'
        ], $lang);

        foreach($lines as $lid => $line) {
            // if model of chosen product has a non-generic booking type, update the booking of the line accordingly
            if(isset($line['product_id.product_model_id.booking_type']) && $line['product_id.product_model_id.booking_type'] != 'general') {
                $om->write('lodging\sale\booking\Booking', $line['booking_id'], ['type' => $line['product_id.product_model_id.booking_type']]);
            }
            $qty = $line['qty'];
            if(!$line['has_own_qty']) {
                if($line['qty_accounting_method'] == 'accomodation') {
                    // lines having a product 'by accomodation' have a qty assigned to the 'duration' of the sojourn
                    // which should have been stored in the nb_nights field
                    $qty = $line['booking_line_group_id.nb_nights'];
                }
                else if($line['qty_accounting_method'] == 'person') {
                    // lines having a product 'by person' have a qty assigned to the 'duration' x 'nb_pers' of the sojourn
                    // which should have been stored in the nb_pers field
                    if($line['is_meal'] || $line['is_accomodation']) {
                        $qty = $line['booking_line_group_id.nb_pers'] * max(1, $line['booking_line_group_id.nb_nights']);
                    }
                    else {
                        $qty = $line['booking_line_group_id.nb_pers'];
                    }
                }
            }

            if($qty != $line['qty'] || $line['qty_accounting_method'] == 'accomodation') {
                // make sure qty is updated in order to re-assign the rental units
                $om->write(__CLASS__, $lid, ['qty' => $qty]);
            }
        }

    }

    public static function onchangeQtyVars($om, $oids, $lang) {
        trigger_error("QN_DEBUG_ORM::calling lodging\sale\booking\BookingLine:onchangeQtyVars", QN_REPORT_DEBUG);
        $lines = $om->read(__CLASS__, $oids, ['booking_line_group_id.nb_pers','qty_vars']);

        if($lines > 0) {
            // set quantities according to qty_vars arrays
            foreach($lines as $lid => $line) {
                $nb_pers = $line['booking_line_group_id.nb_pers'];
                // qty_vars should be a JSON array holding a series of deltas
                $qty_vars = json_decode($line['qty_vars']);
                if($qty_vars) {
                    $qty = 0;
                    foreach($qty_vars as $variation) {
                        $qty += $nb_pers + $variation;
                    }
                    $om->write(__CLASS__, $lid, ['qty' => $qty]);
                }
                else {
                    $om->call(__CLASS__, '_updateQty', $oids, $lang);
                }
            }
        }
    }


    /**
     * Update the quantity of products.
     *
     * This handler is called at booking line creation and is in charge of updating the rental units assignments related to the line.
     */
    public static function onchangeQty($om, $oids, $lang) {
        trigger_error("QN_DEBUG_ORM::calling lodging\sale\booking\BookingLine:onchangeQty", QN_REPORT_DEBUG);

        // try to auto-assign a rental_unit

        $lines = $om->read(__CLASS__, $oids, [
            'booking_id', 'booking_id.center_id',
            'booking_line_group_id',
            'booking_line_group_id.nb_pers',
            'booking_line_group_id.date_from',
            'booking_line_group_id.date_to',
            'product_id',
            'product_id.product_model_id',
            'qty_accounting_method',
            'is_accomodation',
            'rental_unit_assignments_ids'
        ], $lang);

        // drop lines that do not relate to accomodations
        $lines = array_filter($lines, function($a) { return $a['is_accomodation']; });

        $booking_line_groups_ids = [];
        // there is at least one accomodation
        if(count($lines)) {

            // read all related product models at once
            $product_models_ids = array_map(function($oid) use($lines) {return $lines[$oid]['product_id.product_model_id'];}, array_keys($lines));
            $product_models = $om->read('lodging\sale\catalog\ProductModel', $product_models_ids, [
                'rental_unit_assignement',
                'capacity',
                'rental_unit_category_id',
                'rental_unit_id'
            ], $lang);

            foreach($lines as $lid => $line) {
                $booking_line_groups_ids[] = $line['booking_line_group_id'];

                // remove all previous rental_unit assignements
                $om->write(__CLASS__, $lid, ['rental_unit_assignments_ids' => array_map(function($a) { return "-$a";}, $line['rental_unit_assignments_ids'])]);

                $center_id = $line['booking_id.center_id'];
                $nb_pers = $line['booking_line_group_id.nb_pers'];
                $date_from = $line['booking_line_group_id.date_from'];
                $date_to = $line['booking_line_group_id.date_to'];

                $rental_unit_assignement = $product_models[$line['product_id.product_model_id']]['rental_unit_assignement'];

                // find available rental units (sorted by capacity desc)
                $rental_units_ids = Consumption::_getAvailableRentalUnits($om, $center_id, $line['product_id'], $date_from, $date_to);
                // retrieve rental units capacities
                $rental_units = [];
                $assigned_rental_units = [];
                if($rental_units_ids > 0 && count($rental_units_ids)) {
                    $rental_units = array_values($om->read('lodging\realestate\RentalUnit', $rental_units_ids, ['id', 'capacity']));
                }

                for($i = 0, $n = count($rental_units), $available_capacity = 0; $i < $n; ++$i) {
                    $tmp_rental_units = array_slice($rental_units, $i);
                    $sum = array_reduce($tmp_rental_units, function ($c, $a) { return $c + $a['capacity'];}, 0);
                    if($sum < $nb_pers) {
                        break;
                    }
                    // if delta at previous index is lower than remaining list, prefer a single unit
                    if($available_capacity >= $nb_pers && abs($nb_pers-$sum) <= abs($nb_pers-$available_capacity)) {
                        // start using rental units at previous index
                        --$i;
                        break;
                    }
                    $available_capacity = $sum;
                }

                $remaining = $nb_pers;

                if($available_capacity >= $nb_pers) {

                    if($rental_unit_assignement == 'unit') {
                        /*
                            Assign to a specific rental unit
                        */
                        $rental_unit = reset($rental_units);
                        $rental_unit['assigned'] = $remaining;
                        $assigned_rental_units[] = $rental_unit;
                    }
                    else {
                        // min serie for available capacity starts from max(0, i-1)
                        for($j = max(0, $i-1); $j < $n; ++$j) {
                            $rental_unit = $rental_units[$j];
                            $assigned = min($rental_unit['capacity'], $remaining);

                            $rental_unit['assigned'] = $assigned;
                            $assigned_rental_units[] = $rental_unit;

                            $remaining -= $assigned;
                            if($remaining <= 0) break;
                        }
                    }
                }

                if($remaining > 0) {
                    // no availability !
                    $assignement = [
                        'booking_id'        => $line['booking_id'],
                        'booking_line_id'   => $lid,
                        'rental_unit_id'    => 0,
                        'qty'               => $nb_pers
                    ];
                    trigger_error("QN_DEBUG_ORM::no availability", QN_REPORT_DEBUG);
                    $om->create('lodging\sale\booking\BookingLineRentalUnitAssignement', $assignement);
                }
                else {
                    foreach($assigned_rental_units as $rental_unit) {
                        $assignement = [
                            'booking_id'        => $line['booking_id'],
                            'booking_line_id'   => $lid,
                            'rental_unit_id'    => $rental_unit['id'],
                            'qty'               => $rental_unit['assigned']
                        ];
                        trigger_error("QN_DEBUG_ORM::assigning {$rental_unit['id']}", QN_REPORT_DEBUG);
                        $om->create('lodging\sale\booking\BookingLineRentalUnitAssignement', $assignement);
                    }
                }
            }
        }

        // reset total price
        $om->write(__CLASS__, $oids, ['total' => null, 'price' => null]);
    }


    /**
     * Update the quantity according to parent group (pack_id, nb_pers, nb_nights) and variation array.
     * This method is triggered on fields update from BookingLineGroup.
     *
     */
    public static function _updateQty($om, $oids, $lang) {
        trigger_error("QN_DEBUG_ORM::calling lodging\sale\booking\BookingLine:_updateQty", QN_REPORT_DEBUG);

        $lines = $om->read(__CLASS__, $oids, [
            'has_own_qty',
            'qty_vars',
            'booking_line_group_id.nb_pers',
            'booking_line_group_id.nb_nights',
            'product_id.product_model_id.qty_accounting_method',
            'product_id.product_model_id.is_accomodation',
            'product_id.product_model_id.is_meal',
            'product_id.product_model_id.has_duration',
            'product_id.product_model_id.duration'
        ], $lang);

        if($lines > 0) {
            foreach($lines as $lid => $line) {
                if(!$line['has_own_qty']) {
                    if($line['product_id.product_model_id.qty_accounting_method'] == 'accomodation') {
                        $om->write('lodging\sale\booking\BookingLine', $lid, ['qty' => $line['booking_line_group_id.nb_nights']]);
                    }
                    else if($line['product_id.product_model_id.qty_accounting_method'] == 'person') {
                        if(!$line['qty_vars']) {
                            $factor = 1;
                            if($line['product_id.product_model_id.has_duration']) {
                                $factor = $line['product_id.product_model_id.duration'];
                            }
                            else if($line['product_id.product_model_id.is_accomodation'] || $line['product_id.product_model_id.is_meal'] ) {
                                $factor = max(1, $line['booking_line_group_id.nb_nights']);
                            }
                            $om->write(__CLASS__, $lid, ['qty' => $line['booking_line_group_id.nb_pers'] * $factor]);
                        }
                        else {
                            $qty_vars = json_decode($line['qty_vars']);
                            // qty_vars is set and valid
                            if($qty_vars) {
                                $factor = $line['booking_line_group_id.nb_nights'];
                                if($line['product_id.product_model_id.has_duration']) {
                                    $factor = $line['product_id.product_model_id.duration'];
                                }
                                $diff = $factor - count($qty_vars);
                                if($diff > 0) {
                                    $qty_vars = array_pad($qty_vars, $factor, 0);
                                }
                                else if($diff < 0) {
                                    $qty_vars = array_slice($qty_vars, 0, $factor);
                                }
                                $om->write(__CLASS__, $lid, ['qty_vars' => json_encode($qty_vars)]);
                                // will trigger onchangeQtyVar which will update  qty
                            }
                        }
                    }
                }
                else {
                    // own quantity has been assigned in onchangeProductId
                }
            }
        }
    }


    /**
     * Try to assign the price_id according to the current product_id.
     * Resolve the price from the first applicable price list, based on booking_line_group settings and booking center.
     * If found price list is pending, mark the booking as TBC.
     *
     * _updatePriceId is also called upon booking_id.center_id and booking_line_group_id.date_from changes.
     *
     * @param \equal\orm\ObjectManager $om
     */
    public static function _updatePriceId($om, $oids, $lang) {
        trigger_error("QN_DEBUG_ORM::calling lodging\sale\booking\BookingLine:_updatePriceId", QN_REPORT_DEBUG);

        $lines = $om->read(get_called_class(), $oids, [
            'booking_line_group_id.date_from',
            'product_id',
            'booking_id',
            'booking_id.center_id.price_list_category_id'
        ]);

        foreach($lines as $line_id => $line) {
            /*
                Find the Price List that matches the criteria from the booking with the shortest duration
            */
            $price_lists_ids = $om->search(
                'sale\price\PriceList',
                [
                    ['price_list_category_id', '=', $line['booking_id.center_id.price_list_category_id']],
                    ['is_active', '=', true]
                ],
                ['duration' => 'asc']
            );

            $found = false;

            if($price_lists_ids > 0 && count($price_lists_ids)) {
                /*
                    Search for a matching Price within the found Price List
                */
                foreach($price_lists_ids as $price_list_id) {
                    // there should be exactly one matching price
                    $prices_ids = $om->search('sale\price\Price', [ ['price_list_id', '=', $price_list_id], ['product_id', '=', $line['product_id']] ]);
                    if($prices_ids > 0 && count($prices_ids)) {
                        /*
                            Assign found Price to current line
                        */
                        $found = true;
                        $om->write(get_called_class(), $line_id, ['price_id' => $prices_ids[0]]);

                        // update booking depending on the status of the pricelist
                        $pricelists = $om->read('sale\price\PriceList', $price_list_id, [ 'status' ]);
                        if($pricelists > 0) {
                            $pricelist = reset($pricelists);
                            if($pricelist['status'] == 'pending') {
                                $om->write('sale\booking\Booking', $line['booking_id'], ['is_price_tbc' => true]);
                            }
                        }
                        break;
                    }
                }
            }
            if(!$found) {
                $om->write(get_called_class(), $line_id, ['price_id' => null, 'vat_rate' => 0, 'unit_price' => 0, 'price' => 0]);
                $date = date('Y-m-d', $line['booking_line_group_id.date_from']);
                trigger_error("QN_DEBUG_ORM::no matching price list found for product {$line['product_id']} for date {$date}", QN_REPORT_ERROR);
            }
        }
    }

    /**
     *
     * This method is called upon setting booking status to 'option' or 'confirmed' (#see `option.php`)
     * #memo - consumptions are used in the planning.
     *
     */
    public static function _createConsumptions($om, $oids, $lang) {
        trigger_error("QN_DEBUG_ORM::calling lodging\sale\booking\BookingLine:_createConsumptions", QN_REPORT_DEBUG);

        /*
            get in-memory list of consumptions for all lines
        */
        $consumptions = $om->call(__CLASS__, '_getResultingConsumptions', $oids, $lang);

        /*
            create consumptions objects
        */

        // map of consumptions ids for each booking_line_id
        $lines_consumptions_ids = [];
        foreach($consumptions as $consumption) {

            $cid = $om->create('lodging\sale\booking\Consumption', $consumption, $lang);
            if($cid > 0) {
                $booking_line_id = $consumption['booking_line_id'];
                if(!isset($lines_consumptions_ids[$booking_line_id])) {
                    $lines_consumptions_ids[$booking_line_id] = [];
                }
                $lines_consumptions_ids[$booking_line_id][] = $cid;
            }
        }

        foreach($lines_consumptions_ids as $lid => $consumptions_ids) {
            $om->write(__CLASS__, $lid, ['consumptions_ids' => $consumptions_ids ]);
        }

    }



    /**
     * Process targeted BookingLines to create an in-memory list of consumptions objects.
     *
     */
    public static function _getResultingConsumptions($om, $oids, $lang) {
        trigger_error("QN_DEBUG_ORM::calling lodging\sale\booking\BookingLine:_getResultingConsumptions", QN_REPORT_DEBUG);

        // resulting consumptions objects
        $consumptions = [];

        $lines = $om->read(__CLASS__, $oids, [
            'product_id', 'qty', 'qty_vars',
            'booking_id', 'booking_id.center_id',
            'booking_line_group_id', 'booking_line_group_id.nb_pers', 'booking_line_group_id.nb_nights', 'booking_line_group_id.date_from',
            'consumptions_ids',
            'product_id.product_model_id'
        ], $lang);

        // read all related product models at once
        $product_models_ids = array_map(function($oid) use($lines) {return $lines[$oid]['product_id.product_model_id'];}, array_keys($lines));
        $product_models = $om->read('lodging\sale\catalog\ProductModel', $product_models_ids, [
            'type',
            'service_type',
            'schedule_offset',
            'schedule_type',
            'schedule_default_value',
            'qty_accounting_method',
            'has_duration',
            'duration',
            'is_accomodation',
            'is_meal'
        ]);

        if($lines > 0 && count($lines)) {
            foreach($lines as $lid => $line) {
                /*
                    Reset consumptions (updating consumptions_ids will trigger ondetach event)
                */
                $om->write(__CLASS__, $lid, ['consumptions_ids' => array_map(function($a) { return "-$a";}, $line['consumptions_ids'])]);

                if($line['qty'] <= 0) continue;

                $nb_pers    = $line['booking_line_group_id.nb_pers'];
                $nb_nights  = $line['booking_line_group_id.nb_nights'];

                /*
                    Create consumptions according to line product and quantity
                */
                $product_type = $product_models[$line['product_id.product_model_id']]['type'];
                $service_type = $product_models[$line['product_id.product_model_id']]['service_type'];
                $has_duration = $product_models[$line['product_id.product_model_id']]['has_duration'];

                // consumptions are schedulable services
                if($product_type == 'service' && $service_type == 'schedulable') {

                    // retrieve default time for consumption
                    list($hour_from, $minute_from, $hour_to, $minute_to) = [12, 0, 13, 0];
                    $schedule_default_value = $product_models[$line['product_id.product_model_id']]['schedule_default_value'];
                    if(strpos($schedule_default_value, ':')) {
                        $parts = explode('-', $schedule_default_value);
                        list($hour_from, $minute_from) = explode(':', $parts[0]);
                        list($hour_to, $minute_to) = [$hour_from+1, $minute_from];
                        if(count($parts) > 1) {
                            list($hour_to, $minute_to) = explode(':', $parts[1]);
                        }
                    }
                    $schedule_from  = $hour_from * 3600 + $minute_from * 60;
                    $schedule_to    = $hour_to * 3600 + $minute_to * 60;

                    $is_meal = $product_models[$line['product_id.product_model_id']]['is_meal'];
                    // is_accomodation is used as "is it attached to a rental_unit (accomodation or other)"
                    $is_rental_unit = $product_models[$line['product_id.product_model_id']]['is_accomodation'];
                    $qty_accounting_method = $product_models[$line['product_id.product_model_id']]['qty_accounting_method'];

                    // number of consumptions differs for accomodations (rooms are occupied nb_nights + 1 until sometime in the morning)
                    $nb_products = $nb_nights;
                    $nb_times = $nb_pers;

                    if($is_rental_unit) {
                        // #todo - we should check if the rental unit is an acoomodation
                        ++$nb_products; // checkout is done the day following the last night

                        /*
                            retrieve assigned rental units
                            rental units have been assigned during booking in BookingLineRentalUnitAssignement objects
                        */

                        $assignments_ids = $om->search('lodging\sale\booking\BookingLineRentalUnitAssignement', ['booking_line_id', '=', $lid]);
                        $rental_units_assignments = $om->read('lodging\sale\booking\BookingLineRentalUnitAssignement', $assignments_ids, ['rental_unit_id','qty']);

                    }
                    else if($has_duration) {
                        $nb_products = $product_models[$line['product_id.product_model_id']]['duration'];
                    }

                    if($qty_accounting_method == 'accomodation') {
                        $nb_times = 1;  // an accomodation is accounted independently from the number of persons
                    }

                    list($day, $month, $year) = [ date('j', $line['booking_line_group_id.date_from']), date('n', $line['booking_line_group_id.date_from']), date('Y', $line['booking_line_group_id.date_from']) ];
                    // fetch the offset, in days, for the scheduling
                    $offset = $product_models[$line['product_id.product_model_id']]['schedule_offset'];

                    $days_nb_times = array_fill(0, $nb_products, $nb_times);
                    if( $qty_accounting_method == 'person' && ($nb_times * $nb_products) != $line['qty']) {
                        // $nb_times varies from one day to another : load specific days_nb_times array
                        $qty_vars = json_decode($line['qty_vars']);
                        // qty_vars is set and valid
                        if($qty_vars) {
                            $i = 0;
                            foreach($qty_vars as $variation) {
                                $days_nb_times[$i] = $nb_times + $variation;
                                ++$i;
                            }
                            // handle last day for acccomodations
                            if($is_rental_unit) {
                                // #todo - we should check if related rental_unit is an accomodation
                                $days_nb_times[$i] = $nb_times + $variation;
                            }
                        }
                    }

                    /*
                        retrieve all involved rental units (limited to 2 levels above and 2 levels below)
                    */
                    $rental_units = [];
                    $rental_units_ids = [];
                    if($rental_units_assignments > 0) {
                        $rental_units_ids = array_map(function ($a) { return $a['rental_unit_id']; }, $rental_units_assignments);
                        // read first level
                        $units = $om->read('lodging\realestate\RentalUnit', $rental_units_ids, ['parent_id', 'children_ids']);
                        if($units > 0) {
                            foreach($units as $uid => $unit) {
                                $rental_units_ids[] = $uid;

                                if($unit['parent_id'] > 0) {
                                    $rental_units_ids[] = $unit['parent_id'];
                                }
                                if(count($unit['children_ids'])) {
                                    $rental_units_ids = array_merge($rental_units_ids, $unit['children_ids']);
                                }
                            }
                        }
                        // read second level
                        $rental_units = $om->read('lodging\realestate\RentalUnit', $rental_units_ids, ['parent_id', 'children_ids', 'can_partial_rent', 'is_accomodation']);
                    }


                    // $nb_products represent each day of the stay
                    for($i = 0; $i < $nb_products; ++$i) {
                        $c_date = mktime(0, 0, 0, $month, $day+$i+$offset, $year);
                        $c_schedule_from = $schedule_from;
                        $c_schedule_to = $schedule_to;

                        // create as many consumptions as the number of rental units assigned to the line
                        if($is_rental_unit) {
                            // if day is not the arrival day
                            if($i > 0) {
                                $c_schedule_from = 0;               // midnight same day
                            }

                            if($i == $nb_nights) {                  // last day
                                $c_schedule_to = $schedule_to;
                            }
                            else {
                                $c_schedule_to = 24 * 3600;         // midnight next day
                            }

                            if($rental_units_assignments > 0) {
                                foreach($rental_units_assignments as $assignment) {
                                    $rental_unit_id = $assignment['rental_unit_id'];
                                    $consumption = [
                                        'booking_id'            => $line['booking_id'],
                                        'center_id'             => $line['booking_id.center_id'],
                                        'booking_line_group_id' => $line['booking_line_group_id'],
                                        'booking_line_id'       => $lid,
                                        'date'                  => $c_date,
                                        'schedule_from'         => $c_schedule_from,
                                        'schedule_to'           => $c_schedule_to,
                                        'product_id'            => $line['product_id'],
                                        'is_rental_unit'        => true,
                                        'is_meal'               => $is_meal,
                                        'rental_unit_id'        => $rental_unit_id,
                                        'qty'                   => $assignment['qty'],
                                        'type'                  => 'book'
                                    ];
                                    $consumptions[] = $consumption;

                                    // 1) recurse through children : all child units aer blocked as 'link'
                                    $children_ids = [];
                                    $children_stack = $rental_units[$rental_unit_id]['children_ids'];
                                    while(count($children_stack)) {
                                        $unit_id = array_pop($children_stack);
                                        $children_ids[] = $unit_id;
                                        if(isset($rental_units[$unit_id]) && $rental_units[$unit_id]['children_ids']) {
                                            foreach($units[$unit_id]['children_ids'] as $child_id) {
                                                $children_stack[] = $child_id;
                                            }
                                        }
                                    }
                                    $consumption['type'] = 'link';
                                    foreach($children_ids as $child_id) {
                                        $consumption['rental_unit_id'] = $child_id;
                                        $consumptions[] = $consumption;
                                    }
                                    // 2) loop through parents : if a parent has 'can_partial_rent', it is partially blocked as 'part', otherwise fully blocked as 'link'
                                    $parents_ids = [];
                                    $unit_id = $rental_unit_id;
                                    while( isset($rental_units[$unit_id]) ) {
                                        $parent_id = $rental_units[$unit_id]['parent_id'];
                                        if($parent_id > 0) {
                                            $parents_ids[] = $parent_id;
                                        }
                                        $unit_id = $parent_id;
                                    }
                                    foreach($parents_ids as $parent_id) {
                                        $consumption['type'] = ($rental_units[$parent_id]['can_partial_rent'])?'part':'link';
                                        $consumption['rental_unit_id'] = $parent_id;
                                        $consumptions[] = $consumption;
                                    }
                                }
                            }
                        }
                        // create a single consumption with the quantity set accordingly (may vary from one day to another)
                        else {
                            $consumptions[] = [
                                'booking_id'            => $line['booking_id'],
                                'center_id'             => $line['booking_id.center_id'],
                                'booking_line_group_id' => $line['booking_line_group_id'],
                                'booking_line_id'       => $lid,
                                'date'                  => $c_date,
                                'schedule_from'         => $c_schedule_from,
                                'schedule_to'           => $c_schedule_to,
                                'product_id'            => $line['product_id'],
                                'is_rental_unit'        => false,
                                'is_meal'               => $is_meal,
                                'qty'                   => $days_nb_times[$i],
                                'type'                  => 'book'
                            ];
                        }
                    }

                }
            }


        }

        return $consumptions;
    }



}