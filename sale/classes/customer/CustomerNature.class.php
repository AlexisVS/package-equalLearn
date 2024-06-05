<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace sale\customer;

use equal\orm\Model;

class CustomerNature extends Model {

    public static function getDescription() {
        return 'A customer nature is an additional way of classifying customers, they can be linked to a customer type.'
            .' They are used to apply specific vat rate when items/services are sold to a type of user.';
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'alias',
                'description'       => 'Display name of customer nature.',
                'alias'             => 'description'
            ],

            'code' => [
                'type'              => 'string',
                'description'       => "Mnemonic of the customer nature.",
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => "Short description of the customer nature.",
                'multilang'         => true
            ],
            
            'rate_class_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\RateClass',
                'description'       => "The rate class that applies to customers of this nature.",
                'required'          => true
            ],

            'customer_type_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\CustomerType',
                'description'       => "The customer type the nature relates to.",
                'required'          => true
            ]

        ];
    }
}
