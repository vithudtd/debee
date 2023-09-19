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
        $is_multi_tenant = '0';
        try {
            $preference = Preference::where('key','DEBEE_PROJECT_KEY')->first();
            $preferenceUser = Preference::where('key','DEBEE_USER_ID')->first();
            $preferenceIsMultitenant = Preference::where('key','IS_MULTI_TENANT')->first();
            if (isset($preference)) {
                $prodject_key = $preference->value;
            }
            if (isset($preferenceUser)) {
                $user_id = $preferenceUser->value;
            }
            if (isset($preferenceIsMultitenant)) {
                $is_multi_tenant = $preferenceIsMultitenant->value;
            }
        } catch (\Throwable $th) {
            //
        }


        if (isset($prodject_key) && $prodject_key != null && $prodject_key != '') {
            if (isset($user_id) && $user_id != null && $user_id != '') {
                $is_root_db_changes = '1';
                if ($is_multi_tenant == '1') {
                    $whichDbChanges = $this->choice('Which database change do you want to push?', ['Root Database', 'Tenant Database'], 0);
                    $is_root_db_changes = $whichDbChanges == 'Root Database' ? '1' : '0';
                }

                $client = new Client();
                $res = $client->request('GET', config('debee.app_url').'/connect?key='.$prodject_key, [
                    'form_params' => [
                        //
                    ]
                ]);

                $response = $res->getBody()->getContents();
                $data = json_decode($response);

                if (isset($data->project) && $data->project != '') {
                    $this->comment(config('debee.app_url').'/db-changes?x='.$prodject_key."&y=".$user_id.'&is_root='.$is_root_db_changes);
                } else {
                    $this->error("Invalid project key.");
                    $this->line('re-connect the Debee project with this command :');
                    $this->comment('php artisan debee:connect');
                }
            }
            else {
                $this->error("Debee connection is failed.");
                $this->line('Connect the Debee project with this command :');
                $this->comment('php artisan debee:connect');
            }
        }
        else {
            $this->error("Debee connection is failed.");
            $this->line('Connect the Debee project with this command :');
            $this->comment('php artisan debee:connect');
        }

        return 0;
    }
}
