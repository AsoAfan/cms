<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * The walk-in customer comes first and must always exist: every sale names a
     * buyer, and counter trade is recorded against this one so that requirement
     * costs nobody a keystroke.
     */
    use WithoutModelEvents;

    public function run(): void
    {
        Customer::query()->firstOrCreate(
            ['name' => Customer::WALK_IN],
            [
                'notes' => 'Counter trade with nobody named. The sale screen opens on this one.',
                'is_active' => true,
            ]
        );

        $customers = [
            ['Ahmed Karim', '0770 145 8823', 'ahmed.karim@example.com', 'Bakhtiari, Sulaymaniyah'],
            ['Layla Hassan', '0751 209 4471', null, 'Ashti City, Sulaymaniyah'],
            ['Rebaz Interiors', '0773 660 1290', 'orders@rebaz-interiors.example', 'Salim Street, Sulaymaniyah'],
        ];

        foreach ($customers as [$name, $phone, $email, $address]) {
            Customer::query()->firstOrCreate(
                ['name' => $name],
                [
                    'phone' => $phone,
                    'email' => $email,
                    'address' => $address,
                    'is_active' => true,
                ]
            );
        }
    }
}
