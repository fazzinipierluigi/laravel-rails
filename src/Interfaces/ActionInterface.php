<?php

	namespace Fazzinipierluigi\LaravelRails\Interfaces;

	interface ActionInterface
	{
		public function execute($instance, $entity, $configuration, $destination_state);
	}
