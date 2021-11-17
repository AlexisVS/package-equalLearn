<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\sale\booking;


class Booking extends \sale\booking\Booking {


    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Code to serve as reference (might not be unique)",
                'function'          => 'lodging\sale\booking\Booking::getDisplayName',
                'store'             => true,
                'readonly'          => true
            ],

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'domain'            => ['relationship', '=', 'customer'],
                'description'       => "The customer to whom the booking relates.",
                'required'          => true,
                'onchange'          => 'lodging\sale\booking\Booking::onchangeCustomerId'
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Center',
                'description'       => "The center to which the booking relates to.",
                'required'          => true,
                'onchange'          => 'lodging\sale\booking\Booking::onchangeCenterId'
            ],

            'contracts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\Contract',
                'foreign_field'     => 'booking_id',
                'sort'              => 'desc',
                'description'       => 'List of contacts related to the booking, if any.'
            ],

            'consumptions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\Consumption',
                'foreign_field'     => 'booking_id',
                'description'       => 'Consumptions related to the booking.'
            ],

            'booking_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\BookingLine',
                'foreign_field'     => 'booking_id',
                'description'       => 'Detailed consumptions of the booking.'
            ],

            'booking_lines_groups_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\BookingLineGroup',
                'foreign_field'     => 'booking_id',
                'description'       => 'Grouped consumptions of the booking.'
            ],

            'rental_unit_assignments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\BookingLineRentalUnitAssignement',
                'foreign_field'     => 'booking_id',
                'description'       => 'Rental units assignments related to the booking.'
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money',
                'function'          => 'lodging\sale\booking\Booking::getPrice',
                'description'       => 'Total price (vat incl.) of the booking.'
            ],

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'lodging\sale\booking\Funding',
                'foreign_field'     => 'booking_id',
                'description'       => 'Fundings that relate to the booking.',
                'ondetach'          => 'delete'
            ]

        ];
    }

    public static function getDisplayName($om, $oids, $lang) {
        $result = [];

        $bookings = $om->read(__CLASS__, $oids, ['center_id.group_code'], $lang);

        foreach($bookings as $oid => $booking) {

            $settings_ids = $om->search('core\Setting', [
                ['name', '=', 'booking.sequence.'.$booking['center_id.group_code']],
                ['package', '=', 'sale'],
                ['section', '=', 'booking']
            ]);

            if($settings_ids < 0 || !count($settings_ids)) {
                // unexpected error : misconfiguration (setting is missing)
                $result[$oid] = 0;
                continue;
            }

            $settings = $om->read('core\SettingValue', $settings_ids, ['value']);
            if($settings < 0 || count($settings) != 1) {
                // unexpected error : misconfiguration (no value for setting)
                $result[$oid] = 0;
                continue;
            }

            $setting = array_pop($settings);
            $sequence = (int) $setting['value'];
            $om->write('core\SettingValue', $settings_ids, ['value' => $sequence + 1]);

            $result[$oid] = ((string) $booking['center_id.group_code']) . ((string) $sequence);

        }
        return $result;
    }


    public static function getPrice($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(__CLASS__, $oids, ['booking_lines_groups_ids']);
        if($bookings > 0 && count($bookings)) {
            foreach($bookings as $bid => $booking) {
                $groups = $om->read('lodging\sale\booking\BookingLineGroup', $booking['booking_lines_groups_ids'], ['price']);
                $result[$bid] = 0.0;
                if($groups > 0 && count($groups)) {
                    foreach($groups as $group) {
                        $result[$bid] += $group['price'];
                    }
                    $result[$bid] = round($result[$bid], 2);                    
                }
            }
        }
        return $result;
    }

    public static function onchangeCustomerId($om, $oids, $lang) {
        // force immediate recomputing of the name/reference
        $om->write(__CLASS__, $oids, ['name' => null]);
        $bookings = $om->read(__CLASS__, $oids, ['name', 'booking_lines_groups_ids', 'customer_id.partner_identity_id.description']);

        if($bookings > 0 && count($bookings) > 0) {
            foreach($bookings as $bid => $booking) {
                $booking_lines_groups_ids = $booking['booking_lines_groups_ids'];
                if($booking_lines_groups_ids > 0 && count($booking_lines_groups_ids)) {
                    BookingLineGroup::_updatePriceAdapters($om, $booking_lines_groups_ids, $lang);
                }
                $om->write(__CLASS__, $oids, ['description' => $booking['customer_id.partner_identity_id.description']]);
            }
        }
    }

    public static function onchangeCenterId($om, $oids, $lang) {
        $bookings = $om->read(__CLASS__, $oids, ['booking_lines_ids']);

        if($bookings > 0 && count($bookings) > 0) {
            foreach($bookings as $bid => $booking) {
                $booking_lines_ids = $booking['booking_lines_ids'];
                if($booking_lines_ids > 0 && count($booking_lines_ids)) {
                    BookingLine::_updatePriceId($om, $booking_lines_ids, $lang);
                }
            }
        }
    }


    /**
     * Something has changed in the booking (number of nights or persons; customer change; ...)
     */
    public static function _updateAutosaleProducts($om, $oids, $lang) {

        /*
            remove groups related to autosales that already exist
        */
        $bookings = $om->read(__CLASS__, $oids, ['customer_id.count_booking_12', 'booking_lines_groups_ids']);

        $groups_ids_to_delete = [];
        foreach($bookings as $bid => $booking) {
            $booking_lines_groups = $om->read('lodging\sale\booking\BookingLineGroup', $booking['booking_lines_groups_ids'], ['is_autosale']);
            foreach($booking_lines_groups as $gid => $group) {
                if($group['is_autosale']) {
                    $groups_ids_to_delete[] = $gid;
                }
            }
        }
        $om->remove('lodging\sale\booking\BookingLineGroup', $groups_ids_to_delete, true);


        //  groups to create (by autosale)
        /*
            label
            product_id
            qty
        */

        foreach($bookings as $bid => $booking) {
            $booking_lines_groups = $om->read('lodging\sale\booking\BookingLineGroup', $booking['booking_lines_groups_ids'], ['sojourn_type']);

            foreach($booking_lines_groups as $gid => $group) {
                /*
                    Find the first Autosale List that matches the booking dates
                */
                $autosale_list_category_id = ($group['sojourn_type'] == 'GA')?1:2;
                $autosale_lists_ids = $om->search('sale\autosale\AutosaleList', [
                    ['autosale_list_category_id', '=', $autosale_list_category_id],
                    ['date_from', '<=', $group['date_from']],
                    ['date_to', '>=', $group['date_from']]
                ]);

                $autosale_lists = $om->read('sale\autosale\AutosaleList', $autosale_lists_ids, ['id', 'autosale_lines_ids']);
                $autosale_list_id = 0;
                $autosale_list = null;
                if($autosale_lists > 0 && count($autosale_lists)) {
                    // use first match (there should always be only one or zero)
                    $autosale_list = array_pop($autosale_lists);
                    $autosale_list_id = $autosale_list['id'];
                    trigger_error("QN_DEBUG_ORM:: match with autosale List {$autosale_list_id}", QN_REPORT_DEBUG);
                }
                else {
                    trigger_error("QN_DEBUG_ORM:: no autosale List found", QN_REPORT_DEBUG);
                }
                /*
                    Search for matching Autosale products within the found List
                */
                if($autosale_list_id) {
                }
            }


        }
    }

}