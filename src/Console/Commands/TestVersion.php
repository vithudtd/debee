<?php

namespace Apptimus\Debee\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;

class TestVersion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:request {reference}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('ASD');
    }
}
