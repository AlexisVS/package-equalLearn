<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace business\relation;
use equal\orm\Model;

class Employee extends Model {

    public static function getName() {
        return 'Employee';
    }

    public static function getDescription() {
        return "An employee is a natural person working for an Organisation.";
    }

    public static function getColumns() {
        return [
            'name' => [
                'type'              => 'computed',
                'function'          => 'business\relation\Employee::getDisplayName',
                'result_type'       => 'string',
                'store'             => true,
                'description'       => 'The display name of the employee (concatenation of first and last names).'
            ],
            'contact_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Contact',
                'description'       => 'The contact that holds the details of the employee.' 
            ],
            'organisation_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\Organisation',
                'description'       => 'The organisation the employee works for.'             
            ],
            'position' => [
                'type'              => 'string',
                'description'       => "Position or role of the contact within the organisation (e.g. 'CEO', 'secretary')."
            ]

            /*
            contract

            */
        ];
    }

    public static function getDisplayName($om, $oids, $lang) {
        $result = [];
        $employees = $om->read(__CLASS__, $oids, ['contact_id.name']);
        foreach($employees as $oid => $odata) {
            $result[$oid] = $odata['contact_id.name'];
        }
        return $result;              
    }
}