<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\pay;
use equal\orm\Model;

class BankStatement extends Model {

    public static function getColumns() {

        return [
            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'sale\pay\BankStatement::getDisplayName',
                'store'             => true
            ],

            'raw_data'  => [
                'type'              => 'binary',
                'description'       => 'Original file used for creating the statement.'
            ],
        
            'date' => [
                'type'              => 'date',
                'description'       => 'Date the statement was received.',
                'required'          => true,
                'readonly'          => true
            ],

            'old_balance' => [
                'type'              => 'float',
                'description'       => 'Account balance before the transactions.',
                'required'          => true,                
                'readonly'          => true
            ],

            'new_balance' => [
                'type'              => 'float',
                'description'       => 'Account balance after the transactions.',
                'required'          => true,                
                'readonly'          => true
            ],

            'statement_lines_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pay\BankStatementLine',
                'foreign_field'     => 'bank_statement_id',
                'description'       => 'The lines that are assigned to the statement.'
            ],

            'status' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'sale\pay\BankStatement::getStatus',
                'selection'         => [
                    'pending',                // hasn't been fully processed yet
                    'reconciled',             // has been fully processed (all lines either ignored or reconciled) 
                ],
                'description'       => 'Status of the statement (depending on lines).',
                'store'             => true
            ],

            // #memo - CODA statements comes with IBAN or BBAN numbers for reference account    
            'bank_account_number' => [
                'type'              => 'string',
                'description'       => 'Original number of the account (as provided in the statement might not be IBAN).'
            ],

            'bank_account_bic' => [
                'type'              => 'string',
                'description'       => 'Bank Identification Code of the account.'
            ],

            'bank_account_iban' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'usage'             => 'uri/urn.iban',
                'function'          => 'sale\pay\BankStatement::getBankAccountIban',
                'description'       => 'IBAN representation of the account number.',
                'store'             => true
            ]
    
        ];
    }

    public static function getDisplayName($om, $oids, $lang) {
        $result = [];
        $statements = $om->read(get_called_class(), $oids, ['bank_account_number', 'date', 'old_balance', 'new_balance']);
        foreach($statements as $oid => $statement) {
            $result[$oid] = sprintf("%s - %s - %s - %s", $statement['bank_account_number'], date('Ymd', $statement['date']), $statement['old_balance'], $statement['new_balance']);
        }
        return $result;
    }

    public static function getBankAccountIban($om, $oids, $lang) {
        $result = [];
        $statements = $om->read(get_called_class(), $oids, ['bank_account_number', 'bank_account_bic']);

        foreach($statements as $oid => $statement) {
            $result[$oid] = self::_convert_to_iban($statement['bank_account_number']);
        }
        return $result;
    }

    public static function getStatus($om, $oids, $lang) {
        $result = [];
        $statements = $om->read(get_called_class(), $oids, ['statement_lines_ids.status']);

        if($statements > 0) {
            foreach($statements as $sid => $statement) {
                $is_reconciled = true;
                foreach($statement['statement_lines_ids.status'] as $lid => $line) {
                    if($line['status'] != 'reconciled') {
                        $is_reconciled = false;
                        break;
                    }
                }
                $result[$sid] = ($is_reconciled)?'reconciled':'pending';
            }
        }
        return $result;
    }

    public static function _convert_to_iban($account_number) {

        /*
            account number already has IBAN format
        */

        if( !is_numeric(substr($account_number, 0, 2)) ) {
            return $account_number;
        }

        /*
            if code is not a country code, then convert BBAN to IBAN
        */

        // create numeric code of the target country
        $country_code = 'BE';

        $code_alpha = $country_code;
        $code_num = '';
        
        for($i = 0; $i < strlen($code_alpha); ++$i) {
            $letter = substr($code_alpha, $i, 1);
            $order = ord($letter) - ord('A');
            $code_num .= '1'.$order;
        }

        $check_digits = substr($account_number, -2);
        $dummy = intval($check_digits.$check_digits.$code_num.'00');
        $control = 98 - ($dummy % 97);
        return trim(sprintf("BE%s%s", $control, $account_number));
    }


    public function getUnique() {
        return [
            ['date', 'old_balance', 'new_balance']
        ];
    }    
}