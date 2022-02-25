<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\booking;
use equal\orm\Model;

class Booking extends Model {


    public static function getColumns() {
        return [
            'creator' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\User',
                'description'       => 'User who created the entry.',
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Code to serve as reference (might not be unique)",
                'function'          => 'sale\booking\Booking::getDisplayName',
                'store'             => true
            ],

            'description' => [
                'type'              => 'text',
                'usage'             => '',
                'description'       => "Reason of the booking, for internal use.",
                'default'           => ''
            ],

            'customer_reference' => [
                'type'              => 'string',
                'description'       => "Code or short string given by the customer as own reference, if any.",
                'default'           => ''
            ],

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'domain'            => ['relationship', '=', 'customer'],
                'description'       => "The customer whom the booking relates to.",
                'required'          => true,
                'onchange'          => 'sale\booking\Booking::onchangeCustomerId'
            ],

            'customer_identity_id' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'function'          => 'sale\booking\Booking::getCustomerIdentityId',
                'description'       => "The identifier of the Customer identity."
            ],

            'center_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'lodging\identity\Center',
                'description'       => "The center to which the booking relates to.",
                'required'          => true,
                'onchange'          => 'sale\booking\Booking::onchangeCenterId'
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'function'          => 'sale\booking\Booking::getTotal',
                'description'       => 'Total tax-excluded price of the booking.',
                'store'             => true
            ],

            'price' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'function'          => 'sale\booking\Booking::getPrice',
                'description'       => 'Final tax-included price of the booking.',
                'store'             => true
            ],

            // #todo
            // origin ID (OTA)

            // A booking can have several contacts (extending identity\Partner)
            'contacts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Contact',
                'foreign_field'     => 'booking_id',
                'description'       => 'List of contacts related to the booking, if any.',
                'domain'            => ['owner_identity_id', '=', 'object.customer_identity_id']
            ],

            'has_contract' => [
                'type'              => 'boolean',
                'description'       => "Has a contract been generated yet? Flag is reset in case of changes before the sojourn.",
                'default'           => false
            ],

            'contracts_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Contract',
                'foreign_field'     => 'booking_id',
                'description'       => 'List of contacts related to the booking, if any.'
            ],

            'booking_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingLine',
                'foreign_field'     => 'booking_id',
                'description'       => 'Detailed lines of the booking.'
            ],

            'booking_lines_groups_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\BookingLineGroup',
                'foreign_field'     => 'booking_id',
                'description'       => 'Grouped lines of the booking.'
            ],

            'consumptions_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Consumption',
                'foreign_field'     => 'booking_id',
                'description'       => 'Consumptions related to the booking.'
            ],

            'composition_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\Composition',
                'description'       => 'The composition that relates to the booking.'
            ],

            'type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingType',
                'description'       => "The kind of booking it is about.",
                'required'          => true
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'quote',                    // booking is just informative: nothing has been booked in the planning
                    'option',                   // booking has been placed in the planning for 10 days
                    'confirmed',                // booking has been placed in the planning without time limit
                    'validated',                // signed contract and first installment have been received
                    'checkedin',                // host is currently occupying the booked rental unit
                    'checkedout',               // host has left the booked rental unit
                    'due_balance',              // customer still has to pay something
                    'credit_balance',           // a reimbusrsement to customer is still required
                    'balanced'                  // booking is over and balance is cleared
                ],
                'description'       => 'Status of the booking.',
                'default'           => 'quote'
            ],

            'is_cancelled' => [
                'type'              => 'boolean',
                'description'       => "Flag to mark the booking as cancelled (impacts status).",
                'default'           => false
            ],

            'cancellation_reason' => [
                'type'              => 'string',
                'selection'         => [
                    'generic',                  // customer cancelled for a generic reason or without mentioning the reason (admin fees apply)
                    'overbooking',              // the booking was cancelled due to failure in delivery service
                    'duplicate',                // several contacts of the same group made distinct bookings for the same sojourn
                    'schedule_change',          // external resheduling (organisation, means of transport, ...),
                    'health_impediment'         // cancellation for medical or mourning reason
                ],
                'description'       => "Reason for which the customer cancelled the booking.",
                'default'           => 'generic'
            ],

            'payment_status' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'sale\booking\Booking::getPaymentStatus'
            ],

            // date fields are based on dates from booking line groups
            'date_from' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'function'          => 'sale\booking\Booking::getDateFrom',
                'store'             => true,
                'default'           => time()
            ],

            'date_to' => [
                'type'              => 'computed',
                'result_type'       => 'date',
                'function'          => 'sale\booking\Booking::getDateTo',
                'store'             => true,
                'default'           => time()
            ],

            'has_payer_organisation' => [
                'type'              => 'boolean',
                'description'       => "Flag to know if invoice must be sent to another Identity.",
                'default'           => false
            ],

            'payer_organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Partner',
                'visible'           => [ 'has_payer_organisation', '=', true ],
                'domain'            => [ ['owner_identity_id', '=', 'object.customer_identity_id'], ['relationship', '=', 'payer'] ],
                'description'       => "The partner whom the invoices have to be sent to."
            ],

            'fundings_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Funding',
                'foreign_field'     => 'booking_id',
                'description'       => 'Fundings that relate to the booking.',
                'ondetach'          => 'delete'
            ],

            'invoices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\Invoice',
                'foreign_field'     => 'booking_id',
                'description'       => 'Invoices that relate to the booking.'
            ]

        ];
    }

    public static function getDisplayName($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(__CLASS__, $oids, ['created', 'customer_id', 'customer_id.partner_identity_id'], $lang);

        foreach($bookings as $oid => $odata) {
            $increment = 1;
            // search for bookings made the same day by same customer, if any
            if(!empty($odata['customer_id'])) {
                $bookings_ids = $om->search(__CLASS__, [ ['created', '=', $odata['created']], ['customer_id','=', $odata['customer_id']] ]);
                $increment = count($bookings_ids);
            }
            $result[$oid] = sprintf("%s-%08d-%02d", date("ymd", $odata['created']), $odata['customer_id.partner_identity_id'], $increment);
        }
        return $result;
    }

    public static function getDateFrom($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(__CLASS__, $oids, ['booking_lines_groups_ids']);

        foreach($bookings as $bid => $booking) {
            $min_date = PHP_INT_MAX;
            $booking_line_groups = $om->read('sale\booking\BookingLineGroup', $booking['booking_lines_groups_ids'], ['date_from']);
            if($booking_line_groups > 0 && count($booking_line_groups)) {
                foreach($booking_line_groups as $gid => $group) {
                    if($group['date_from'] < $min_date) {
                        $min_date = $group['date_from'];
                    }
                }
                $result[$bid] = $min_date;
            }
        }

        return $result;
    }

    public static function getDateTo($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(__CLASS__, $oids, ['booking_lines_groups_ids']);

        foreach($bookings as $bid => $booking) {
            $max_date = 0;
            $booking_line_groups = $om->read('sale\booking\BookingLineGroup', $booking['booking_lines_groups_ids'], ['date_to']);
            if($booking_line_groups > 0 && count($booking_line_groups)) {
                foreach($booking_line_groups as $gid => $group) {
                    if($group['date_to'] > $max_date) {
                        $max_date = $group['date_to'];
                    }
                }
                $result[$bid] = $max_date;
            }
        }

        return $result;
    }

    public static function getPaymentStatus($om, $oids, $lang) {
        // #todo
    }

    public static function getCustomerIdentityId($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(get_called_class(), $oids, ['customer_id.partner_identity_id']);

        foreach($bookings as $oid => $booking) {
            $result[$oid] = (int) $booking['customer_id.partner_identity_id'];
        }
        return $result;
    }

    public static function getPrice($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(get_called_class(), $oids, ['booking_lines_groups_ids.price']);
        if($bookings > 0) {
            foreach($bookings as $bid => $booking) {
                $result[$bid] = array_reduce($booking['booking_lines_groups_ids.price'], function ($c, $a) {
                    return $c + $a['price'];
                }, 0.0);
            }
        }
        return $result;
    }

    public static function getTotal($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(get_called_class(), $oids, ['booking_lines_groups_ids.total']);
        if($bookings > 0) {
            foreach($bookings as $bid => $booking) {
                $result[$bid] = array_reduce($booking['booking_lines_groups_ids.total'], function ($c, $a) {
                    return $c + $a['total'];
                }, 0.0);    
            }
        }
        return $result;
    }

    public static function onchangeCustomerId($om, $oids, $lang) {
        $om->write(get_called_class(), $oids, ['name' => null]);
        // force immediate recomputing of the name/reference
        $booking_lines_groups_ids = $om->read(__CLASS__, $oids, ['name', 'booking_lines_groups_ids']);
        if($booking_lines_groups_ids > 0 && count($booking_lines_groups_ids)) {
            BookingLineGroup::_updatePriceAdapters($om, $booking_lines_groups_ids, $lang);
        }
    }

    public static function onchangeCenterId($om, $oids, $lang) {
        $booking_lines_ids = $om->read(__CLASS__, $oids, ['booking_lines_ids']);
        if($booking_lines_ids > 0 && count($booking_lines_ids)) {
            BookingLine::_updatePriceId($om, $booking_lines_ids, $lang);
        }
    }


    public static function ondelete($om, $oids) {
        $res = $om->read(get_called_class(), $oids, [ 'status' ]);

        if($res > 0) {
            foreach($res as $oids => $odata) {
                if($odata['status'] != 'quote') {
                    return false;
                }
            }
        }
        return parent::ondelete($om, $oids);
    }

}