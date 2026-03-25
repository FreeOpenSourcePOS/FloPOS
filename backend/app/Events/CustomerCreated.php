<?php

namespace App\Events;

use App\Models\Tenant\Customer;

class CustomerCreated
{
    public function __construct(
        public readonly Customer $customer,
        public readonly int $tenantId,
    ) {}
}
