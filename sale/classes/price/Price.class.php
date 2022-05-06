<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2021
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\price;
use equal\orm\Model;

class Price extends Model {
    public static function getColumns() {
        /**
         */

        return [

            'name' => [
                'type'              => 'computed',
                'function'          => 'sale\price\Price::getDisplayName',
                'result_type'       => 'string',
                'store'             => true,
                'description'       => 'The display name of the price.'
            ],

            'price' => [
                'type'              => 'float',
                'usage'             => 'amount/money:4',
                'description'       => "Tax excluded price.",
                'onchange'          => 'sale\price\Price::onchangePrice',
                'required'          => true
            ],

            'price_vat' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'function'          => 'sale\price\Price::getPriceVat',
                'usage'             => 'amount/money:4',
                'description'       => "Tax included price. This field is used to allow encoding prices VAT incl.",
                'store'             => true,
                'onchange'          => 'sale\price\Price::onchangePriceVat'
            ],

            'type' => [
                'type'              => 'string',
                'selection'         => ['direct', 'computed'],
                'default'           => 'direct'
            ],

            'calculation_method_id' => [
                'type'              => 'string',
                'description'       => "Method to use for price computation.",
                'visible'           => ['type', '=', 'computed']
            ],

            'price_list_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\price\PriceList',
                'description'       => "The Price List the price belongs to.",
                'required'          => true,
                'ondelete'          => 'cascade',
                'onchange'          => 'sale\price\Price::onchangePriceListId'
            ],

            'is_active' => [
                'type'              => 'computed',
                'result_type'       => 'boolean',
                'function'          => 'sale\price\Price::getIsActive',
                'store'             => true,
                'description'       => "Is the price currently applicable?"
            ],

            'accounting_rule_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'finance\accounting\AccountingRule',
                'description'       => "Selling accounting rule. If set, overrides the rule of the product this price is assigned to.",
                'onchange'          => 'sale\price\Price::onchangeAccountingRuleId'
            ],

            'product_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\catalog\Product',
                'description'       => "The Product (sku) the price applies to.",
                'required'          => true,
                'onchange'          => 'sale\price\Price::onchangeProductId'
            ],

            'vat_rate' => [
                'type'              => 'computed',
                'result_type'       => 'float',
                'usage'             => 'amount/rate',
                'function'          => 'sale\price\Price::getVatRate',
                'description'       => 'VAT rate applied on the price (from accounting rule).',
                'store'             => true,
                'readonly'          => true
            ]

        ];
    }

    public static function getDisplayName($om, $oids, $lang) {
        $result = [];
        $res = $om->read(__CLASS__, $oids, ['product_id.sku', 'price_list_id.name']);
        if($res > 0 && count($res)) {
            foreach($res as $oid => $odata) {
                $result[$oid] = "{$odata['product_id.sku']} - {$odata['price_list_id.name']}";
            }
        }
        return $result;
    }

    public static function getVatRate($om, $oids, $lang) {
        $result = [];
        $prices = $om->read(__CLASS__, $oids, ['accounting_rule_id.vat_rule_id.rate']);

        if($prices > 0 && count($prices)) {
            foreach($prices as $pid => $price) {
                $result[$pid] = $price['accounting_rule_id.vat_rule_id.rate'];
            }
        }
        return $result;
    }

    public static function getPriceVat($om, $oids, $lang) {
        $result = [];
        $prices = $om->read(__CLASS__, $oids, ['price', 'vat_rate']);

        if($prices > 0 && count($prices)) {
            foreach($prices as $pid => $price) {
                $result[$pid] = $price['price'] * (1.0 + $price['vat_rate']);
            }
        }
        return $result;
    }

    public static function getIsActive($om, $oids, $lang) {
        $result = [];
        $prices = $om->read(__CLASS__, $oids, ['price_list_id.is_active']);

        if($prices > 0 && count($prices)) {
            foreach($prices as $pid => $price) {
                $result[$pid] = $price['price_list_id.is_active'];
            }
        }
        return $result;
    }

    public static function onchangeAccountingRuleId($om, $oids, $lang) {
        $res = $om->write(__CLASS__, $oids, ['vat_rate' => null, 'price_vat' => null]);
    }

    public static function onchangePriceListId($om, $oids, $lang) {
        $om->write(__CLASS__, $oids, ['name' => null], $lang);
    }

    public static function onchangeProductId($om, $oids, $lang) {
        $om->write(__CLASS__, $oids, ['name' => null], $lang);
    }

    /**
     * Update price, based on VAT incl. price and applied VAT rate
     */
    public static function onchangePriceVat($om, $oids, $lang) {
        $prices = $om->read(__CLASS__, $oids, ['price_vat', 'vat_rate']);

        if($prices > 0 && count($prices)) {
            foreach($prices as $pid => $price) {
                $om->write(__CLASS__, $pid, ['price' => $price['price_vat'] / (1.0 + $price['vat_rate'])]);
            }
        }
    }

    public static function onchangePrice($om, $oids, $lang) {
        $om->write(__CLASS__, $oids, ['price_vat' => null]);
    }

    public function getUnique() {
        return [
            ['product_id', 'price_list_id']
        ];
    }


}