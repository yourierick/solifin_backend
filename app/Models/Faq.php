<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'question',
        'answer',
        'is_featured',
        'order',
        'helpful_votes',
        'unhelpful_votes'
    ];

    /**
     * Get the category that owns the FAQ.
     */
    public function category()
    {
        return $this->belongsTo(FaqCategory::class);
    }

    /**
     * Get the related FAQs for this FAQ.
     */
    public function relatedFaqs()
    {
        return $this->belongsToMany(Faq::class, 'faq_related', 'faq_id', 'related_faq_id')
            ->withTimestamps();
    }
}
