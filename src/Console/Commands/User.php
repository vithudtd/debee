<?php

namespace Apptimus\Debee\Console\Commands;

use Apptimus\Debee\Model\Preference;
use Illuminate\Console\Command;
use GuzzleHttp\Client;

class User extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debee:user {reference}';

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
            $username = $this->ask('Username');
            if ($username == '') {
                $this->error('Invalid username');
                return 0;
            }
            $password = $this->ask('Password');
            if ($password == '') {
                $this->error('Invalid password');
                return 0;
            }

            $res = $client->request('GET', config('debee.app_url').'/user/create?u='.$username.'&p='.$password, [
                'form_params' => [
                    //
                ]
            ]);

            $response = $res->getBody()->getContents();
            $data = json_decode($response);
            if ($data->success == true) {
                $this->info('User has been successfully created');
            } else {
                $this->error($data->error);
            }
        }
        else if ($reference == 'show') {
            $res = $client->request('GET', config('debee.app_url').'/user/show', [
                'form_params' => [
                    //
                ]
            ]);

            $response = $res->getBody()->getContents();
            $data = json_decode($response);
            if ($data->success == true) {
                $this->info('users');
                $this->info('------------');
                foreach ($data->data as $key => $user) {
                    $this->line($user->name);
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
