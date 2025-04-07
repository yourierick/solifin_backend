<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Http\Requests\UpdateProfileRequest;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $user->profile_picture_url = $user->getProfilePictureUrlAttribute();
        $packs = $user->packs;
        
        return response()->json([
            'success' => true,
            'data' => $user,
            'packs' => $packs
        ]);
    }

    public function update(UpdateProfileRequest $request)
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            // Gérer l'upload de la photo de profil
            if ($request->hasFile('picture')) {
                $validated['picture'] = $user->uploadProfilePicture($request->file('picture'));
            }

            // Gérer le mot de passe
            if (isset($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            } else {
                unset($validated['password']);
            }

            $user->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Profil mis à jour avec succès',
                'data' => $user
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur lors de la mise à jour du profil : ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du profil',
                'error' => $e->getMessage()
            ], 500);
        }
    }
} 