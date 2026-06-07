<?php

	namespace Fazzinipierluigi\LaravelRails\Interfaces;

	interface ActionInterface
	{
		public function execute($instance, $entity, ?array $configuration, $destination_state): bool;
	}
