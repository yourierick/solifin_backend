<?php

namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

trait HandleProfilePicture
{
    public function uploadProfilePicture(UploadedFile $file)
    {
        $path = $file->store('profile-pictures', 'public');
        
        // Supprimer l'ancienne photo si elle existe
        if ($this->picture && Storage::disk('public')->exists($this->picture)) {
            Storage::disk('public')->delete($this->picture);
        }
        
        return $path;
    }

    public function deleteProfilePicture()
    {
        if ($this->picture && Storage::disk('public')->exists($this->picture)) {
            Storage::disk('public')->delete($this->picture);
        }
    }
} 