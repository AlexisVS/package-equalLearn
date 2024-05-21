<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
use identity\User;

list($params, $providers) = eQual::announce([
    'description'   => 'Returns descriptor of current User, based on received access_token',
    'constants'     => ['AUTH_ACCESS_TOKEN_VALIDITY', 'BACKEND_URL'],
    'response'      => [
        'content-type'      => 'application/json',
        'charset'           => 'UTF-8',
        'accept-origin'     => '*'
    ],
    'providers'     => ['context', 'auth']
]);

list($context, $auth) = [$providers['context'], $providers['auth']];

$result = eQual::run('get', 'core_userinfo');

// user has always READ right on its own object
$user = User::id($user_id)
    ->read([
        'identity_id' => ['firstname', 'lastname'],
        'organisation_id'
    ])
    ->adapt('json')
    ->first(true);

$result = array_merge($result, [
        'identity_id'       => $user['identity_id'],
        'organisation_id'   => $user['organisation_id']
    ]);

// renew JWT access token
$access_token = $auth->token($user_id, constant('AUTH_ACCESS_TOKEN_VALIDITY'));

// send back basic info of the User object
$context->httpResponse()
    ->cookie('access_token',  $access_token, [
        'expires'   => time() + constant('AUTH_ACCESS_TOKEN_VALIDITY'),
        'httponly'  => true,
        'secure'    => constant('AUTH_TOKEN_HTTPS'),
        'domain'    => parse_url(constant('BACKEND_URL'), PHP_URL_HOST)
    ])
    ->body($result)
    ->send();
