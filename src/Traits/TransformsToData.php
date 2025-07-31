<?php

namespace HassanDomeDenea\HddLaravelHelpers\Traits;

use Illuminate\Support\Str;
use LogicException;
use Spatie\LaravelData\Data;
use Throwable;

trait TransformsToData
{
    /**
     * Create a new data object for the given data.
     *
     * @param  class-string<Data>|null  $dataClass
     * @return Data
     *
     * @throws Throwable
     */
    public function toData(?string $dataClass = null): Data
    {
        if ($dataClass === null) {
            return $this->guessData();
        }

        return $dataClass::from($this);
    }

    /**
     * Guess the data class for the model.
     *
     * @return Data
     *
     * @throws Throwable
     */
    protected function guessData(): Data
    {
        foreach (static::guessDataName() as $dataClass) {
            if (is_string($dataClass) && class_exists($dataClass)) {
                return $dataClass::from($this);
            }
        }

        throw new LogicException(sprintf('Failed to find data class for model [%s].', get_class($this)));
    }

    /**
     * Guess the data class name for the model.
     *
     * @return array<class-string<Data>>
     */
    public static function guessDataName(): array
    {
        $modelClass = static::class;

        if (! Str::contains($modelClass, '\\Models\\')) {
            return [];
        }

        $relativeNamespace = Str::after($modelClass, '\\Models\\');

        $relativeNamespace = Str::contains($relativeNamespace, '\\')
            ? Str::before($relativeNamespace, '\\'.class_basename($modelClass))
            : '';

        $potentialData = sprintf(
            '%s\\Data\\%s%s',
            Str::before($modelClass, '\\Models'),
            strlen($relativeNamespace) > 0 ? $relativeNamespace.'\\' : '',
            class_basename($modelClass)
        );

        return [$potentialData.'Data', $potentialData];
    }
}
