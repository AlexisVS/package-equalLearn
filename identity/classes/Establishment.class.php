<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace identity;
use equal\orm\Model;

class Establishment extends Model {
    
    public static function getName() {
        return "Establishment unit";
    }    

    public static function getColumns() {

        return [
            'name' => [
                'type'          => 'string',
                'description'   => "Name of the establishment unit.",
                'required'      => true
            ],
            'phone' => [
                'type'          => 'string',
                'description'   => 'Official contact phone number.' 
            ],
            'email' => [
                'type'          => 'string',
                'description'   => 'Official contact email address.' 
            ],            
            'address_country' => [
                'type'          => 'string',
                'description'   => 'Country in which the establishment is located.' 
            ],
            'address_street' => [
                'type'          => 'string',
                'description'   => 'Street and number of the estalishment address.'
            ],
            'address_dispatch' => [
                'type'          => 'string',
                'description'   => 'Optional info for mail dispatch (appartment, box, floor, ...).'
            ],
            'address_city' => [
                'type'          => 'string',
                'description'   => 'City in which estalishment is located.'
            ],
            'address_zip' => [
                'type'          => 'string',
                'description'   => 'Postal code of the estalishment address.'
            ],
            'registration_number' => [
                'type'          => 'string',
                'description'   => 'Establishment registration number (establishment unit number), if any.'
            ],  
            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => "The organisation the establishment belongs to",
                'required'          => true
            ]
        ];
    }
}