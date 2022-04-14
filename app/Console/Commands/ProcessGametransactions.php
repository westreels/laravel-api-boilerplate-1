<?php

namespace App\Console\Commands;
use App\Models\Slotlayer\GameoptionsParent;
use App\Models\Slotlayer\LogImportant;
use App\Models\Slotlayer\AccessProfiles;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ProcessGameTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slotlayer:processgametransactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process game transactions to billing';

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

    }
}