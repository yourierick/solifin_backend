<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use App\Models\User;
use App\Models\AdvertisementValidation;

class Advertisement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'image_path',
        'url',
        'start_date',
        'end_date',
        'status',
        'user_id',
        'validation_status',
        'validated_by',
        'validation_note',
        'validated_at',
        'is_published'
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'status' => 'boolean',
        'is_published' => 'boolean',
        'validated_at' => 'datetime'
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function validator()
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function validations()
    {
        return $this->hasMany(AdvertisementValidation::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true)
            ->where('is_published', true)
            ->where('start_date', '<=', now())
            ->where(function ($query) {
                $query->where('end_date', '>=', now())
                    ->orWhereNull('end_date');
            });
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('status', true)
            ->where('is_published', true)
            ->where('start_date', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('end_date', '<', now());
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('validation_status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('validation_status', 'approved');
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('validation_status', 'rejected');
    }

    // Méthodes utilitaires
    public function isPending(): bool
    {
        return $this->validation_status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->validation_status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->validation_status === 'rejected';
    }

    public function isPublished(): bool
    {
        return $this->is_published;
    }

    public function submit()
    {
        $this->validation_status = 'pending';
        $this->save();

        $this->logValidation('submit');
    }

    public function approve(User $admin, ?string $note = null)
    {
        $this->validation_status = 'approved';
        $this->validated_by = $admin->id;
        $this->validation_note = $note;
        $this->validated_at = now();
        $this->save();

        $this->logValidation('approve', $note);
    }

    public function reject(User $admin, string $note)
    {
        $this->validation_status = 'rejected';
        $this->validated_by = $admin->id;
        $this->validation_note = $note;
        $this->validated_at = now();
        $this->save();

        $this->logValidation('reject', $note);
    }

    public function publish()
    {
        if (!$this->isApproved()) {
            throw new \Exception('Cannot publish unapproved advertisement');
        }

        $this->is_published = true;
        $this->save();

        $this->logValidation('publish');
    }

    public function unpublish()
    {
        $this->is_published = false;
        $this->save();

        $this->logValidation('unpublish');
    }

    protected function logValidation(string $action, ?string $note = null)
    {
        return $this->validations()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'note' => $note,
            'metadata' => [
                'title' => $this->title,
                'validation_status' => $this->validation_status,
                'is_published' => $this->is_published
            ]
        ]);
    }
}