<?php
namespace App\Traits;

use Illuminate\Support\Facades\Storage;

trait GetConfigFile
{
	private function get_config_file($location)
	{

		if ( !Storage::exists( $location ) ) {
			$this->error( "File Not Found" );
			return false;
		}
		$site_config = json_decode( Storage::get( $location ) );
		$this->info( print_r( $site_config, true ) );
		return $site_config;
	}
}