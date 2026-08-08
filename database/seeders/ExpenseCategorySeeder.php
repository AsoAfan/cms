<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * A starting set most businesses recognise. They are ordinary rows, so any
     * of them can be renamed or removed.
     */
    public function run(): void
    {
        foreach (['Rent', 'Salaries', 'Transport', 'Utilities', 'Marketing', 'Miscellaneous'] as $name) {
            ExpenseCategory::query()->firstOrCreate(['name' => $name]);
        }
    }
}
