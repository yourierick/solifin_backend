<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users')->ignore($this->user()->id),
            ],
            'phone' => ['sometimes', 'string', 'max:20'],
            'whatsapp' => ['sometimes', 'string', 'max:20'],
            'address' => ['sometimes', 'string', 'max:255'],
            'sexe' => ['sometimes', 'string'],
            'pays' => ['sometimes', 'string'],
            'province' => ['sometimes', 'string'],
            'ville' => ['sometimes', 'string'],
            'picture' => ['sometimes', 'nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'password' => ['sometimes', 'min:8', 'confirmed'],
            'pack_de_publication_id' => ['sometimes', 'exists:packs,id'],
        ];
    }
} 