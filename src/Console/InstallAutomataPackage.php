<?php

	namespace Fazzinipierluigi\LaravelRails\Console;

	use Illuminate\Console\Command;
	use function Laravel\Prompts\info;

	class InstallAutomataPackage extends Command
	{
		protected $signature = 'automata:install';

		protected $description = 'Install the LaravelRails package';

		private $actions_to_register = [
			\Fazzinipierluigi\LaravelRails\Actions\SendEmail::class,
			\Fazzinipierluigi\LaravelRails\Actions\SetVariableWithEntity::class,
			\Fazzinipierluigi\LaravelRails\Actions\SetVariableWithFormula::class,
			\Fazzinipierluigi\LaravelRails\Actions\StartSubprocess::class,
			\Fazzinipierluigi\LaravelRails\Actions\WriteEntity::class,
		];

		public function handle()
		{
			info('Installing LaravelRails package...');

			info('Running migrations...');
			$this->call('migrate');

			info('Register default actions...');
			foreach($this->actions_to_register as $action)
			{
				info('Registrazione azione "'.$action.'"');
				$this->call("automata:action:register", [ "class_name" => $action ]);
			}

			info('Installed LaravelRails package');
		}

	}
