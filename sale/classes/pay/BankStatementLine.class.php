<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\pay;
use equal\orm\Model;

class BankStatementLine extends Model {

    public static function getColumns() {

        return [

            'bank_statement_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\pay\BankStatement',
                'description'       => 'The bank statement the line relates to.'
            ],

            'date' => [
                'type'              => 'datetime',
                'description'       => 'Date at which the statement was issued.'
            ],

            'message' => [
                'type'              => 'string',
                'description'       => 'Message from the payer (or ref from the bank).'
            ],

            'structured_message' => [
                'type'              => 'string',
                'description'       => 'Structured message, if any.'
            ],

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Partner',
                'domain'            => ['relationship', '=', 'customer'],
                'description'       => 'The customer the payment relates to, if known.',
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money',
                'description'       => 'Amount of the transaction.'
            ],

            'account_iban' => [
                'type'              => 'string',
                'usage'             => 'uri/urn:iban',
                'description'       => 'IBAN which the payment originates from.'
            ],
            
            'account_holder' => [
                'type'              => 'string',
                'description'       => 'Name of the Person whom the payment originates.'
            ],            

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',              // hasn't been processed yet
                    'ignored',              // has been processed and ignore
                    'reconciled'            // has been processed and assigned to a Payment
                ],
                'description'       => 'Status of the line.',
                'default'           => 'pending'
            ]

        ];
    }

}