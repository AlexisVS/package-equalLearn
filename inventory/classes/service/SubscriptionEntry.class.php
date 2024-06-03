<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/

namespace inventory\service;

use sale\subscription\SubscriptionEntry as SaleSubscriptionEntry;

class SubscriptionEntry extends SaleSubscriptionEntry {

    public static function getDescription() {
        return 'Subscription entries describe the process of invoicing the client for the service.
        They are associated with both the customer and the service provider.';
    }

    public static function getColumns(): array {
        return [

            /**
             * Override Sale SubscriptionEntry columns
             */

            'object_class' => [
                'type'           => 'string',
                'description'    => 'Class of the object object_id points to.',
                'default'        => 'inventory\service\Subscription',
                'dependents'     => ['subscription_id']
            ],

            'subscription_id' => [
                'type'           => 'computed',
                'result_type'    => 'many2one',
                'foreign_object' => 'inventory\service\Subscription',
                'description'    => 'Identifier of the subscription the subscription entry originates from.',
                'store'          => true,
                'function'       => 'calcSubscriptionId',
                'dependents'     => ['product_id', 'customer_id', 'service_provider']
            ],

            /**
             * Specific Inventory SubscriptionEntry columns
             */

            'has_external_provider' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'description'       => 'The subscriptionEntry has external provider.',
                'store'             => true,
                'instance'          => true,
                'function'          => 'calcHasExternalProvider'
            ],

            'service_provider_id' => [
                'type'           => 'computed',
                'result_type'    => 'many2one',
                'foreign_object' => 'inventory\service\ServiceProvider',
                'description'    => 'The service provider to which the subscription belongs.',
                'store'          => true,
                'instance'       => true,
                'function'       => 'calcServiceProviderId'
            ]

        ];
    }

    public static function calcHasExternalProvider($self): array {
        return self::calcFromSubscription($self, 'has_external_provider');
    }

    public static function calcServiceProviderId($self): array {
        return self::calcFromSubscription($self, 'service_provider_id');
    }

    public static function calcSubscriptionId($self): array {
        return self::calcFromSubscription($self, 'subscription_id');
    }
}
