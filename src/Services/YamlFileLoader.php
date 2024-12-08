<?php

namespace HassanDomeDenea\HddLaravelHelpers\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Translation\FileLoader;
use RuntimeException;
use Symfony\Component\Yaml\Parser;

class YamlFileLoader extends FileLoader
{
    protected function loadJsonPaths($locale)
    {
        return collect(array_merge($this->jsonPaths, $this->paths))
            ->reduce(/**
       * @throws FileNotFoundException
       */ function ($output, $path) use ($locale) {

                if ($this->files->exists($full = "$path/$locale.json")) {
                    $decoded = json_decode($this->files->get($full), true);

                    if (is_null($decoded) || json_last_error() !== JSON_ERROR_NONE) {
                        throw new RuntimeException("Translation file [$full] contains an invalid JSON structure.");
                    }

                    $output = array_merge($output, $decoded);
                } elseif ($this->files->exists($full = "$path/$locale.yml")) {
                    $decoded = $this->parseYamlOrLoadFromCache($full);
                    $output = array_merge($output, $decoded);
                } elseif ($this->files->exists($full = "$path/$locale.yaml")) {
                    $decoded = $this->parseYamlOrLoadFromCache($full);
                    if ($decoded) {
                        $output = array_merge($output, $decoded);
                    }
                }

                return $output;
            }, []);
    }

    /**
     * @throws FileNotFoundException
     */
    protected function parseYamlOrLoadFromCache($file)
    {
        $cashedFile = storage_path().'/framework/cache/yaml.lang.cache.'.md5($file).'.php';
        if (@filemtime($cashedFile) < filemtime($file)) {
            $parser = new Parser;
            $content = $parser->parse(file_get_contents($file));
            file_put_contents($cashedFile, '<?php'.PHP_EOL.PHP_EOL.'return '.var_export($content, true).';');

            return $content;
        }

        return $this->files->getRequire($cashedFile);
    }
}
