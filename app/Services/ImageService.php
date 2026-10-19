<?php

namespace App\Services;

use Illuminate\Http\Request;

class ImageService
{
    /**
     * Process images from request (UploadedFile objects or existing base64 strings).
     *
     * @param Request $request
     * @param int $max
     * @return array
     */
    public static function processImages(Request $request, int $max = 4): array
    {
        $imagePaths = [];

        if ($request->hasFile('images')) {
            $images = $request->file('images');
            if (is_array($images)) {
                $images = array_slice($images, 0, $max);
                foreach ($images as $image) {
                    if ($image && $image->isValid()) {
                        $base64 = 'data:' . $image->getMimeType() . ';base64,' . base64_encode(file_get_contents($image->getRealPath()));
                        $imagePaths[] = $base64;
                    }
                }
            }
        } elseif ($request->has('images') && is_array($request->images)) {
            $imagePaths = array_slice($request->images, 0, $max);
        }

        return $imagePaths;
    }
}
