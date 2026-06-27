<?php

namespace Yuga\Forge;

use Yuga\Interfaces\Application\Application;
use Yuga\Providers\ServiceProvider;

class ForgeServiceProvider extends ServiceProvider
{
    public function load(Application $app)
    {
        return $app;
    }
}
