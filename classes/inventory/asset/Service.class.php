<?php
namespace symbiose\inventory\asset;


class Service extends \qinoa\orm\Model {
    public static function getColumns() {
        /**
        *
        */
        return [
            'provider_id' => [
                'type'              => 'one2many',            
                'foreign_object'    => 'symbiose\inventory\Provider', 
                'foreign_field'     => 'service_id',
                'description'       => "name of the company providing the service"
            ],
            'description' => [
                'type'              => 'string',
                'description'       => "short description of the service"
            ],
            'access_id' => [
                'type'              => 'one2many',            
                'foreign_object'    => 'symbiose\inventory\Access', 
                'foreign_field'     => 'server_id'
            ]
        ];
    }
}