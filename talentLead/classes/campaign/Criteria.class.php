<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace talentLead\campaign;

use equal\orm\Model;

class Criteria extends Model {

    public static function getColumns() {
        return [

            'name' => [
                'type'              => 'string',
                'description'       => "Name of the Criteria."
            ],

            'description' => [
                'type'              => 'string',
                'usage'             => 'text/plain',
                'description'       => 'Description of the Criteria.'
            ],

            'type' => [
                'type'              => 'string',
                'description'       => 'Type of the Criteria.'
            ],

            'form_control' => [
                'type'              => 'string',
                'description'       => 'Form control of the Criteria.'
            ],

            'is_multiple' => [
                'type'              => 'boolean',
                "description"       => 'Is there multiple criteria ?',
                'default'           => false
            ],

            'criteria_choices_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'talentLead\CriteriaChoice',
                'foreign_field'     => 'criteria_id',
                'description'       => 'Criteria choices.',
                // 'domain'            => ['owner_identity_id', '<>', 'object.id']
            ],

            // field for retrieving all partners related to the identity
            'campaigns_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'talentLead\Campaign',
                'foreign_field'     => 'customer_identity_id',
                'description'       => 'Customers related to a campaign.',
                // 'domain'            => ['owner_identity_id', '<>', 'object.id']
            ]

        ];
    }

}