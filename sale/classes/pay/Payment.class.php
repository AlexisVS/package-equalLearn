<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\pay;
use equal\orm\Model;

class Payment extends Model {

    public static function getColumns() {

        return [

            'partner_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Partner',
                'description'       => "The partner to whom the booking relates."
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Reference from the bank.'
            ],

            'communication' => [
                'type'              => 'string',
                'description'       => "Message from the payer.",
            ],

            'receipt_date' => [
                'type'              => 'datetime',
                'description'       => "Time of reception of the payment.",
                'default'           => time()
            ],

            'payment_method' => [
                'type'              => 'string',
                'selection'         => ['voucher','cashdesk','bank'],
                'description'       => "The method used for payment."
            ],

            'payment_origin' => [
                'type'              => 'string',
                'selection'         => ['cash','bank'],
                'description'       => "Origin of the received money.",
                'visible'           => [ ['payment_method', '=', 'cashdesk'] ]
            ],

            'operation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pos\Operation',
                'description'       => 'The operation the payment relates to.',
                'visible'           => [ ['payment_method', '=', 'cashdesk'] ]
            ],

            'statement_line_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\BankStatementLine',
                'description'       => 'The bank statement line the payment relates to.',
                'visible'           => [ ['payment_method', '=', 'bank'] ]
            ],

            'voucher_ref' => [
                'type'              => 'string',
                'description'       => 'The reference of the voucher the payment relates to.',
                'visible'           => [ ['payment_method', '=', 'voucher'] ]
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\Funding',
                'description'       => 'The funding the payement relates to, if any.',
                'onchange'          => 'sale\pay\Payment::onchangeFundingId'
            ]

        ];
    }

    public static function onchangeFundingId($om, $ids, $lang) {
        trigger_error("QN_DEBUG_ORM::calling sale\pay\Payment::onchangeFundingId", QN_REPORT_DEBUG);

        $payments = $om->read(get_called_class(), $ids, ['funding_id', 'partner_id', 'funding_id.due_amount', 'amount', 'partner_id', 'statement_line_id']);

        if($payments > 0) {
            $fundings_ids = [];
            foreach($payments as $pid => $payment) {

                if($payment['funding_id']) {
                    $partner_id = $payment['partner_id'];
                    // make sure a partner_id is assigned to the payment
                    if(!$payment['partner_id']) {
                        $fundings = $om->read('sale\booking\Funding', $payment['funding_id'], ['type', 'due_amount', 'booking_id.customer_id.id', 'booking_id.customer_id.name', 'invoice_id.partner_id.id', 'invoice_id.partner_id.name'], $lang);
                        if($fundings > 0) {
                            $funding = reset($fundings);
                            if($funding['type'] == 'invoice')  {
                                $partner_id = $funding['invoice_id.partner_id.id'];
                            }
                            else {
                                $partner_id = $funding['booking_id.customer_id.id'];
                            }
                            $om->write(get_called_class(), $pid, ['partner_id' => $partner_id]);
                        }
                    }

                    if($payment['amount'] > $payment['funding_id.due_amount']) {
                        $diff = $payment['funding_id.due_amount'] - $payment['amount'];
                        // create a new payment with negative amount
                        $om->create('sale\pay\Payment', [
                            'funding_id'        => $payment['funding_id'],
                            'partner_id'        => $partner_id,
                            'statement_line_id' => $payment['statement_line_id'],
                            'amount'            => $diff
                        ], $lang);
                    }
    
                    $om->write('sale\pay\Funding', $payment['funding_id'], ['is_paid' => null]);
                    $fundings_ids[] = $payment['funding_id'];
                }
            }
            // force immediate re-computing of the is_paid field
            $om->read('sale\pay\Funding', array_unique($fundings_ids), ['is_paid']);
        }
    }

    /**
     * Check wether the payment can be updated, and perform some additional operations if necessary.
     * This method can be overriden to define a more precise set of tests.
     *
     * @param  Object   $om         ObjectManager instance.
     * @param  Array    $oids       List of objects identifiers.
     * @param  Array    $values     Associative array holding the new values to be assigned.
     * @param  String   $lang       Language in which multilang fields are being updated.
     * @return Array    Returns an associative array mapping fields with their error messages. En empty array means that object has been successfully processed and can be updated.
     */
    public static function onupdate($om, $oids, $values, $lang=DEFAULT_LANG) {
        if(isset($values['amount'])) {
            $payments = $om->read('sale\pay\Payment', $oids, ['statement_line_id.amount'], $lang);

            foreach($payments as $pid => $payment) {
                if($values['amount'] > $payment['statement_line_id.amount']) {
                    return ['amount' => ['excessive_amount' => 'Payment amount cannot be higher than statement line amount.']];
                }
            }
        }
        return parent::onupdate($om, $oids, $values, $lang);
    }



}