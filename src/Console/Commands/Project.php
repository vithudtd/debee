<?php

namespace Apptimus\Debee\Console\Commands;

use Apptimus\Debee\Model\Preference;
use Illuminate\Console\Command;
use GuzzleHttp\Client;

class Project extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debee:project {reference}';

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
        $client = new Client();
        $reference = $this->argument('reference');
        if ($reference == 'create') {
            $name = $this->ask('Project name?');
            if ($name == '') {
                $this->error('Invalid name');
                return 0;
            }

            $res = $client->request('GET', config('debee.app_url').'/project/create?n='.$name, [
                'form_params' => [
                    //
                ]
            ]);

            $response = $res->getBody()->getContents();
            $data = json_decode($response);
            if ($data->success == true) {
                $this->info('Project has been successfully created');
                $this->line('Project Key => '.$data->project->key);
            } else {
                $this->error($data->error);
            }
        }
        else if ($reference == 'show') {
            $res = $client->request('GET', config('debee.app_url').'/project/show', [
                'form_params' => [
                    //
                ]
            ]);

            $response = $res->getBody()->getContents();
            $data = json_decode($response);
            if ($data->success == true) {
                $this->info('projects');
                $this->info('------------');
                foreach ($data->data as $key => $project) {
                    $this->line($project->name." => ".$project->key);
                }
            } else {
                $this->error($data->error);
            }
        }
        else {
            $this->error('Invalid preference');
        }

        return 0;
    }
}
