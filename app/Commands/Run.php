<?php

namespace App\Commands;

use App\MTSite;
use App\Traits\GetConfigFile;
use App\Traits\MustRunProcess;
use LaravelZero\Framework\Commands\Command;


class Run extends Command
{
	use MustRunProcess;
	use GetConfigFile;
	/**
	 * The signature of the command.
	 *
	 * @var string
	 */
	protected $signature = 'run
	{site_config_file : The JSON file that holds the site info (required)}';

	/**
	 * The description of the command.
	 *
	 * @var string
	 */
	protected $description = 'The main script to make the site and do all the fun stuff';

	/**
	 * Execute the console command.
	 *
	 * @return mixed
	 */
	public function handle()
	{
		$site_config_file = $this->argument( 'site_config_file' );

		$this->call( 'setup:local-wp', [ 'site_config_file' => $site_config_file ] );
		$this->call( 'setup:wp-plugins', [ 'site_config_file' => $site_config_file ] );
		$this->call( 'setup:wp-theme', [ 'site_config_file' => $site_config_file ] );
		$this->call( 'setup:wp-cpt', [ 'site_config_file' => $site_config_file ] );
		$this->call( 'setup:wp-flex', [ 'site_config_file' => $site_config_file ] );
		$this->call( 'setup:wp-pages', [ 'site_config_file' => $site_config_file ] );
		$this->call( 'setup:git', [ 'site_config_file' => $site_config_file ] );
		$this->call( 'setup:bitbucket', [ 'site_config_file' => $site_config_file ] );
		$this->call( 'setup:forge', [ 'site_config_file' => $site_config_file ] );
		$this->call( 'setup:local-mamp', [ 'site_config_file' => $site_config_file ] );

		return true;
	}



}
