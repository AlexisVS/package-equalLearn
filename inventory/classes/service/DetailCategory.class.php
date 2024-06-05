<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace inventory\service;

use equal\orm\Model;

class DetailCategory extends Model {

    public static function getDescription() {
        return 'DetailCategory organizes and manages categories of details related to services or products, enabling efficient classification and organization of information.';
    }

    public static function getColumns()
    {
        return [
            'name' => [
                'type'              => 'string',
                'description'       => 'Name of the detail category.',
                'unique'            => true,
                'required'          => true
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Short presentation of the detail category.'
            ],

            'details_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'inventory\service\Detail',
                'foreign_field'     => 'detail_category_id',
                'description'       => 'The details of the detail category.'
            ]
        ];
    }
}
