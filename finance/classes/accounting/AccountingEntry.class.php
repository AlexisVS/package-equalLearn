<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace finance\accounting;
use equal\orm\Model;

class AccountingEntry extends Model {

    public static function getName() {
        return "Journal accounting entry";
    }

    public static function getDescription() {
        return "Accounting entries convert invoice lines into records of financial transactions in the accounting books.";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Label for identifying the entry.',
            ],

            'has_invoice' => [
                'type'              => 'boolean',
                'description'       => 'Signals that the entry relates to an invoice.',
                'default'           => false
            ],

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => Invoice::getType(),
                'description'       => 'Invoice that the line relates to.',
                'ondelete'          => 'cascade',
                'visible'           => ['has_invoice', '=', true]
            ],

            'has_order' => [
                'type'              => 'boolean',
                'description'       => 'Signals that the entry relates to an order.',
                'default'           => false
            ],

            'order_id' => [
                'type'              => 'many2one',
                'foreign_object'    => \sale\pos\Order::getType(),
                'description'       => 'Order that the line relates to.',
                'ondelete'          => 'cascade',
                'visible'           => ['has_order', '=', true]
            ],

            'account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => AccountChartLine::getType(),
                'description'       => "Accounting account the entry relates to.",
                'required'          => true,
                'ondelete'          => 'null'
            ],

            'journal_id' => [
                'type'              => 'many2one',
                'foreign_object'    => AccountingJournal::getType(),
                'description'       => "Accounting journal the entry relates to.",
                'required'          => true
            ],

            'debit' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Amount to be received.',
                'default'           => 0.0
            ],

            'credit' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Amount to be disbursed.',
                'default'           => 0.0
            ]
        ];
    }

}