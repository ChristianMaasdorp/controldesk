<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TicketsApiUserSeeder extends Seeder
{
    /**
     * Create the service user that the tickets write API acts as.
     * Set TICKETS_API_USER_ID to the id printed by this seeder.
     */
    public function run()
    {
        $user = User::firstOrCreate(
            ['email' => 'pipeline@ncloud.africa'],
            [
                'name' => 'Pipeline',
                'password' => bcrypt(Str::random(40)),
                'email_verified_at' => now(),
            ]
        );
        $user->creation_token = null;
        $user->save();

        $this->command?->info('Tickets API service user id: '.$user->id);
    }
}
