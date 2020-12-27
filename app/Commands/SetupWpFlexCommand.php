<?php

namespace App\Commands;

use App\MTSite;
use LaravelZero\Framework\Commands\Command;

class SetupWpFlexCommand extends Command
{
	/**
	 * The signature of the command.
	 *
	 * @var string
	 */
	protected $signature = 'setup:wp-flex
	{site_config_file : The JSON file that holds the site info (required)}
	';

	/**
	 * The description of the command.
	 *
	 * @var string
	 */
	protected $description = 'Create Flexible Components';

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle()
	{
		$mt_site = MTSite::getInstance($this->argument( 'site_config_file' ));

		$this->task( $this->description, function () use ( $mt_site ) {
			return $mt_site->setup_wp_flex();
		} );

		return true;
	}

}
