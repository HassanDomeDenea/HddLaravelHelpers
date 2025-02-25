<?php

namespace HassanDomeDenea\HddLaravelHelpers\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Str;
use Touhidurabir\StubGenerator\StubGenerator;

use function Laravel\Prompts\search;

class CreateStandardModelCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'basic-model {name} {--only=} {--c|create? : Create Model} {--p|permissions? : With Permissions}';

    public function __construct()
    {
        parent::__construct();
    }

    protected function promptForMissingArgumentsUsing(): array
    {
        return [
            'name' => fn () => search(
                label: 'Search For Model Name',
                options: fn ($value) => $this->getModels(),
                placeholder: 'E.g. Patient'
            ),
        ];
    }

    public function getModels(?string $filename = null): array
    {
        $path = $filename ?: app_path().'/Models';
        $out = [];
        $results = scandir($path);
        foreach ($results as $result) {
            if ($result === '.' or $result === '..') {
                continue;
            }
            $filename = $path.'/'.$result;
            if (is_dir($filename)) {
                $out = array_merge($out, $this->getModels($filename));
            } else {
                $name = basename(substr($filename, 0, -4));
                if ($name !== 'BaseModel') {
                    $out[] = $name;
                }
            }
        }

        return $out;
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $only = $this->option('only') ? explode(',', $this->option('only')) : ['controller', 'model', 'factory', 'migration', 'seeder', 'requests'];

        $createModel = $this->option('create?');
        $withPermissions = $this->option('permissions?');
        if ($createModel) {
            $this->call('make:model', ['name' => $this->argument('name'), '--all' => true]);
            $this->info('Model files created!');
        }
        if ($withPermissions) {
            $this->call('add-permission', ['name' => $this->argument('name')]);
            $this->info('Permissions cases created!');
        }
        $name = ucfirst($this->argument('name'));
        if (! in_array($name, $this->getModels())) {
            $this->info('Wrong Model Name');

            return;
        }
        $modelName = $name;
        $snakeModel = Str::snake($modelName);
        $modelTable = Str::plural($snakeModel);
        $controllerName = $modelName.'Controller';
        $factoryName = $modelName.'Factory';
        $seederName = $modelName.'Seeder';
        $storeRequestName = 'Store'.$modelName.'Request';
        $updateRequestName = 'Update'.$modelName.'Request';

        $migrations = collect(scandir(database_path('migrations')));
        $migrationName = Str::remove('.php', $migrations->where(fn ($name) => Str::endsWith($name, "create_{$modelTable}_table.php"))->first()
          ?: date('Y_m_d_Hms_')."create_{$modelTable}_table.php");

        if (in_array('controller', $only)) {
            $stab = new StubGenerator;
            $stab->from(base_path('stubs/custom/controller.stub'), true)
                ->to(app_path('Http/Controllers'), true)
                ->as($controllerName)
                ->withReplacers([
                    'model' => $modelName,
                    'modelInstance' => $snakeModel,
                    'modelTable' => $modelTable,
                ])
                ->replace(true)
                ->save();
            $this->output->info('Controller Modified!');
        }

        if (in_array('factory', $only)) {
            $stab = new StubGenerator;

            $stab->from(base_path('stubs/custom/factory.stub'), true)
                ->to(database_path('factories'), true)
                ->as($factoryName)
                ->withReplacers([
                    'model' => $modelName,
                    'modelInstance' => $snakeModel,
                    'modelTable' => $modelTable,
                ])
                ->replace(true)
                ->save();
            $this->output->info('Factory Modified!');
        }

        if (in_array('migration', $only)) {
            $stab = new StubGenerator;

            $stab->from(base_path('stubs/custom/migration.stub'), true)
                ->to(database_path('migrations'), true)
                ->as($migrationName)
                ->withReplacers([
                    'model' => $modelName,
                    'modelInstance' => $snakeModel,
                    'modelTable' => $modelTable,
                ])
                ->replace(true)
                ->save();
            $this->output->info('Migration Modified!');

        }
        if (in_array('seeder', $only)) {
            $stab = new StubGenerator;

            $stab->from(base_path('stubs/custom/seeder.stub'), true)
                ->to(database_path('seeders'), true)
                ->as($seederName)
                ->withReplacers([
                    'model' => $modelName,
                    'modelInstance' => $snakeModel,
                    'modelTable' => $modelTable,
                ])
                ->replace(true)
                ->save();
            $this->output->info('Seeder Modified!');

        }
        if (in_array('model', $only)) {
            $stab = new StubGenerator;

            $stab->from(base_path('stubs/custom/model.stub'), true)
                ->to(app_path('Models'), true)
                ->as($modelName)
                ->withReplacers([
                    'model' => $modelName,
                    'modelInstance' => $snakeModel,
                    'modelTable' => $modelTable,
                ])
                ->replace(true)
                ->save();
            $this->output->info('Model Modified!');

        }
        if (in_array('requests', $only)) {

            $stab = new StubGenerator;

            $stab->from(base_path('stubs/custom/storeRequest.stub'), true)
                ->to(app_path('Http/Requests'), true)
                ->as($storeRequestName)
                ->withReplacers([
                    'model' => $modelName,
                    'modelInstance' => $snakeModel,
                    'modelTable' => $modelTable,
                ])
                ->replace(true)
                ->save();
            $this->output->info('Store Request Modified!');

            $stab = new StubGenerator;

            $stab->from(base_path('stubs/custom/updateRequest.stub'), true)
                ->to(app_path('Http/Requests'), true)
                ->as($updateRequestName)
                ->withReplacers([
                    'model' => $modelName,
                    'modelInstance' => $snakeModel,
                    'modelTable' => $modelTable,
                ])
                ->replace(true)
                ->save();
            $this->output->info('Update Request Modified!');

        }

        $this->info('Stubs generated!');
    }
}
