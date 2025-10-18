<?php

namespace HassanDomeDenea\HddLaravelHelpers\Traits;

use HassanDomeDenea\HddLaravelHelpers\Helpers\PathHelpers;
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
        /** @var class-string<Data>|false $dataClass */
        $dataClass = PathHelpers::getDataClassFromModelClass(static::class);
        if($dataClass){
            return $dataClass::from($this);
        }

        throw new LogicException(sprintf('Failed to find data class for model [%s].', get_class($this)));
    }
}
