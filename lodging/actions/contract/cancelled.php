<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use lodging\sale\booking\Contract;

list($params, $providers) = announce([
    'description'   => "Sets contract as cancelled.",
    'params'        => [
        'id' =>  [
            'description'   => 'Identifier of the contract to cancel.',
            'type'          => 'integer',
            'min'           => 1,
            'required'      => true
        ],
    ],
    'access' => [
        'visibility'        => 'public',		// 'public' (default) or 'private' (can be invoked by CLI only)
        'users'             => [ROOT_USER_ID],		// list of users ids granted 
        'groups'            => ['admin'],// list of groups ids or names granted 
    ],
    'response'      => [
        'content-type'  => 'application/json',
        'charset'       => 'utf-8',
        'accept-origin' => '*'
    ],
    'providers'     => ['context', 'orm', 'auth'] 
]);


list($context, $orm, $auth) = [$providers['context'], $providers['orm'], $providers['auth']];



Contract::id($params['id'])->update(['status' => 'cancelled']);


$context->httpResponse()
        ->status(204)
        ->send();