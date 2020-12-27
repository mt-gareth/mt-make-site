<?php

namespace App\Commands;

use App\MTSite;
use LaravelZero\Framework\Commands\Command;

class SetupLocalMampCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'setup:local-mamp
    {site_config_file : The JSON file that holds the site info (required)}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Adds the host to MAMP';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
		$mt_site = MTSite::getInstance($this->argument( 'site_config_file' ));

		$this->task( $this->description, function () use ( $mt_site ) {
			return $mt_site->setup_local_mamp();
		} );

		return true;
    }

}