<?php
/*
    This file is part of the eQual framework <http://www.github.com/cedricfrancoys/equal>
    Some Rights Reserved, Cedric Francoys, 2010-2024
    Licensed under GNU GPL 3 license <http://www.gnu.org/licenses/>
*/

namespace inventory;

use equal\orm\Model;

class Product extends Model {

    public static function getDescription() {
        return 'Inventory products are composed of services, softwares, servers and instances. They are either owned by a company or by a customer.';
    }

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'string',
                'unique'            => true,
                'description'       => 'Name of the product.',
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Short presentation of the product.'
            ],

            'is_internal' => [
                'type'              => 'boolean',
                'description'       => 'The product is internal.',
                'help'              => 'Internal products are used by own organisation. Information relating to external products are kept so that the company can work on those.',
                'default'           => false
            ],

            'customer_id'=> [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'description'       => 'The client associated with the product.',
                'visible'           => ['is_internal', '=', false]
            ],

            'servers_ids' => [
                'type'              => 'many2many',
                'foreign_object'    => 'inventory\server\Server',
                'foreign_field'     => 'products_ids',
                'rel_table'         => 'inventory_rel_product_server',
                'rel_foreign_key'   => 'server_id',
                'rel_local_key'     => 'product_id',
                'ondelete'          => 'cascade',
                'description'       => 'List of server that are used by the product.'
            ],

            'instances_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'inventory\server\Instance',
                'foreign_field'     => 'product_id',
                'description'       => 'Instances used by product.'
            ],

            'services_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'inventory\service\Service',
                'foreign_field'     => 'product_id',
                'ondetach'          => 'delete',
                'description'       => 'Services used by product.'
            ],

            'softwares_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'inventory\Software',
                'foreign_field'     => 'product_id',
                'description'       => 'List of software associated with the product.'
            ]

        ];
    }
}
