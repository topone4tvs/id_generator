<?php
namespace Jiuyan\IdGenerator\Provider;

use Illuminate\Support\ServiceProvider;
use Jiuyan\IdGenerator\Generator\ApcIdGenerator;
use Jiuyan\IdGenerator\Generator\ApcIdGeneratorFactory;
use Monolog\Logger;

class IdGeneratorProvider extends ServiceProvider
{

    public function register()
    {
        $this->registerConfig();
        $this->app->configure('id_generator');
        $generator = $this->app['config']->get('id_generator.database');
        ApcIdGeneratorFactory::setConfig($generator);
        ApcIdGenerator::setLogger($this->app['log']);


    }
    public function registerConfig()
    {
        $path = realpath(__DIR__ . '/../../../config/id_generator.php');

        $this->publishes([
            $path => config_path('id_generator.php'),
        ], 'config');
        $this->mergeConfigFrom($path, 'id_generator');

    }
}
