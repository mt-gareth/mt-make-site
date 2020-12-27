<?php

namespace App\Traits;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

trait MustRunProcess
{
	private function must_run_process( $cmd, $loc = null, $env = null, $input = null, $timeout = 60 )
	{
		$process = new Process( $cmd, $loc, $env, $input, $timeout );
		try {
			$process->mustRun();
			return $process->getOutput() ?: true;
		} catch ( ProcessFailedException $exception ) {
			echo $exception->getMessage();
			return false;
		}
	}
}