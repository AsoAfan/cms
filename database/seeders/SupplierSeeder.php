<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $suppliers = [
            ['Northwind Textiles', '020 7946 0102', 'orders@northwind-textiles.example', 'Unit 4, Mill Road, Leeds'],
            ['Contoso Fabrics', '020 7946 0311', 'sales@contoso-fabrics.example', '18 Weaver Street, Manchester'],
            ['Fabrikam Hardware', '020 7946 0577', null, '92 Foundry Lane, Birmingham'],
        ];

        foreach ($suppliers as [$name, $phone, $email, $address]) {
            Supplier::query()->firstOrCreate(
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
