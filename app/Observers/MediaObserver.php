<?php

namespace App\Observers;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaObserver
{
    public function creating(Media $media)
    {
        // Set the name field to the original file name without extension
        $pathInfo = pathinfo($media->file_name);
        $media->name = $pathInfo['filename'];
        
        // Ensure the file_name doesn't get modified with -meta
        $media->file_name = $pathInfo['filename'] . '.' . $pathInfo['extension'];
    }
} 