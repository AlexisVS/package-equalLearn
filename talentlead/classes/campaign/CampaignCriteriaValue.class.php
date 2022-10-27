<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace talentlead\campaign;

use equal\orm\Model;

class CampaignCriteriaValue extends Model {

    public static function getColumns() {
        return [

            'campaign_criteria_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'talentlead\CampaignCriteria',
                'description'       => ""
            ],

            "value"       => [
                'type'              => 'string',
                'description'       => "Value of the Campaign Criteria."
            ]

        ];
    }

}