<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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

        $users = [
            ['username' => 'admin_juan', 'full_name' => 'Juan Dela Cruz', 'email' => 'juan@example.com'],
            ['username' => 'cashier_maria', 'full_name' => 'Maria Clara', 'email' => 'maria@example.com'],
            ['username' => 'manager_ibarra', 'full_name' => 'Crisostomo Ibarra', 'email' => 'ibarra@example.com'],
            ['username' => 'supervisor_andres', 'full_name' => 'Andres Bonifacio', 'email' => 'andres@example.com'],
            ['username' => 'staff_gregoria', 'full_name' => 'Gregoria De Jesus', 'email' => 'gregoria@example.com'],
        ];

        foreach ($users as $user) {
            $user['password'] = Hash::make('password123');
            User::updateOrCreate(
                ['username' => $user['username']],
                $user,
            );
        }

        $this->call(ProductSeeder::class);

        $this->command->info('Seeding completed successfully!');
    }
}
