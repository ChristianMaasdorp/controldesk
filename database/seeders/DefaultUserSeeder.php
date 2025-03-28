<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DefaultUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        if (User::where('email', 'john.doe@helper.app')->count() == 0) {
            $user = User::create([
                'name' => 'John DOE',
                'email' => 'john.doe@helper.app',
                'password' => bcrypt('Passw@rd1'),
                'email_verified_at' => now()
            ]);
            $user->creation_token = null;
            $user->save();
        }
        if (User::where('email', 'admin@ncloud.africa')->count() == 0) {
            $user = User::create([
                'name' => 'Super Admin',
                'email' => 'admin@ncloud.africa',
                'password' => bcrypt('NextY$#@!09'),
                'email_verified_at' => now()
            ]);
            $user->creation_token = null;
            $user->save();
        }
    }
}
