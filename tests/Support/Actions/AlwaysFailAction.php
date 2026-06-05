<?php

namespace Fazzinipierluigi\LaravelRails\Tests\Support\Actions;

use Fazzinipierluigi\LaravelRails\Interfaces\ActionInterface;

class AlwaysFailAction implements ActionInterface
{
    public static string $display_name    = 'Always Fail';
    public static array  $configuration_schema = [];

    public function execute($instance, $entity, ?array $configuration, $destination_state): bool
    {
        return false;
    }
}
