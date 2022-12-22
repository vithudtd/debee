<?php

namespace Apptimus\Debee\Console\Commands;

use Apptimus\Debee\Model\Preference;
use Apptimus\Debee\Model\Version;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UpdateDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debee:pull';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update database version.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            $preference = Preference::where('key','DEBEE_PROJECT_KEY')->first();
            if (isset($preference)) {
                $prodject_key = $preference->value;
            }
        } catch (\Throwable $th) {
            //
        }

        if (isset($prodject_key) && $prodject_key != null && $prodject_key != '') {
            try {
                $currentVersion = Version::select('version')->orderBy('version','desc')->first();
                if (isset($currentVersion)) {
                    $currentVersion = $currentVersion->version;
                }
                else {
                    $currentVersion = 0;
                }
            } catch (\Throwable $th) {
                DB::statement('CREATE TABLE zz_atc_run_queries ( `id` bigint unsigned NOT NULL AUTO_INCREMENT, version VARCHAR(200) NOT NULL, query TEXT, status ENUM("FAILED","SUCCESS"), remarks TEXT, created_at TIMESTAMP NULL DEFAULT NULL, updated_at TIMESTAMP NULL DEFAULT NULL, deleted_at TIMESTAMP NULL DEFAULT NULL, PRIMARY KEY (id) )');
                $currentVersion = 0;
            }

            if(isset($currentVersion)) {
                try {
                    $client = new Client();
                    $res = $client->request('GET', config('debee.app_url').'/db-changes/pull?cv='.$currentVersion.'&project_key='.$prodject_key, [
                        'form_params' => [
                            //
                        ]
                    ]);

                    $response = $res->getBody()->getContents();
                    $data = json_decode($response);

                    if ($data->success == true) {
                        if ($data->merge_requests > 0) {
                            $this->error("There are ".$data->merge_requests." change requests pending, so the database cannot be upgraded.");
                            $this->info('--------Contact your project manager---------');
                            $this->info(config('debee.app_url').'/check-merge-request?cv='.$currentVersion.'&project_key='.$prodject_key);
                        } else {
                            if ($currentVersion >= $data->last_version) {
                                $this->info('Your database version is already up to date.');
                            } else {
                                try {
                                    $success = 0;
                                    $failed = 0;
                                    foreach ($data->data as $key => $value) {
                                        $version = new Version();
                                        $version->version = $value->version;
                                        $version->query = $value->query;
                                        try {
                                            DB::unprepared($value->query);
                                            $version->status = 'SUCCESS';
                                            $success++;
                                        } catch (\Throwable $th) {
                                            // $this->info($th->getMessage());
                                            $version->remarks = $th->getMessage();
                                            $version->status = 'FAILED';
                                            $failed++;
                                        }
                                        $version->save();
                                    }

                                    $this->info('Queries executed, '.$success.' success, '.$failed.' errors, 0 warnings => (V'.$currentVersion.' to V'.$data->last_version.')');
                                    $this->line('Your DB version is now '.$data->last_version);
                                    $currentVersion = $data->last_version;
                                } catch (\Throwable $th) {
                                    $this->error("There are some errors in the query.");
                                    $this->error($th->getMessage());
                                }
                            }
                        }
                    } else {
                        if ($data->type == 'project') {
                            $this->error("Invalid project key.");
                            $this->line('re-connect the Debee project with this command :');
                            $this->info('php artisan debee:connect');
                        } else {
                            $this->error("Invalid project key.");
                            $this->line('re-connect the Debee project with this command :');
                            $this->info('php artisan debee:connect');
                        }
                    }

                } catch (\Throwable $th) {
                    $this->error("Oops something went wrong.");
                    $this->line('Invalid http request.');
                    $this->error($th->getMessage());
                }
            }
            else {
                $this->error("Oops something went wrong.");
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
