<?php

namespace HassanDomeDenea\HddLaravelHelpers\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;

use Illuminate\Support\Str;
use function Laravel\Prompts\search;

class AddPermissionsCommand extends Command implements PromptsForMissingInput
{
    protected $signature = 'add-permissions {name}';

    protected $description = 'Add permissions of CRUD with specifying name';

    private FileSystem $fileSystem;

    public function __construct()
    {
        parent::__construct();
        $this->fileSystem = new Filesystem;
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

    public function getModels(string|null$filename=null): array
    {
        $path = $filename?:app_path() . '/Models';
        $out = [];
        $results = scandir($path);
        foreach ($results as $result) {
            if ($result === '.' or $result === '..') {
                continue;
            }
            $filename = $path . '/' . $result;
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
     * @throws FileNotFoundException
     */
    public function handle(): void
    {
        $name = ucfirst($this->argument('name'));
        if (! in_array($name, $this->getModels())) {
            $this->info('Wrong Model Name');

            return;
        }
        $path = app_path('Enums/PermissionsEnum.php');
        $contents = $this->fileSystem->get($path);
        $createRule = 'Create' . $name;
        $updateRule = 'Update' . $name;
        $viewRule = 'View' . $name;
        $deleteRule = 'Delete' . $name;
        $restoreRule = 'Restore' . $name;
        $forceDeleteRule = 'ForceDelete' . $name;

        $createSnake = Str::snake($createRule);
        $updateSnake = Str::snake($updateRule);
        $viewSnake = Str::snake($viewRule);
        $deleteSnake = Str::snake($deleteRule);
        $restoreSnake = Str::snake($restoreRule);
        $forceDeleteSnake = Str::snake($forceDeleteRule);

        if (Str::contains($contents, $createRule . ' =')) {
            $this->info('Rules Already Exists');
        } else {
            $newItems = <<<PHP
    case $createRule = '$createSnake';
    case $updateRule = '$updateSnake';
    case $viewRule = '$viewSnake';
    case $deleteRule = '$deleteSnake';
    case $restoreRule = '$restoreSnake';
    case $forceDeleteRule = '$forceDeleteSnake';

PHP;
            $contents = str_replace('}', $newItems . PHP_EOL . '}', $contents);
            $this->info('Rules Written');

        }

        $this->fileSystem->put($path, $contents);

        $policyFilePath = app_path('Policies/' . $name . 'Policy.php');
        $this->modifyPolicy($policyFilePath, $viewRule, $createRule, $updateRule, $deleteRule, $restoreRule, $forceDeleteRule);

        $this->info('Done');
    }

    /**
     * @throws FileNotFoundException
     */
    public function modifyPolicy(string $policyFilePath, string $viewRule, string $createRule, string $updateRule, string $deleteRule, string $restoreRule, string $forceDeleteRule): void
    {
        if ($this->fileSystem->exists($policyFilePath)) {
            $policyContents = $this->fileSystem->get($policyFilePath);

            //Remove comments
            preg_match_all('/\/\*\*(.*?)\*\//s', $policyContents, $matches);
            foreach ($matches[0] as $match) {
                $policyContents = str_replace($match, '', $policyContents);
            }

            //Remove spaces between methods
            $policyContents = preg_replace('/}(.*?)public/s', '}' . PHP_EOL . '    public', $policyContents);
            $policyContents = preg_replace('/\{(.*?)public/s', '{' . PHP_EOL . '    public', $policyContents, 1);

            //Add Permissions Enum usage
            $policyContents = preg_replace('/namespace App\\\Policies;(.*?)use App\\\Models/s', "namespace App\Policies;" . PHP_EOL . PHP_EOL . "use App\Enums\PermissionsEnum;" . PHP_EOL . "use App\Models", $policyContents);

            //Add body for each method:

            $policyContents = preg_replace_callback('/public function viewAny\((.*)\): bool(\s*\{)(\s*)(\/\/)(\s*})/', function ($i) use ($viewRule) {
                return str_replace($i[4], 'return $user->hasPermissionTo(PermissionsEnum::' . $viewRule . ');', $i[0]);
            }, $policyContents);

            $policyContents = preg_replace_callback('/public function view\((.*)\): bool(\s*\{)(\s*)(\/\/)(\s*})/', function ($i) use ($viewRule) {
                return str_replace($i[4], 'return $user->hasPermissionTo(PermissionsEnum::' . $viewRule . ');', $i[0]);
            }, $policyContents);

            $policyContents = preg_replace_callback('/public function create\((.*)\): bool(\s*\{)(\s*)(\/\/)(\s*})/', function ($i) use ($createRule) {
                return str_replace($i[4], 'return $user->hasPermissionTo(PermissionsEnum::' . $createRule . ');', $i[0]);
            }, $policyContents);

            $policyContents = preg_replace_callback('/public function update\((.*)\): bool(\s*\{)(\s*)(\/\/)(\s*})/', function ($i) use ($updateRule) {
                return str_replace($i[4], 'return $user->hasPermissionTo(PermissionsEnum::' . $updateRule . ');', $i[0]);
            }, $policyContents);

            $policyContents = preg_replace_callback('/public function delete\((.*)\): bool(\s*\{)(\s*)(\/\/)(\s*})/', function ($i) use ($deleteRule) {
                return str_replace($i[4], 'return $user->hasPermissionTo(PermissionsEnum::' . $deleteRule . ');', $i[0]);
            }, $policyContents);

            $policyContents = preg_replace_callback('/public function forceDelete\((.*)\): bool(\s*\{)(\s*)(\/\/)(\s*})/', function ($i) use ($forceDeleteRule) {
                return str_replace($i[4], 'return $user->hasPermissionTo(PermissionsEnum::' . $forceDeleteRule . ');', $i[0]);
            }, $policyContents);

            $policyContents = preg_replace_callback('/public function restore\((.*)\): bool(\s*\{)(\s*)(\/\/)(\s*})/', function ($i) use ($restoreRule) {
                return str_replace($i[4], 'return $user->hasPermissionTo(PermissionsEnum::' . $restoreRule . ');', $i[0]);
            }, $policyContents);
            $this->fileSystem->put($policyFilePath, $policyContents);
            $this->info('Policy Updated');
        }
    }
}
