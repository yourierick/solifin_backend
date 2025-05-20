<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaqCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'order'
    ];

    /**
     * Get the FAQs for this category.
     */
    public function faqs()
    {
        return $this->hasMany(Faq::class, 'category_id');
    }
}
