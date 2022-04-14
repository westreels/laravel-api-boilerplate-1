<?php

namespace App\Console\Commands;
use App\Models\Slotlayer\GameoptionsParent;
use App\Models\Slotlayer\LogImportant;
use App\Models\Slotlayer\AccessProfiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ResetHourlyMaximums extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slotlayer:resethourlymaximums';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset hourly maximums';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $parent_apiprofiles = GameoptionsParent::all();
        
        foreach($parent_apiprofiles as $apiprofile) {
            try {
            $getAccessProfile = AccessProfiles::where('id', $apiprofile->access_profile)->first();

            if(!$getAccessProfile) {
                LogImportant::insertImportantLog('4', 'Error while resetting hourly maximums for '.$apiprofile->apikey_parent.' owned by '.$apiprofile->ownedBy.' - was unable to find access profile matching the access profile.');
            } else {
                $max_hourly_demosessions = $getAccessProfile->max_hourly_demosessions;
                $max_hourly_callback_errors = $getAccessProfile->max_hourly_callback_errors;
                $max_hourly_createsession_errors = $getAccessProfile->max_hourly_createsession_errors;

            $apiprofile->update([
                'hourly_spare_demosessions' => $max_hourly_demosessions,
                'hourly_spare_callback_errors' => $max_hourly_callback_errors,
                'hourly_spare_createsession_errors' => $max_hourly_createsession_errors,
            ]);

            }
            } catch (Throwable $e) {
                LogImportant::insertImportantLog('5', 'Error while resetting hourly maximums');
        }

        }
    }
}