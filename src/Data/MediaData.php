<?php

declare(strict_types=1);

namespace HassanDomeDenea\HddLaravelHelpers\Data;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class MediaData extends Data
{



    public function __construct(
        public int $id,
        public string $name,
        public string $extension,
        public string $fileName,
        public string $previewUrl,
        public string $originalUrl,
        public array $customProperties,
        public string $mimeType,
        public string $type,
        public string $humanReadableSize,
        public int|float $size,
        public ?Carbon $date = null,
        public ?string $description = null,
        public ?Carbon $createdAt = null,
    ) {
        $this->date = ! empty($customProperties['date']) ? Carbon::parse($customProperties['date']) : $this->createdAt;
        $this->description = ! empty($customProperties['description']) ? $customProperties['description'] : null;
    }


}
