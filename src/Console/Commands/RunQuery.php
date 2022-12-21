<?php

namespace Apptimus\Debee\Console\Commands;

use Apptimus\Debee\Model\Preference;
use Illuminate\Console\Command;
use GuzzleHttp\Client;

class RunQuery extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debee:push';

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
                $res = $client->request('GET', config('debee.app_url').'/debee/connect?key='.$prodject_key, [
                    'form_params' => [
                        //
                    ]
                ]);

                $response = $res->getBody()->getContents();
                $data = json_decode($response);

                if (isset($data->project) && $data->project != '') {
                    $this->info(config('debee.app_url').'/db-changes?x='.$prodject_key."&y=".$user_id);
                } else {
                    $this->error("Invalid project key.");
                    $this->line('re-connect the Debee project with this command :');
                    $this->info('php artisan debee:connect');
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
