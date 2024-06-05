<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace inventory\server;

use equal\orm\Model;

class Instance extends Model {

    public static function getDescription() {
        return 'Instance manages service or product instances, detailing type, version, URL, access information, and running software.';
    }

    public static function getColumns()
    {
        return [

            'name'    => [
                'type'              => 'string',
                'unique'            => true,
                'required'          => true,
                'description'       => 'Unique identifier of the instance.'
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Short description of the instance.'
            ],

            'instance_type' => [
                'type'              => 'string',
                'selection'         => ['dev', 'staging', 'prod', 'replica'],
                'description'       => 'Type of instance.',
                'default'           => 'dev'
            ],

            'version' => [
                'type'              => 'string',
                'selection'         => ['development', 'staging', 'preview', 'production'],
                'description'       => 'Branch version.',
                'default'           => 'development'
            ],

            'branch' => [
                'type'              => 'string',
                'description'       => 'Branch used by the instance.'
            ],

            'url' => [
                'type'              => 'string',
                'description'       => 'Front-end home URL.'
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'inventory\Product',
                'description'       => 'Product the instance belongs to.',
                'required'          => true
            ],

            'server_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'inventory\server\Server',
                'description'       => 'Server on which the instance runs.',
                'ondelete'          => 'cascade',
                'required'          => true
            ],

            'accesses_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'inventory\Access',
                'foreign_field'     => 'instance_id',
                'description'       => 'Information about how to access the instance.'
            ],

            'softwares_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'inventory\Software',
                'foreign_field'     => 'instance_id',
                'description'       => 'Information about the list of  software running on the instance.'
            ]

        ];
    }
}
