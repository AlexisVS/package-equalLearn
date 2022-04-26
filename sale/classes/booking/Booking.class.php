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
                'description'       => 'User who created the entry.'
            ],

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'description'       => "Code to serve as reference (might not be unique)",
                'function'          => 'sale\booking\Booking::getDisplayName',
                'store'             => true
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
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
                'description'       => "The customer whom the booking relates to (computed).",
                'onchange'          => 'sale\booking\Booking::onchangeCustomerId'
            ],

            'customer_identity_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Identity',
                'description'       => "The Customer identity.",
                'onchange'          => 'sale\booking\Booking::onchangeCustomerIdentityId'
            ],

            'total' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:4',
                'function'          => 'sale\booking\Booking::getTotal',
                'description'       => 'Total tax-excluded price of the booking.',
                'store'             => true
            ],

            'is_price_tbc' => [
                'type'              => 'boolean',
                'description'       => 'The booking contains products with prices to be confirmed.',
                'default'           => false
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
            // origin ID (internal, OTA, TO)

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

            'composition_items_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\booking\CompositionItem',
                'foreign_field'     => 'booking_id',
                'description'       => "The items that refer to the composition."
            ],

            'type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\booking\BookingType',
                'description'       => "The kind of booking it is about.",
                'default'           => 1                // default to 'general public'
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
                    'invoiceable',
                    'due_balance',              // customer still has to pay something
                    'credit_balance',           // a reimbusrsement to customer is still required
                    'balanced'                  // booking is over and balance is cleared
                ],
                'description'       => 'Status of the booking.',
                'default'           => 'quote',
                'onchange'          => 'onchangeStatus'
            ],

            'is_cancelled' => [
                'type'              => 'boolean',
                'description'       => "Flag to mark the booking as cancelled (impacts status).",
                'default'           => false
            ],

            'cancellation_reason' => [
                'type'              => 'string',
                'selection'         => [
                    'other',                    // customer cancelled for a non-listed reason or without mentionning the reason (cancellation fees might apply)
                    'overbooking',              // the booking was cancelled due to failure in delivery of the service
                    'duplicate',                // several contacts of the same group made distinct bookings for the same sojourn
                    'internal_impediment',      // cancellation due to an incident impacting the rental units
                    'external_impediment',      // cancellation due to external delivery failure (organisation, means of transport, ...)
                    'health_impediment'         // cancellation for medical or mourning reason
                ],
                'description'       => "Reason for which the customer cancelled the booking.",
                'default'           => 'generic'
            ],

            'payment_status' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'selection'         => [
                    'due',                       // some due payment have not been received yet
                    'paid'                       // all expected payments have been received
                ],
                'function'          => 'getPaymentStatus',
                'store'             => true
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

            'nb_pers' => [
                'type'              => 'computed',
                'result_type'       => 'integer',
                'description'       => 'Approx. amount of persons involved in the booking.',
                'function'          => 'getNbPers',
                'store'             => true
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
                'description'       => "The partner whom the invoices have to be sent to, if any."
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
        $bookings = $om->read(__CLASS__, $oids, ['created', 'customer_identity_id', 'customer_identity_id.name'], $lang);

        foreach($bookings as $oid => $odata) {
            $increment = 1;
            // search for bookings made the same day by same customer, if any
            if(!empty($odata['customer_id'])) {
                $bookings_ids = $om->search(__CLASS__, [ ['created', '=', $odata['created']], ['customer_identity_id','=', $odata['customer_identity_id']] ]);
                $increment = count($bookings_ids);
            }
            $result[$oid] = sprintf("%s-%08d-%02d", date("ymd", $odata['created']), $odata['customer_identity_id.name'], $increment);
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

        if($bookings > 0) {
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
        }

        return $result;
    }

    public static function getNbPers($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(__CLASS__, $oids, ['booking_lines_groups_ids.nb_pers']);

        if($bookings > 0) {
            foreach($bookings as $bid => $booking) {
                $result[$bid] = array_reduce($booking['booking_lines_groups_ids.nb_pers'], function ($c, $group) {
                    return $c + $group['nb_pers'];
                }, 0);
            }
        }
        return $result;
    }

    /**
     * Payment status tells if a given booking is in order regarding the expected payment up to now.
     */
    public static function getPaymentStatus($om, $oids, $lang) {
        // #todo
        $result = [];
        return $result;
    }

    public static function getPrice($om, $oids, $lang) {
        $result = [];
        $bookings = $om->read(get_called_class(), $oids, ['booking_lines_groups_ids.price']);
        if($bookings > 0) {
            foreach($bookings as $bid => $booking) {
                $result[$bid] = array_reduce($booking['booking_lines_groups_ids.price'], function ($c, $group) {
                    return $c + $group['price'];
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


    public static function onchangeStatus($om, $oids, $lang) {
        $bookings = $om->read(get_called_class(), $oids, ['status'], $lang);
        if($bookings > 0) {
            foreach($bookings as $bid => $booking) {
                if($booking['status'] == 'confirmed') {
                    $om->write(get_called_class(), $bid, ['has_contract' => true], $lang);
                }
            }
        }
    }

    public static function onchangeCustomerId($om, $oids, $lang) {
        trigger_error("QN_DEBUG_ORM::calling sale\booking\Booking:onchangeCustomerId", QN_REPORT_DEBUG);
        $bookings = $om->read(__CLASS__, $oids, ['customer_identity_id', 'customer_id.partner_identity_id', 'contacts_ids.partner_identity_id'], $lang);

        if($bookings > 0) {
            foreach($bookings as $bid => $booking) {
                if(!$booking['customer_identity_id']) {
                    $om->write(__CLASS__, $bid, ['customer_identity_id' => $booking['customer_id.partner_identity_id']], $lang);
                }

                if(!in_array($booking['customer_id.partner_identity_id'], array_map( function($a) { return $a['partner_identity_id']; }, $booking['contacts_ids.partner_identity_id']))) {
                    // create a contact with the customer as 'booking' contact
                    $om->create('sale\booking\Contact', ['booking_id' => $bid, 'owner_identity_id' => $booking['customer_identity_id'], 'partner_identity_id' => $booking['customer_id.partner_identity_id']]);
                }
            }
        }
    }

    public static function onchangeCustomerIdentityId($om, $oids, $lang) {
        trigger_error("QN_DEBUG_ORM::calling sale\booking\Booking:onchangeCustomerIdentityId", QN_REPORT_DEBUG);
        // reset name
        $om->write(__CLASS__, $oids, ['name' => null]);
        $bookings = $om->read(__CLASS__, $oids, ['customer_identity_id', 'customer_id']);

        if($bookings > 0) {
            foreach($bookings as $oid => $booking) {
                if(!$booking['customer_id']) {
                    $partner_id = null;

                    // find the partner that related to this identity, if any
                    $partners_ids = $om->search('sale\customer\Customer', [
                        ['relationship', '=', 'customer'],
                        ['owner_identity_id', '=', 1],
                        ['partner_identity_id', '=', $booking['customer_identity_id']]
                    ]);
                    if(count($partners_ids)) {
                        $partner_id = reset($partners_ids);
                    }
                    else {
                        // read Identity [type_id]
                        $identities = $om->read('identity\Identity', $booking['customer_identity_id'], ['type_id']);
                        if($identities > 0) {
                            $identity = reset($identities);
                            $partner_id = $om->create('sale\customer\Customer', [
                                'partner_identity_id'   => $booking['customer_identity_id'],
                                'customer_type_id'      => $identity['type_id']
                            ]);
                        }
                    }
                    if($partner_id) {
                        $om->write(__CLASS__, $oid, ['customer_id' => $partner_id]);
                    }
                }
            }
        }
    }

    public static function ondelete($om, $oids, $lang=DEFAULT_LANG) {
        $res = $om->read(get_called_class(), $oids, [ 'status' ]);

        if($res > 0) {
            foreach($res as $oids => $odata) {
                if($odata['status'] != 'quote') {
                    return false;
                }
            }
        }
        return parent::ondelete($om, $oids, $lang);
    }

    /**
     * Check wether an object can be updated, and perform some additional operations if necessary.
     * This method can be overriden to define a more precise set of tests.
     *
     * @param  object   $om         ObjectManager instance.
     * @param  array    $oids       List of objects identifiers.
     * @param  array    $values     Associative array holding the new values to be assigned.
     * @param  string   $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. En empty array means that object has been successfully processed and can be updated.
     */
    public static function onupdate($om, $oids, $values, $lang=DEFAULT_LANG) {
        if(count($values) == 1 && (
                isset($values['status'])                // status can always be updated
            ||  isset($values['composition_id'])        // composition can be assigned at any time
            ) ) {
            
        }
        else {
            $res = $om->read(get_called_class(), $oids, [ 'status', 'customer_id', 'customer_identity_id' ]);

            if($res > 0) {
                foreach($res as $oids => $odata) {
                    if($odata['status'] != 'quote') {
                        return ['status' => ['non_editable' => 'Booking can only be updated while its status is quote.']];
                    }
                    if( !$odata['customer_id'] && !$odata['customer_identity_id'] && !isset($values['customer_id']) && !isset($values['customer_identity_id']) ) {
                        return ['customer_id' => ['missing_mandatory' => 'Customer is mandatory.']];
                    }
                }
            }
        }
        return parent::onupdate($om, $oids, $values, $lang);
    }

}