<?php

namespace App;

use App\Traits\HasErrors;
use Bitbucket\Client;
use Bitbucket\Exception\RuntimeException;
use Illuminate\Support\Facades\Storage;

class MTSiteConfig
{
	use HasErrors;

	private object $config;

	public function __construct( $config_file )
	{
		if ( !Storage::exists( $config_file ) ) {
			$this->errors[] = "Config File Not Found";
			return;
		}

		$this->config = json_decode( Storage::get( $config_file ) );
		$this->verify_required();
		$this->set_defaults();
		$this->verify();
	}

	public function verify()
	{
		//check if local folder exits
		if ( Storage::exists( $this->config->site_root ) ) $this->errors[] = "The site folder already exists";
		//check if local host is taken
		if ( get_headers( $this->config->site_url . ':8080' ) ) $this->errors[] = "The local URL already exists";
		//check if staging url is taken
		if ( get_headers( $this->config->forge_url ) ) $this->errors[] = "The forge URL already exists";
	}

	private function verify_required()
	{
		$required_array = [
			'site_name',
		];
		foreach ( $required_array as $required ) {
			if ( !property_exists( $this->config, $required ) ) {
				$this->errors[] = "Config $required is required";
			}
		}
	}

	private function set_defaults()
	{
		if ( !property_exists( $this->config, 'db_name' ) ) $this->config->db_name = str_replace( '-', '_', $this->config->site_name );
		if ( !property_exists( $this->config, 'site_title' ) ) $this->config->site_title = ucwords( str_replace( '-', ' ', $this->config->site_name ) );
		if ( !property_exists( $this->config, 'admin_user_name' ) ) $this->config->admin_user_name = env( 'WP_USER_NAME' );
		if ( !property_exists( $this->config, 'admin_user_email' ) ) $this->config->admin_user_email = env( 'WP_USER_EMAIL' );
		if ( !property_exists( $this->config, 'admin_user_pass' ) ) $this->config->admin_user_pass = $this->generate_pass();
		if ( !property_exists( $this->config, 'theme_name' ) ) $this->config->theme_name = $this->config->site_name;


		$this->config->site_root = env( 'SITES_ROOT' ) . '/' . $this->config->site_name;
		$this->config->site_url = $this->config->site_name . env( 'LOCAL_URL_SUFFIX' );
		$this->config->forge_url = $this->config->site_name . env( 'STAGING_URL_SUFFIX' );
		$this->config->themes_root = $this->config->site_root . '/wp-content/themes';
		$this->config->theme_root = $this->config->themes_root . '/' . $this->config->theme_name;

		$this->set_bitbucket_project_key();
	}

	private function set_bitbucket_project_key()
	{
		$bucket_project_key = property_exists( $this->config, 'bitbucket_project_key' ) ? $this->config->bitbucket_project_key : $this->generate_new_key( $this->config->site_name );
		$client = new Client();
		$client->authenticate(
			Client::AUTH_HTTP_PASSWORD,
			env( 'BITBUCKET_EMAIL' ),
			env( 'BITBUCKET_PASS' )
		);
		$this->config->bitbucket_project_key = $this->generate_bitbucket_project_key( $client, $this->config->site_name, $bucket_project_key );
	}

	private function generate_bitbucket_project_key( $client, $site_name, $key_to_test, $tested_keys = [] )
	{
		try {
			$client->workspaces( env( 'BITBUCKET_WORKSPACE' ) )->projects()->show( $key_to_test );
		} catch ( RuntimeException $exception ) {
			return $key_to_test;
		}
		$tested_keys[] = $key_to_test;
		$key_to_test = $this->generate_new_key( $site_name, $tested_keys );
		return $this->generate_bitbucket_project_key( $client, $site_name, $key_to_test, $tested_keys );
	}

	private function generate_new_key( $site_name, $tested_keys = [] )
	{
		//take the first character of each word
		$array = explode( '-', $site_name );
		if ( count( $array ) >= 3 ) {
			$key = '';
			foreach ( array_slice( $array, 0, 3 ) as $word ) {
				$key .= ucfirst( $word[ 0 ] );
			}
			if ( !in_array( $key, $tested_keys ) ) return $key;
		}
		//take the three characters in the name
		$dashless = str_replace( '-', '', $site_name );
		if ( strlen( $dashless ) >= 3 ) {
			for ( $i = 0; $i < strlen( $dashless ) - 3; $i++ ) {
				$key = strtoupper( substr( $dashless, $i, 3 ) );
				if ( !in_array( $key, $tested_keys ) ) return $key;
			}
		}
		//take the first two and add a number after it
		if ( strlen( $dashless ) >= 2 ) {
			$key_base = substr( $dashless, 0, 2 );
			for ( $i = 0; $i < 10; $i++ ) {
				$key = strtoupper( $key_base . $i );
				if ( !in_array( $key, $tested_keys ) ) return $key;
			}
		}

		//just take the first letter and try adding any number after it
		$key_base = substr( $dashless, 0, 1 );
		for ( $i = 0; $i < 100; $i++ ) {
			if ( $i < 10 ) $i = '0' . $i;
			$key = strtoupper( $key_base . $i );
			if ( !in_array( $key, $tested_keys ) ) return $key;
		}

		//I give up just try some random letters
		return chr( rand( 65, 90 ) ) . chr( rand( 65, 90 ) ) . chr( rand( 65, 90 ) );
	}


	/**
	 * @param int $length
	 * @return string
	 */
	private function generate_pass( $length = 12 ): string
	{
		return substr( preg_replace( "/[^a-zA-Z0-9]/", "", base64_encode( openssl_random_pseudo_bytes( $length + 1, $strong ) ) ), 0, $length );
	}

	/**
	 * @return object
	 */
	public function getConfig(): object
	{
		return $this->config;
	}
}