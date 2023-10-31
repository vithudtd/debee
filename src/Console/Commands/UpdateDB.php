<?php

namespace Apptimus\Debee\Console\Commands;

use Apptimus\Debee\Model\Preference;
use Apptimus\Debee\Model\Version;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

class UpdateDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debee:pull {reference?}';

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
        $reference = $this->argument('reference');
        if ($reference != "" && $reference != "/all") {
            $this->error('Invalid reference '. $reference);
            return 0;
        }

        if ($reference == "/all") {
            $updateType = "ALL";
        } else {
            $updateType = "OTHER";
        }

        try {
            $preference = Preference::where('key','DEBEE_PROJECT_KEY')->first();
            if (isset($preference)) {
                $prodject_key = $preference->value;
            }
        } catch (\Throwable $th) {
            //
        }
        try {
            $user_id = Preference::where('key','DEBEE_USER_ID')->first();
            if (isset($user_id)) {
                $user_id = $user_id->value;
            }
        } catch (\Throwable $th) {
            $user_id = '';
        }
        try {
            $is_multi_tenant = Preference::where('key','IS_MULTI_TENANT')->first();
            if (isset($is_multi_tenant)) {
                $is_multi_tenant = $is_multi_tenant->value;
            }
        } catch (\Throwable $th) {
            $is_multi_tenant = '0';
        }

        if (isset($prodject_key) && $prodject_key != null && $prodject_key != '') {
            $is_root_db_changes = '1';
            if ($is_multi_tenant == '1') {
                $whichDbChanges = $this->choice('Which database change do you want to pull?', ['Root Database', 'Tenant Database'], 0);
                $is_root_db_changes = $whichDbChanges == 'Root Database' ? '1' : '0';
            }

            if ($is_root_db_changes == '1') {
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
                        $res = $client->request('GET', config('debee.app_url').'/db-changes/pull?cv='.$currentVersion.'&project_key='.$prodject_key.'&is_root='.$is_root_db_changes, [
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
                                $this->comment(config('debee.app_url').'/check-merge-request?cv='.$currentVersion.'&project_key='.$prodject_key.'&is_root='.$is_root_db_changes);
                            } else {
                                if ($currentVersion >= $data->last_version) {
                                    $this->info('Your database version is already up to date.');
                                } else {
                                    try {
                                        $success = 0;
                                        $failed = 0;
                                        $total = 0;
                                        foreach ($data->data as $key => $value) {
                                            $total++;
                                            $version = new Version();
                                            $version->version = $value->version;
                                            $version->query = $value->query;
                                            if ($updateType == "OTHER" && $user_id == $value->user_id && $value->is_run == 1) {
                                                $version->remarks = '****This query has already been executed in your database****';
                                                $version->status = 'SUCCESS';
                                            } else {
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
                                            }

                                            $version->save();
                                        }

                                        $this->info('Total queries : '.$total.', executed queries : '.($success + $failed));
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
                                $this->comment('php artisan debee:connect');
                            } else {
                                $this->error("Invalid project key.");
                                $this->line('re-connect the Debee project with this command :');
                                $this->comment('php artisan debee:connect');
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
                $client = new Client();
                $res = $client->request('GET', config('debee.app_url').'/projects/'.$prodject_key, [
                    'form_params' => [
                        //
                    ]
                ]);

                $response = $res->getBody()->getContents();
                $data = json_decode($response);

                $tenant_dbs_table_name = '';
                $tenant_db_column_name = '';

                try {
                    $tenant_dbs_table_name = $data->data->tenant_dbs_table_name;
                } catch (\Throwable $th) {
                    $this->error("Invalid tenant_dbs_table_name");
                    return 0;
                }

                try {
                    $tenant_db_column_name = $data->data->tenant_db_column_name;
                } catch (\Throwable $th) {
                    $this->error("Invalid tenant_db_column_name");
                    return 0;
                }

                $query = 'SELECT '.$tenant_db_column_name.' FROM '.$tenant_dbs_table_name;

                $dbSelect = $this->choice('Want to update all tenant databases?',['Yes - All tenant databases in the '.$tenant_dbs_table_name.' table','No - Certain databases'],0);
                if($dbSelect == 'No - Certain databases') {
                    $wantToSeeDbs = $this->choice('Want to see the tenant database`s name?', ['Yes','No'],0);

                    if($wantToSeeDbs == 'Yes') {
                        $tenantDbs1 = DB::select($query);
                        $headers1 = ['DB Name'];
                        $rows1 = [];
                        foreach ($tenantDbs1 as $tekey1 => $value) {
                            $rows1[$tekey1][0] = $value->$tenant_db_column_name;
                        }
                        $this->table($headers1, $rows1);
                    }

                    $selectedDbs = $this->ask('Please enter DB name (comma separated)');
                    $selectedDbArray = explode(',', $selectedDbs);
                    $quotedSelectedDbArray = array_map(function ($value) {
                        return "'" . trim($value) . "'";
                    }, $selectedDbArray);

                    $quotedSelectedDbs = implode(', ', $quotedSelectedDbArray);
                    $query .= ' WHERE ' . $tenant_db_column_name . ' IN (' . $quotedSelectedDbs . ')';
                }

                try {
                    $tenantDbs = DB::select($query);
                    $rows = [];
                    foreach ($tenantDbs as $tekey => $value) {
                        $rows[$tekey][0] = $value->$tenant_db_column_name;
                        $this->info('---------------------'.$value->$tenant_db_column_name.'---------------------------------------------------------');

                        Config::set('database.connections.mysql.database', $value->$tenant_db_column_name);
                        DB::reconnect('mysql');
                        Schema::connection('mysql')->getConnection()->reconnect();
                        Config::set('database.default', 'mysql');

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
                                $res = $client->request('GET', config('debee.app_url').'/db-changes/pull?cv='.$currentVersion.'&project_key='.$prodject_key.'&is_root='.$is_root_db_changes, [
                                    'form_params' => [
                                        //
                                    ]
                                ]);

                                $response = $res->getBody()->getContents();
                                $data = json_decode($response);

                                if ($data->success == true) {
                                    if ($data->merge_requests > 0) {
                                        $this->error("There are ".$data->merge_requests." change requests pending, so the database cannot be upgraded.");
                                        $this->comment(config('debee.app_url').'/check-merge-request?cv='.$currentVersion.'&project_key='.$prodject_key.'&is_root='.$is_root_db_changes);

                                        $rows[$tekey][1] = 'Success';
                                        $rows[$tekey][2] = '-';
                                        $rows[$tekey][3] = '-';
                                        $rows[$tekey][4] = '-';
                                        $rows[$tekey][5] = '-';
                                        $rows[$tekey][6] = '-';
                                        $rows[$tekey][7] = '-';
                                        $rows[$tekey][8] = '-';
                                        $rows[$tekey][9] = "".$data->merge_requests." change requests pending";
                                    } else {
                                        if ($currentVersion >= $data->last_version) {
                                            $this->info('Database version is already up to date.');
                                            $rows[$tekey][1] = 'Success';
                                            $rows[$tekey][2] = '-';
                                            $rows[$tekey][3] = '-';
                                            $rows[$tekey][4] = '-';
                                            $rows[$tekey][5] = '-';
                                            $rows[$tekey][6] = '-';
                                            $rows[$tekey][7] = '-';
                                            $rows[$tekey][8] = '-';
                                            $rows[$tekey][9] = 'DB version is already up to date.';
                                        } else {
                                            try {
                                                $success = 0;
                                                $failed = 0;
                                                $total = 0;
                                                foreach ($data->data as $key => $value) {
                                                    $total++;
                                                    $version = new Version();
                                                    $version->version = $value->version;
                                                    $version->query = $value->query;
                                                    if ($updateType == "OTHER" && $user_id == $value->user_id && $value->is_run == 1) {
                                                        $version->remarks = '****This query has already been executed in your database****';
                                                        $version->status = 'SUCCESS';
                                                    } else {
                                                        try {
                                                            DB::unprepared($value->query);
                                                            $version->status = 'SUCCESS';
                                                            $success++;
                                                        } catch (\Throwable $th) {
                                                            $version->remarks = $th->getMessage();
                                                            $version->status = 'FAILED';
                                                            $failed++;
                                                        }
                                                    }

                                                    $version->save();
                                                }

                                                $rows[$tekey][1] = 'Success';
                                                $rows[$tekey][2] = $total;
                                                $rows[$tekey][3] = $success + $failed;
                                                $rows[$tekey][4] = $success;
                                                $rows[$tekey][5] = $failed;
                                                $rows[$tekey][6] = 0;
                                                $rows[$tekey][7] = $currentVersion;
                                                $rows[$tekey][8] = $data->last_version;
                                                $rows[$tekey][9] = 'updated.';

                                                $this->info('Queries executed');
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
                                        $this->comment('php artisan debee:connect');
                                    } else {
                                        $this->error("Invalid project key.");
                                        $this->line('re-connect the Debee project with this command :');
                                        $this->comment('php artisan debee:connect');
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

                        $this->line('');
                    }
                } catch (\Throwable $th) {
                    $rows[$tekey][1] = 'Failed';
                    $rows[$tekey][2] = '-';
                    $rows[$tekey][3] = '-';
                    $rows[$tekey][4] = '-';
                    $rows[$tekey][5] = '-';
                    $rows[$tekey][6] = '-';
                    $rows[$tekey][7] = '-';
                    $rows[$tekey][8] = '-';
                    $rows[$tekey][9] = 'Please check above';
                    $this->comment("Oops something went wrong.");
                    $this->error($th->getMessage());
                    $this->line('');
                }

                $headers = ['DB Name', 'DB Connection', 'Total queries', 'Executed Queries', 'EQ: Success', 'EQ: Errors', 'EQ: warnings', 'Prev Version', 'Updated Version', 'Comment'];
                $this->table($headers, $rows);
            }
        }
        else {
            $this->error("Debee connection is failed.");
            $this->line('Connect the Debee project with this command :');
            $this->comment('php artisan debee:connect');
        }

        Config::set('database.connections.mysql.database',  env('DB_DATABASE'));
        DB::reconnect('mysql');
        Schema::connection('mysql')->getConnection()->reconnect();
        Config::set('database.default', 'mysql');

        return 0;
    }
}
