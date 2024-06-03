<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace inventory\service;

use equal\orm\Model;

class ServiceProviderCategory extends Model {

    public static function getDescription() {
        return 'ServiceProviderCategory organizes service providers into categories, aiding in system management and categorization';
    }

    public static function getColumns()
    {
        return [
            'name' => [
                'type'              => 'string',
                'description'       => 'Name of the service provider category.',
                'required'          => true,
            ],

            'description' => [
                'type'              => 'string',
                'description'       => 'Short presentation of the service provider category.'
            ],

            'services_providers_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'inventory\service\ServiceProvider',
                'foreign_field'     => 'service_provider_category_id',
                'description'       => 'Service provider to the category.'
            ],
        ];
    }
}
