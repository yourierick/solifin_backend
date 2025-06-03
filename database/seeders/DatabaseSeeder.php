<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Vous pouvez décommenter les seeders que vous souhaitez exécuter
        // $this->call(UserSeeder::class);
        // $this->call(PackSeeder::class);
        $this->call(PublicationSeeder::class);
        $this->call(BonusPointsSeeder::class);
        $this->call(FaqSeeder::class);
        $this->call(JetonEsengoSeeder::class); // Seeder pour les jetons Esengo
    }
}
