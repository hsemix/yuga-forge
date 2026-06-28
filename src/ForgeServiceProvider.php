<?php

namespace Yuga\Forge;

use Yuga\Forge\Console\MakeResourceCommand;
use Yuga\Interfaces\Application\Application;
use Yuga\Providers\ServiceProvider;
use Yuga\Providers\Shared\MakesCommandsTrait;

class ForgeServiceProvider extends ServiceProvider
{
    use MakesCommandsTrait;

    public function load(Application $app)
    {
        if ($app->runningInConsole()) {
            $app->singleton('command.forge.make-resource', fn () => new MakeResourceCommand());

            $this->commands('command.forge.make-resource');
        }

        return $app;
    }
}
