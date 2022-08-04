<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\pos;
use equal\orm\Model;

class CashdeskSession extends Model {

    public static function getColumns() {

        return [

            'name' => [
                'type'              => 'computed',
                'result_type'       => 'string',
                'function'          => 'calcName',
                'store'             => true
            ],

            'amount' => [
                'type'              => 'float',
                'usage'             => 'amount/money:2',
                'description'       => "Amount of money in the cashdesk at the opening.",
                'required'          => true
            ],

            'user_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'identity\User',
                'description'       => 'User whom performed the log entry.',
                'required'          => true
            ],

            'cashdesk_id' => [
                'type'              => 'many2one',
                'foreign_object'    => Cashdesk::getType(),
                'description'       => 'Cash desk the log entry belongs to.',
                'onupdate'          => 'onupdateCashdeskId',                
                'required'          => true
            ],

            'status' => [
                'type'              => 'string',
                'selection'         => [
                    'pending',
                    'closed'
                ],
                'description'       => 'Cash desk the log entry belongs to.',
                'default'           => 'pending'
            ],

            'orders_ids' => [
                'type'              => 'one2many',
                'foreign_object'    => 'sale\pos\Order',
                'foreign_field'     => 'session_id',
                'description'       => 'The orders that relate to the session.'
            ]

        ];
    }

    /**
     * Check for special constraint : only one session can be opened at a time on a given cashdesk.
     * Make sure there are no other pending sessions, otherwise, deny the update (which might be called on draft instance).
     */
    public static function cancreate($om, $values, $lang) {
        $res = $om->search(get_called_class(), [ ['status', '=', 'pending'], ['cashdesk_id', '=', $values['cashdesk_id']] ]);
        if($res > 0 && count($res)) {
            return ['status' => ['already_open' => 'There can be only one session at a time on a given cashdesk.']];
        }
        return parent::cancreate($om, $values, $lang);
    }

    /**
     * Create an 'opening' operation in the operations log.
     * Cashdesk assignement cannot be changed, so this handler is called once, when the session has just be created.
     */
    public static function onupdateCashdeskId($om, $oids, $values, $lang) {
        $sessions = $om->read(__CLASS__, $oids, ['cashdesk_id', 'amount', 'user_id'], $lang);

        if($sessions > 0) {
            foreach($sessions as $sid => $session) {
                $om->create('sale\pos\Operation', [
                    'cashdesk_id'   => $session['cashdesk_id'],
                    'user_id'       => $session['user_id'],
                    'amount'        => $session['amount'],
                    'type'          => 'opening'
                ], $lang);
            }
        }
    }

    public static function calcName($om, $ids, $lang) {
        $result = [];

        $sessions = $om->read(get_called_class(), $ids, ['cashdesk_id.name', 'user_id.name'], $lang);

        if($sessions > 0) {
            foreach($sessions as $sid => $session) {
                if(strlen($session['user_id.name']) || strlen($session['cashdesk_id.name'])) {
                    $result[$sid] = $session['user_id.name'].' - '.$session['cashdesk_id.name'];
                }                
            }
        }

        return $result;
    }
}