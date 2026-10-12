<?php

namespace App\Modules\Shared;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RuntimeException;

class SharedServiceProvider extends ServiceProvider
{
    /**
     * Factories stay in database/factories while models live in modules, so
     * Laravel's namespace-based guessing is replaced with an explicit lookup.
     */
    public function boot(): void
    {
        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        Factory::guessModelNamesUsing(function (Factory $factory): string {
            $model = Str::beforeLast(class_basename($factory), 'Factory');

            foreach (glob(app_path("Modules/*/Models/{$model}.php")) ?: [] as $file) {
                return 'App\\Modules\\'.basename(dirname($file, 2)).'\\Models\\'.$model;
            }

            throw new RuntimeException('No module model found for factory '.$factory::class);
        });
    }
}
