<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['full_name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'phone' => '09171234567'],
            ['full_name' => 'Maria Clara', 'email' => 'maria@example.com', 'phone' => '09182345678'],
            ['full_name' => 'Crisostomo Ibarra', 'email' => 'ibarra@example.com', 'phone' => '09193456789'],
            ['full_name' => 'Andres Bonifacio', 'email' => 'andres@example.com', 'phone' => '09204567890'],
            ['full_name' => 'Gregoria De Jesus', 'email' => 'gregoria@example.com', 'phone' => '09215678901'],
        ];

        foreach ($customers as $customer) {
            Customer::updateOrCreate(
                ['email' => $customer['email']],
                $customer,
            );
        }

        $this->call(ProductSeeder::class);

        $this->command->info('Sample data seeded. Create the first admin with php artisan admin:bootstrap.');
    }
}
