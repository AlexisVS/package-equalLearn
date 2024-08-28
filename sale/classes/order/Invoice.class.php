<?php
/*
    This file is part of Symbiose Community Edition <https://github.com/yesbabylon/symbiose>
    Some Rights Reserved, Yesbabylon SRL, 2020-2024
    Licensed under GNU AGPL 3 license <http://www.gnu.org/licenses/>
*/
namespace sale\order;
use sale\customer\Customer;

use sale\accounting\invoice\Invoice as SaleInvoice;

class Invoice extends SaleInvoice {

    public static function getColumns() {

        return [

            'order_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\order\Order',
                'description'       => 'Order the invoice relates to.',
                'required'          => true
            ],

            'funding_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\order\Funding',
                'description'       => 'The funding the invoice originates from, if any.'
            ],

            'customer_id' => [
                'type'              => 'many2one',
                'foreign_object'    => 'sale\customer\Customer',
                'description'       => 'The counter party organization the invoice relates to.',
                'required'          => true,
                'onupdate'          => 'onupdateCustomer'
            ],


        ];
    }

    public static function onupdateCustomer($self): void {
        $self->read(['id', 'customer_id','status']);
        foreach($self as $id => $invoice) {
            if(isset($invoice['customer_id'], $invoice['status']) && $invoice['status'] == 'proforma'){
                $customer = Customer::id($invoice['customer_id'])
                    ->read(['name'])
                    ->first();
                self::id($id)->update(['invoice_number' => '[proforma]['.$customer['name'].']['.date('Y-m-d').']']);
            }
        }
    }

}