<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\BranchGroup;
use App\Models\Branch;
use App\Models\User;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample company
        $company = Company::create([
            'name' => 'PT. Sample Indonesia',
            'legal_name' => 'PT. Sample Indonesia Tbk.',
            'address' => 'Jl. Contoh No. 123',
            'city' => 'Jakarta',
            'province' => 'DKI Jakarta',
            'postal_code' => '12345',
            'phone' => '021-12345678',
        ]);

        // Create main branch group
        $branchGroup = BranchGroup::create([
            'name' => 'Pusat',
            'company_id' => $company->id,
        ]);

        // Create main branch
        $branch = Branch::create([
            'name' => 'Kantor Pusat',
            'address' => 'Jl. Contoh No. 123',
            'branch_group_id' => $branchGroup->id,
        ]);
        
        // Attach first user to branch
        $user = User::first();
        $user->branches()->attach($branch);
        $user->save();
    }
}
