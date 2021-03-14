<?php
namespace symbiose\identity;
use qinoa\orm\Model;

class Organisation extends Model {
    public static function getColumns() {
        /**
         * This class aims to hold the core information describing any organisation (which includes natural and legal persons).
         *
         */

        return [
            'name' => [
                'type'          => 'string',
                'description'   => 'Display name. A short name to be used as a memo for identifying the organisation (e.g. an acronym).'
            ],
            'type' => [
                'type'          => 'string',
                'selection'     => [
                                        'individual (natural person)',
                                        'self-employed',
                                        'company',
                                        'non-profit',
                                        'public-administration'
                ],
                'description'   => 'Type of organisation.'
            
            ],
            'legal_name' => [
                'type'          => 'string',
                'description'   => 'Full name of the organisation (business name).'
            ],
            'description' => [
                'type'          => 'string',
                'description'   => 'A short reminder to help user identify the organisation (e.g. "Human Resources Consultancy Firm").'
            ],
            'phone' => [
                'type'          => 'string',
                'usage'         => 'phone',                
                'description'   => 'Official contact phone number.' 
            ],
            'email' => [
                'type'          => 'string',
                'usage'         => 'email',                
                'description'   => 'Official contact email address.' 
            ],            
            'VAT_number' => [
                'type'          => 'string',
                'description'   => 'Value Added Tax identification number, if any.'
            ],
            'registration_number' => [
                'type'          => 'string',
                'description'   => 'Organisation registration number (company number), if any.'
            ],  
            /*
                Description of the main address of the organisation (the headquarters, most of the time)
            */
            'address_country' => [
                'type'          => 'string',
                'description'   => 'Country in which headquarters are located.' 
            ],
            'address_street' => [
                'type'          => 'string',
                'description'   => 'Street and number of the headquarters address.'
            ],
            'address_city' => [
                'type'          => 'string',
                'description'   => 'City in which headquarters are located.'
            ],
            'address_zip' => [
                'type'          => 'string',
                'description'   => 'Postal code of the headquarters address.'
            ],
            /*
                The reference person is stored as part of the organisation (might be the director, the manager, the CEO, ...).
                These contact details are commonly requested by service providers for validating the identity of an organisation.
            */
            'contact_position' => [
                'type'          => 'string',
                'description'   => 'Position of the reference contact for the organisation.'
            ],            
            'contact_firstname' => [
                'type'          => 'string',
                'description'   => 'Reference contact forename.'
            ],
            'contact_lastname' => [
                'type'          => 'string',
                'description'   => 'Reference contact surname.'
            ],
            'contact_gender' => [
                'type'          => 'string',
                'selection'     => ['M' => 'Male', 'F' => 'Female'],
                'description'   => 'Reference contact gender.'
            ],
            /*
                Relational fields for
                Children entities and parent company, if any
            */
            'children_id' => [
                'type'              => 'one2many',
                'foreign_object'    => 'symbiose\identity\Organisation',
                'description'       => 'Children organisations owned by the company, if any.' 
            ],            
            'parent_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'symbiose\identity\Organisation',
                'description'       => 'Parent company of which the organisation is a branch, if any.' 
            ]
        ];
	}
}