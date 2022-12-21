<?php

namespace Apptimus\Debee\Console\Commands;

use Apptimus\Debee\Model\Preference;
use Apptimus\Debee\Model\Version;
use Illuminate\Console\Command;
use GuzzleHttp\Client;

class CheckVersion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debee:merge';

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
        try {
            $preference = Preference::where('key','DEBEE_PROJECT_KEY')->first();
            $preferenceUser = Preference::where('key','DEBEE_USER_ID')->first();
            if (isset($preference)) {
                $prodject_key = $preference->value;
            }if (isset($preferenceUser)) {
                $user_id = $preferenceUser->value;
            }
        } catch (\Throwable $th) {
            //
        }

        if (isset($prodject_key) && $prodject_key != null && $prodject_key != '') {
            if (isset($user_id) && $user_id != null && $user_id != '') {
                $client = new Client();
                try {
                    $client = new Client();
                    $res = $client->request('GET', config('debee.app_url').'/update-request?project_key='.$prodject_key.'&user_id='.$user_id, [
                        'form_params' => [
                            //
                        ]
                    ]);

                    $response = $res->getBody()->getContents();
                    $data = json_decode($response);
                    if ($data->status) {
                        $this->info('Success');
                    } else {
                        if ($data->type == 'project') {
                            $this->error("Invalid project key.");
                            $this->line('re-connect the Debee project with this command :');
                            $this->info('php artisan debee:connect');
                        }
                        elseif ($data->type == 'user') {
                            $this->error("Invalid user.");
                            $this->line('re-connect the Debee project with this command :');
                            $this->info('php artisan debee:connect');
                        }
                        else if ($data->type == 'user_permission') {
                            $this->error("You are not authorized to perform this action. please contact your project manager.");
                        }
                    }

                } catch (\Throwable $th) {
                    $this->error("Oops something went wrong.");
                    $this->line('Invalid http request.');
                    $this->error($th->getMessage());
                }
            }
            else {
                $this->error("Debee connection is failed.");
                $this->line('Connect the Debee project with this command :');
                $this->info('php artisan debee:connect');
            }
        }
        else {
            $this->error("Debee connection is failed.");
            $this->line('Connect the Debee project with this command :');
            $this->info('php artisan debee:connect');
        }

        return 0;
    }
}
