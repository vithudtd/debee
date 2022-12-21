<?php

namespace Apptimus\Debee\Console\Commands;

use Apptimus\Debee\Model\Preference;
use Apptimus\Debee\Model\Version;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DebeeConnect extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debee:connect';
    // {--queue=default}

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Connect to debee project.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // $queueName = $this->option('queue');
        // $this->info($queueName);
        $client = new Client();
        try {
            DB::statement('CREATE TABLE `zz_atc_preference` ( `id` bigint unsigned NOT NULL AUTO_INCREMENT, `key` VARCHAR(200) NOT NULL, `value` VARCHAR(200) NOT NULL, `remarks` TEXT, `created_at` TIMESTAMP NULL DEFAULT NULL, `updated_at` TIMESTAMP NULL DEFAULT NULL, `deleted_at` TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (`id`) )');
        } catch (\Throwable $th) {
            //
        }

        $key = $this->ask('Project key');
        try {
            $res = $client->request('GET', config('debee.app_url').'/debee/connect?key='.$key, [
                'form_params' => [
                    //
                ]
            ]);

            $response = $res->getBody()->getContents();
            $dataP = json_decode($response);

            if (isset($dataP->project) && $dataP->project != '') {
                $project = $dataP->project;
                $username = $this->ask('Username');
                $password = $this->secret('Password');

                $res = $client->request('GET', config('debee.app_url').'/user/check?email='.$username."&password=".$password, [
                    'form_params' => [
                        //
                    ]
                ]);

                $response = $res->getBody()->getContents();
                $data = json_decode($response);

                if ($data->success == true) {
                    $preference = Preference::where('key','DEBEE_PROJECT_KEY')->first();
                    if (isset($preference)) {
                        $preference->value = $key;
                    }
                    else {
                        $preference = new Preference();
                        $preference->key = 'DEBEE_PROJECT_KEY';
                        $preference->value = $key;
                    }
                    $preference->save();

                    $preferenceU = Preference::where('key','DEBEE_USER_ID')->first();
                    if (isset($preferenceU)) {
                        $preferenceU->value = $data->user->id;
                    }
                    else {
                        $preferenceU = new Preference();
                        $preferenceU->key = 'DEBEE_USER_ID';
                        $preferenceU->value = $data->user->id;
                    }
                    $preferenceU->save();

                    $this->info('Your project is connected with the Debee [Project: '.$project->name.']');
                } else {
                    if ($data->type == 'username') {
                        $this->error('Invalid username');
                        $this->line('Create the Debee account with this command :');
                        $this->info('php artisan debee:user create');
                    } else {
                        $this->error('Password is incorrect');
                    }
                }
            } else {
                $this->error("Invalid project key.");
            }

        } catch (\Throwable $th) {
            $this->error("Oops something went wrong.");
            $this->line('Invalid http request.');
            $this->error($th->getMessage());
        }
        return 0;
    }
}
