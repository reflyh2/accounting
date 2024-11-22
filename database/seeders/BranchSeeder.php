<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Branch;
use App\Models\BranchGroup;
use Faker\Factory as Faker;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID'); // Using Indonesian locale

        // Get all branch group IDs
        $branchGroupIds = BranchGroup::pluck('id')->toArray();

        // Create 200 branches
        for ($i = 0; $i < 200; $i++) {
            Branch::create([
                'name' => $faker->company . ' Branch',
                'address' => $faker->address,
                'branch_group_id' => $faker->randomElement($branchGroupIds),
            ]);
        }
    }
}
