<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            'CASH' => 'Tunai (Cash)',
            'TRANSFER' => 'Transfer Bank',
            'ONLINE' => 'QRIS / Virtual Account',
        ];

        foreach ($methods as $code => $name) {
            PaymentMethod::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'is_active' => true]
            );
        }
    }
}
