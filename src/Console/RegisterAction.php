<?php

	namespace Fazzinipierluigi\LaravelRails\Console;

	use Illuminate\Console\Command;
	use function Laravel\Prompts\info;
	use function Laravel\Prompts\error;
	use function Laravel\Prompts\text;

	class RegisterAction extends Command
	{
		protected $signature = 'automata:action:register {class_name}';

		protected $description = 'Register a new action inside Atutomata system';

		public function handle()
		{
			$class_name = $this->argument('class_name');

			if(!class_exists($class_name))
			{
				error('The provided class does not exist');
				return Command::INVALID;
			}

			if(!in_array("Fazzinipierluigi\LaravelRails\Interfaces\ActionInterface", class_implements($class_name)))
			{
				error('The class provided must implement the interface \Fazzinipierluigi\LaravelRails\Interfaces\ActionInterface');
				return Command::INVALID;
			}

			$display_name = '';
			if(!empty($class_name::$display_name))
				$display_name = $class_name::$display_name;
			else
			{
				$default = explode('\\',$class_name);
				$default = $default[count($default)-1];
				$default = explode('.',$default)[0];

				$display_name = text(
					label: 'What is the display name?',
					default: $default,
					required: true
				);
			}

			$registered_action = \Fazzinipierluigi\LaravelRails\Models\RegisteredAction::where('action','=',$class_name)
																					  ->first();
			if(empty($registered_action))
			{
				$registered_action = new \Fazzinipierluigi\LaravelRails\Models\RegisteredAction();
				$registered_action->display_name = $display_name;
				$registered_action->action = $class_name;
				$registered_action->save();

				info('Action recorded');
			}
			else
			{
				$registered_action->display_name = $display_name;
				$registered_action->save();

				info('Action updated');
			}

			return Command::SUCCESS;
		}
	}
