<?php

namespace App;

use App\Traits\HasErrors;
use App\Traits\MustRunProcess;
use Bitbucket\Client;
use Laravel\Forge\Forge;
use Symfony\Component\Process\Process;

class MTSite
{
	use MustRunProcess;
	use HasErrors;

	private object $config;
	private static $instance = null;

	public function __construct( $config_file )
	{
		$config = new MTSiteConfig( $config_file );
		if ( $config->isError() ) {
			$this->errors = [ ...$this->errors, ...$config->getErrors() ];
			return;
		}
		$this->config = $config->getConfig();
	}

	public static function getInstance($config_file)
	{
		if (self::$instance == null)
		{
			self::$instance = new MTSite($config_file);
		}

		return self::$instance;
	}

	public function setup_local_wp()
	{
		if ( $this->isError() ) return false;
		//make the folder
		if ( !$this->must_run_process( [ 'mkdir', $this->config->site_name ], env( 'SITES_ROOT' ) ) ) return false;
		//install WP
		if ( !$this->must_run_process( [ 'wp', 'core', 'download' ], $this->config->site_root ) ) return false;
		//setup the wp-config
		if ( !$this->must_run_process( [ 'wp', 'config', 'create', '--dbname=' . $this->config->db_name, '--dbuser=' . env( 'LOCAL_DB_USER' ), '--dbpass=' . env( 'LOCAL_DB_PASS' ), '--skip-check' ], $this->config->site_root ) ) return false;
		//make the new DB
		if ( !$this->must_run_process( [ 'wp', 'db', 'create' ], $this->config->site_root ) ) return false;
		//Install Into this new DB
		if ( !$this->must_run_process( [ 'wp', 'core', 'install', '--url=' . $this->config->site_url . ':8080', '--title=' . $this->config->site_title, '--admin_user=' . $this->config->admin_user_name, '--admin_password=' . $this->config->admin_user_pass, '--admin_email=' . $this->config->admin_user_email ], $this->config->site_root ) ) return false;

		return true;
	}

	public function setup_local_mamp()
	{
		if ( $this->isError() ) return false;
		$site_root = $this->config->site_root;
		$site_url = $this->config->site_url;

		$mamp_loc = '/Applications/MAMP\ PRO.app';
		$mamp_command = $mamp_loc . '/Contents/MacOS/MAMP\ PRO cmd';
		//quit mamp
		if ( !$this->must_run_process( [ 'osascript', '-e', 'quit app "MAMP Pro"' ] ) ) return false;
		//setup the new host in MAMP
		$process = Process::fromShellCommandline( "$mamp_command createHost $site_url $site_root" );
		$process->run();
		sleep( 5 );
		//Open MAMP back up
		$process = Process::fromShellCommandline( "open $mamp_loc" );
		$process->run();

		return true;
	}

	public function setup_wp_plugins()
	{
		if ( $this->isError() ) return false;
		$dir = env( 'REMOTE_PLUGIN_URL' );
		$remote_plugins = array_map(
			function ( $plugin ) use ( $dir ) {
				return $dir . $plugin;
			},
			explode( '|', env( 'REMOTE_PLUGINS' ) )
		);
		$wp_plugins = explode( '|', env( 'WP_PLUGINS' ) );

		if ( !$this->must_run_process( [ 'wp', 'plugin', 'delete', '--all' ], $this->config->site_root ) ) return false;
		if ( !$this->must_run_process( [ 'wp', 'plugin', 'install', ...$remote_plugins, '--activate' ], $this->config->site_root ) ) return false;
		if ( !$this->must_run_process( [ 'wp', 'plugin', 'install', ...$wp_plugins, '--activate' ], $this->config->site_root ) ) return false;

		return true;
	}

	public function setup_wp_theme()
	{
		if ( $this->isError() ) return false;
		if ( !$this->must_run_process( [ 'composer', 'create-project', 'roots/sage', $this->config->theme_name, 'dev-master' ], $this->config->themes_root, null, null, 240 ) ) return false;
		if ( !$this->must_run_process( [ 'wp', 'theme', 'activate', $this->config->theme_name ], $this->config->theme_root ) ) return false;
		if ( !$this->must_run_process( [ 'composer', 'require', 'motiontactic/sage-flex' ], $this->config->theme_root, null, null, 240 ) ) return false;
		if ( !$this->must_run_process( [ 'wp', 'acorn', 'publish:mt' ], $this->config->theme_root, null, null, 240 ) ) return false;
		if ( !$this->must_run_process( [ 'yarn' ], $this->config->theme_root, null, null, 240 ) ) return false;
		if ( !$this->must_run_process( [ 'yarn', 'build' ], $this->config->theme_root, null, null, 240 ) ) return false;

		return true;
	}

	public function setup_wp_cpt()
	{
		if ( $this->isError() ) return false;
		if ( !property_exists( $this->config, 'CPT' ) ) return true;

		foreach ( $this->config->CPT as $cpt ) {
			if ( !$this->must_run_process( [ 'wp', 'scaffold', 'post-type', $cpt->slug, '--theme=' . $this->config->theme_name, '--force' ], $this->config->site_root ) ) return false;
		}
		return true;
	}

	public function setup_wp_flex()
	{
		if ( $this->isError() ) return false;
		if ( !property_exists( $this->config, 'flex' ) ) return true;

		foreach ( $this->config->flex as $flex ) {
			$template = property_exists( $flex, 'template' ) ? $flex->template : 'default';
			if ( !$this->must_run_process( [ 'wp', 'acorn', 'make:flex', $flex->name, $template ], $this->config->theme_root ) ) return false;
		}
		return true;
	}

	public function setup_wp_pages()
	{
		if ( $this->isError() ) return false;
		if ( !property_exists( $this->config, 'pages' ) ) return true;
		$first = true;
		foreach ( $this->config->pages as $page ) {
			$page_id = $this->must_run_process( [ 'wp', 'post', 'create', '--post_type=page', "--post_title=$page->name", '--post_status=publish', '--porcelain' ], $this->config->site_root );
			if ( !$page_id ) return false;
			if($first) {
				$this->must_run_process( [ 'wp', 'option', 'update', 'page_on_front', (int)$page_id ], $this->config->site_root );
				$this->must_run_process( [ 'wp', 'option', 'update', 'show_on_front', 'page' ], $this->config->site_root );
			}
			if ( !property_exists( $page, 'flex' ) ) continue;
			foreach ( $page->flex as $flex ) {
				if ( !$this->must_run_process( [ 'wp', 'acorn', 'add:flex', $flex, (int)$page_id ], $this->config->site_root ) ) return false;
			}
			$first = false;
		}
		return true;
	}

	public function setup_git()
	{
		if ( $this->isError() ) return false;
		$site_root = $this->config->site_root;
		$p = Process::fromShellCommandline( "echo '.idea\n.DS_Store\nuploads\nwp-config.php\nyarn.lock\n' >> .gitignore", $site_root );
		$p->run();
		$p = Process::fromShellCommandline( "git init", $site_root );
		$p->run();
		$p = Process::fromShellCommandline( "git add -A", $site_root );
		$p->run();
		$p = Process::fromShellCommandline( "git commit -a -m 'Init'", $site_root );
		$p->run();
		$p = Process::fromShellCommandline( "git branch staging", $site_root );
		$p->run();

		return true;
	}

	public function setup_bitbucket()
	{
		if ( $this->isError() ) return false;
		$site_name = $this->config->site_name;
		$site_root = $this->config->site_root;

		$bucket_workspace = env( 'BITBUCKET_WORKSPACE' );
		$bucket_project_key = $this->config->bitbucket_project_key;
		$bucket_project_name = $this->config->site_title;

		$client = new Client();
		$client->authenticate(
			Client::AUTH_HTTP_PASSWORD,
			env( 'BITBUCKET_EMAIL' ),
			env( 'BITBUCKET_PASS' )
		);

		$client->workspaces( $bucket_workspace )->projects()->create( [
			'name'       => $bucket_project_name,
			'key'        => $bucket_project_key,
			'is_private' => true,
		] );

		$client->repositories()->workspaces( $bucket_workspace )->create( $site_name, [
			'scm'        => 'git',
			'is_private' => true,
			'project'    => [
				'key' => $bucket_project_key
			],
		] );

		$p = Process::fromShellCommandline( "git remote add origin git@bitbucket.org:$bucket_workspace/$site_name.git", $site_root );
		$p->run();
		$p = Process::fromShellCommandline( "git push origin master", $site_root, null, null, 270 );
		$p->run();
		$p = Process::fromShellCommandline( "git push origin staging", $site_root, null, null, 270 );
		$p->run();
		return true;
	}

	public function setup_forge()
	{
		if ( $this->isError() ) return false;

		$site_name = $this->config->site_name;
		$site_url = $this->config->site_url;
		$site_root = $this->config->site_root;
		$theme_name = $this->config->theme_name;
		$db_name = $this->config->db_name;

		$staging_domain = $this->config->forge_url;
		$forge_ssh = env( 'FORGE_SSH' );

		$forge = new Forge( env( 'FORGE_TOKEN' ) );

		$forge_site = false;
		foreach ( $forge->sites( env( 'FORGE_STAGING_ID' ) ) as $site ) {
			if ( $site->name === $staging_domain ) $forge_site = $site;
		}
		if ( !$forge_site ) {
			$forge_site = $forge->setTimeout( 270 )->createSite( env( 'FORGE_STAGING_ID' ), [
				'domain'       => $staging_domain,
				'project_type' => 'php',
				'directory'    => '/',
				'database'     => $db_name,
			] );
		}

		$database_id = false;
		foreach ( $forge->databases( env( 'FORGE_STAGING_ID' ) ) as $database ) {
			if ( $database->name === $db_name ) $database_id = $database->id;
		}

		if ( !$database_id ) {
			$this->error( 'Database on Forge was not created' );
			return false;
		}

		$db_user = $forge->databaseUser( env( 'FORGE_STAGING_ID' ), env( 'FORGE_DB_USER_ID' ) );
		$db_user_databases = $db_user->attributes[ 'databases' ];
		if ( !in_array( $database_id, $db_user_databases ) ) {
			$db_user_databases[] = $database_id;
			$db_user->update( [ 'databases' => $db_user_databases ] );
		}

		if ( $forge_site->repositoryStatus !== 'installed' ) {
			$forge_site->installGitRepository( [
				'provider'   => 'bitbucket',
				'repository' => env( 'BITBUCKET_WORKSPACE' ) . '/' . $site_name,
				'branch'     => 'staging',
				'composer'   => false,
			] );
		}


		$deployment_script = "cd /home/forge/$staging_domain
git pull origin staging
cd /home/forge/$staging_domain/wp-content/themes/$theme_name
composer install --no-interaction --prefer-dist --optimize-autoloader
yarn && yarn build:production
( flock -w 10 9 || exit 1
echo 'Restarting FPM...'; sudo -S service \$FORGE_PHP_FPM reload ) 9>/tmp/fpmlock";
		$forge_site->updateDeploymentScript( $deployment_script );

		$forge_site->enableQuickDeploy();
		$forge_site->deploySite();

		if ( !$this->must_run_process( [ 'wp', "--ssh=$forge_ssh/home/forge/$staging_domain", 'config', 'create', '--dbname=' . $db_name, '--dbuser=' . env( 'FORGE_DB_USER_NAME' ), '--dbpass=' . env( 'FORGE_DB_USER_PASS' ) ], $site_root ) ) return false;
		if ( !$this->must_run_process( [ 'wp', "--ssh=$forge_ssh/home/forge/$staging_domain", 'core', 'install', '--url=' . $staging_domain, '--title=TEMP', '--admin_user=temp', '--admin_password=temp', '--admin_email=gareth@motiontactic.com' ], $site_root ) ) return false;

		$p = Process::fromShellCommandline( "wp db export - | wp --ssh=$forge_ssh/home/forge/$staging_domain db import -", $site_root, null, null, 270 );
		$p->run();
		$p = Process::fromShellCommandline( "wp --ssh=$forge_ssh/home/forge/$staging_domain search-replace '//$site_url:8080' '//$staging_domain'", $site_root, null, null, 270 );
		$p->run();

		return true;
	}

}