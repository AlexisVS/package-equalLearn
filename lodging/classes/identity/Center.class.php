<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace lodging\identity;

class Center extends \identity\Establishment {

    public static function getName() {
        return 'Center';
    }

    public static function getDescription() {
        return "A center is an accommodation establishment providing overnight lodging and holding one or more rental unit(s).";
    }

    public static function getColumns() {

        return [
            /*
                The manager is stored as part of the Center object.
            */
            'manager_firstname' => [
                'type'          => 'string',
                'description'   => 'Reference contact forename.'
            ],
            'manager_lastname' => [
                'type'          => 'string',
                'description'   => 'Reference contact surname.'
            ],
            'manager_gender' => [
                'type'          => 'string',
                'selection'     => ['M' => 'Male', 'F' => 'Female'],
                'description'   => 'Reference contact gender.'
            ],
            'manager_phone' => [
                'type'          => 'string',
                'usage'         => 'phone',
                'description'   => 'Official contact phone number.' 
            ],
            'manager_email' => [
                'type'          => 'string',
                'usage'         => 'email',
                'description'   => 'Official contact email address.' 
            ],              
        ];
    }
}