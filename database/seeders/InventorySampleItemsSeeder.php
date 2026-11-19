<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Superseded. The trial items this seeder used to add were replaced by the official stock
 * catalogue, which the migrations install. This file does nothing and can be deleted.
 */
class InventorySampleItemsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('Nothing to seed: the official stock catalogue is installed by the migrations.');
    }
}
