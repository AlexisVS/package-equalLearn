<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\pay;
use equal\orm\Model;

class Funding extends Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'sale\pay\Funding::getDisplayName',
                'store'             => true
            ],

            'payments_ids' => [ 
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pay\Payment',
                'foreign_field'     => 'funding_id'
            ],

            'type' => [
                'type'              => 'string',
                'selection'         => ['installment','invoice'],
                'description'       => "Deadlines are installment except for last one: final invoice."
            ],

            'due_amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => 'Amount expected for the funding (computed based on VAT incl. price).'
            ],

            'due_date' => [
                'type'              => 'date',
                'description'       => "Deadline before which the funding is expected."
            ],

            'issue_date' => [
                'type'              => 'date',
                'description'       => "Date at which the request for payment has to be issued."
            ],

            'is_paid' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'store'             => true,
                'description'       => "Has the full payment been received?",
                'function'          => 'getIsPaid'
            ],

            'payment_deadline_id' => [
                'type'              => 'many2one',                
                'foreign_object'    => 'sale\pay\PaymentDeadline',
                'description'       => "The deadline model used for creating the funding, if any.",
                'onchange'          => "sale\pay\Funding::onchangePaymentDeadlineId"
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


    public static function getDisplayName($om, $oids, $lang) {
        $result = [];
        $fundings = $om->read(get_called_class(), $oids, ['payment_deadline_id.name'], $lang);

        if($fundings > 0) {
            foreach($fundings as $oid => $funding) {
                $result[$oid] = $funding['payment_deadline_id.name'];
            }    
        }
        return $result;
    }

    public static function getIsPaid($om, $oids, $lang) {
        $result = [];
        $fundings = $om->read(get_called_class(), $oids, ['due_amount', 'payments_ids.amount'], $lang);
        if($fundings > 0) {
            foreach($fundings as $fid => $funding) {
                $result[$fid] = false;
                $sum = array_reduce($funding['payments_ids.amount'], function ($c, $a) {
                    return $c + $a['amount'];
                }, 0.0);

                if($sum >= $funding['due_amount']) {
                    $result[$fid] = true;
                }
            }
        }
        return $result;        
    }

    public static function onchangePaymentDeadlineId($orm, $oids, $lang) {
        $orm->write(get_called_class(), $oids, ['name' => null], $lang);
        $orm->read(get_called_class(), $oids, ['name'], $lang);
    }


}