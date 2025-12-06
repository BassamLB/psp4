<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AssignFamilies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'families:assign';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign families to voters based on parent-child relationships';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Running family assignment...');

        $job = new \App\Jobs\AssignFamiliesToVoters;
        $job->handle();

        $this->info('Family assignment completed!');
    }
}
