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
        return "Accounting entries translate the invoice lines into entries that must be created in the accounting books.";
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => 'Label for identifying the entry.',
            ],

            'invoice_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\Invoice',
                'description'       => 'Invoice that the line relates to.',
                'required'          => true,
                'ondelete'          => 'cascade'
            ],

            'account_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountChartLine',
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
            ],

            'credit' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => 'Amount to be disbursed.',
            ],

            'is_exported' => [
                'type'              => 'boolean',
                'description'       => 'Mark the entry as exported.',
                'default'           => false
            ]

        ];
    }

}