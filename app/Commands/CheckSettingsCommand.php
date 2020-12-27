<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class CheckSettingsCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'check:config
    {site_config_file : The JSON file that holds the site info (required)}
    ';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Confirm that all settings on this new site look good';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
    	//check config file exits
        //check Folder is not there
		//check mysql is running
		//check url is not taken local and staging
		//check bitbucket project key
    }
}
