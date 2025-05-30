<?php

namespace App\Observers;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

class MediaObserver
{
    public function creating(Media $media)
    {
        // Don't modify file_name as it affects actual file storage and URLs
        // The 'name' field is automatically set to the original filename without extension
        // The 'file_name' field should be left as-is for proper file access
        
        // Spatie Media Library handles file storage automatically
        // We'll use the 'name' field for user-friendly display in the UI
    }
} 