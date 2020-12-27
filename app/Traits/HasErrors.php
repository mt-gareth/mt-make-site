<?php

namespace App\Traits;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

trait HasErrors
{
	protected array $errors = [];

	/**
	 * @return array
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}

	/**
	 * @return bool
	 */
	public function isError(): bool
	{
		return !empty( $this->errors );
	}
}