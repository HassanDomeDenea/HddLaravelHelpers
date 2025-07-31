<?php

namespace HassanDomeDenea\HddLaravelHelpers\Controllers;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController
{
    public function show($media): BinaryFileResponse
    {
        $media = Media::findOrFail($media);
        return response()->file($media->getPath());
    }
    public function download($media): BinaryFileResponse
    {
        $media = Media::findOrFail($media);
        return response()->download($media->getPath(),$media->getDownloadFilename());
    }
}
