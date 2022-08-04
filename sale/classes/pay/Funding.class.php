<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\pay;
use equal\orm\Model;
use equal\cron\Scheduler;

class Funding extends Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true
            ],

            'payments_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pay\Payment',
                'foreign_field'     => 'funding_id'
            ],

            'type' => [
                'type'              => 'string',
                'selection'         => [
                    'installment',
                    'invoice'
                ],
                'default'           => 'installment',
                'description'       => "Deadlines are installment except for last one: final invoice."
            ],

            'due_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Amount expected for the funding (computed based on VAT incl. price).',
                'required'          => true,
                'onupdate'          => 'onupdateDueAmount'
            ],

            'due_date' => [
                'type'              => 'date',
                'description'       => "Deadline before which the funding is expected.",
                'default'           => time()
            ],

            'issue_date' => [
                'type'              => 'date',
                'description'       => "Date at which the request for payment has to be issued.",
                "default"           => time()
            ],

            'paid_amount' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Total amount that has been received (can be greater than due_amount).",
                'function'          => 'calcPaidAmount',
                'store'             => true
            ],

            'is_paid' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => "Has the full payment been received?",
                'function'          => 'calcIsPaid',
                'store'             => true,
            ],

            'amount_share' => [
                'type'              => 'float',
                'usage'             => 'amount/percent',
                'description'       => "Share of the payment over the total due amount."
            ],

            'payment_deadline_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\PaymentDeadline',
                'description'       => "The deadline model used for creating the funding, if any.",
                'onupdate'          => "onupdatePaymentDeadlineId"
            ],

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Invoice',
                'description'       => 'The invoice targeted by the funding, if any.',
                'visible'           => [ ['type', '=', 'invoice'] ]
            ],

            'payment_reference' => [
                'type'              => 'string',
                'description'       => 'Message for identifying the purpose of the transaction.',
                'default'           => ''
            ]
        ];
    }


    public static function calcName($om, $oids, $lang) {
        $result = [];
        $fundings = $om->read(get_called_class(), $oids, ['payment_deadline_id.name', 'due_amount'], $lang);

        if($fundings > 0) {
            foreach($fundings as $oid => $funding) {
                $result[$oid] = Setting::format_number_currency($funding['due_amount']).'    '.$funding['payment_deadline_id.name'];
            }
        }
        return $result;
    }

    public static function calcPaidAmount($om, $oids, $lang) {
        $result = [];
        $fundings = $om->read(get_called_class(), $oids, ['payments_ids.amount'], $lang);
        if($fundings > 0) {
            foreach($fundings as $fid => $funding) {
                $result[$fid] = array_reduce($funding['payments_ids.amount'], function ($c, $funding) {
                    return $c + $funding['amount'];
                }, 0);
            }
        }
        return $result;
    }

    public static function calcIsPaid($om, $oids, $lang) {
        $result = [];
        $fundings = $om->read(get_called_class(), $oids, ['due_amount', 'payments_ids.amount'], $lang);
        if($fundings > 0) {
            foreach($fundings as $fid => $funding) {
                $result[$fid] = false;
                $sum = array_reduce($funding['payments_ids.amount'], function ($c, $a) {
                    return $c + $a['amount'];
                }, 0.0);

                if($sum >= $funding['due_amount'] && $funding['due_amount'] > 0) {
                    $result[$fid] = true;
                }
            }
        }
        return $result;
    }

    public static function onupdateDueAmount($orm, $oids, $values, $lang) {
        $orm->update(self::getType(), $oids, ['name' => null], $lang);
    }

    public static function onupdatePaymentDeadlineId($orm, $oids, $values, $lang) {
        $orm->write(get_called_class(), $oids, ['name' => null], $lang);
    }



    /**
     * Check wether an object can be updated, and perform some additional operations if necessary.
     * This method can be overriden to define a more precise set of tests.
     *
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @param  array                        $values     Associative array holding the new values to be assigned.
     * @param  string                       $lang       Language in which multilang fields are being updated.
     * @return array    Returns an associative array mapping fields with their error messages. An empty array means that object has been successfully processed and can be updated.
     */
    public static function canupdate($om, $oids, $values, $lang) {
        $fundings = $om->read(self::getType(), $oids, ['is_paid'], $lang);

        if($fundings > 0) {
            foreach($fundings as $funding) {
                if( $funding['is_paid'] ) {
                    return ['is_paid' => ['non_editable' => 'No change is allowed once the funding has been paid.']];
                }
            }
        }

        return parent::canupdate($om, $oids, $values, $lang);
    }


    /**
     * Hook invoked before object update for performing object-specific additional operations.
     * Update the scheduled tasks related to the fundinds.
     *
     * @param  \equal\orm\ObjectManager    $om         ObjectManager instance.
     * @param  array                       $oids       List of objects identifiers.
     * @param  array                       $values     Associative array holding the new values that have been assigned.
     * @param  string                      $lang       Language in which multilang fields are being updated.
     * @return void
     */
    public static function onupdate($om, $oids, $values, $lang) {
        $cron = $om->getContainer()->get('cron');

        if(isset($values['due_date'])) {
            foreach($oids as $fid) {
                // remove any previsously scheduled task
                $cron->cancel("booking.funding.overdue.{$fid}");
                // setup a scheduled job upon funding overdue
                $cron->schedule(
                    // assign a reproducible unique name
                    "booking.funding.overdue.{$fid}",
                    // remind on day following due_date
                    $values['due_date'] + 86400,
                    'lodging_funding_check-payment',
                    [ 'id' => $fid ]
                );
            }
        }

        parent::onupdate($om, $oids, $values, $lang);
    }


    /**
     * Hook invoked after object deletion for performing object-specific additional operations.
     * Remove the scheduled tasks related to the deleted fundinds.
     * 
     * @param  \equal\orm\ObjectManager     $om         ObjectManager instance.
     * @param  array                        $oids       List of objects identifiers.
     * @return void
     */
    public static function ondelete($om, $oids) {
        $cron = $om->getContainer()->get('cron');

        foreach($oids as $fid) {
            // remove any previsously scheduled task
            $cron->cancel("booking.funding.overdue.{$fid}");
        }
    }

}