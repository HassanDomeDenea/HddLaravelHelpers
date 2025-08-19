<?php

namespace HassanDomeDenea\HddLaravelHelpers\Controllers;

use Carbon\Carbon;
use Exception;
use HassanDomeDenea\HddLaravelHelpers\Data\MediaData;
use HassanDomeDenea\HddLaravelHelpers\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Spatie\Image\Enums\Orientation;
use Spatie\Image\Image;
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
        return response()->download($media->getPath(), $media->getDownloadFilename());
    }

    /**
     * Used to update the date in custom_properties of media model
     *
     * @param int|string $media
     * @param Request $request
     * @return ApiResponse
     */
    public function updateDate(int|string $media, Request $request): ApiResponse
    {
        $request->validate([
            'date' => 'required|date'
        ]);
        /** @var Media $media */
        $media = config('media-library.media_model')::findOrFail($media);
        $media->setCustomProperty('date', $request->date('date')->format('Y-m-d H:i:s'))
            ->save();
        return ApiResponse::successResponse(MediaData::from($media));
    }

    /**
     * Used to update the description in custom_properties of media model
     *
     * @param int|string $media
     * @param Request $request
     * @return ApiResponse
     */
    public function updateDescription(int|string $media, Request $request): ApiResponse
    {
        $request->validate([
            'description' => 'nullable|string'
        ]);
        /** @var Media $media */
        $media = config('media-library.media_model')::findOrFail($media);
        $media->setCustomProperty('description', $request->string('description'))
            ->save();
        return ApiResponse::successResponse(MediaData::from($media));
    }

    /**
     * Used to manipulate (rotation for example) of media model
     *
     * @param int|string $media
     * @param Request $request
     * @return ApiResponse
     */
    public function manipulate(int|string $media, Request $request): ApiResponse
    {
        $request->validate([
            'rotation' => ['nullable', Rule::in([0,90,180,270,-90])],
        ]);
        /** @var Media $media */
        $media = config('media-library.media_model')::findOrFail($media);
        try {
            $orientation = Orientation::tryFrom($request->integer('rotation'));
            if ($orientation) {
                Image::load($media->getPath())->orientation($orientation)->save();
                $media->update([
                    'created_at' => Carbon::now(),
                ]);
            } else {
                throw new Exception('Orientation not supported');
            }
        } catch (\Exception $exception) {
            return ApiResponse::failedResponse($exception->getMessage());
        }
        return ApiResponse::successResponse(MediaData::from($media));
    }

    /**
     * Used to destroy the media
     *
     * @param int|string $media
     * @return ApiResponse
     */
    public function destroy(int|string $media): ApiResponse
    {
        /** @var Media $media */
        $media = config('media-library.media_model')::findOrFail($media);
        $media->delete();
        return ApiResponse::successResponse();
    }
}
