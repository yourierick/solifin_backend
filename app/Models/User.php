<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\HandleProfilePicture;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Notifications\VerifyEmailFrench;
use App\Models\Role;
use App\Models\Permission;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HandleProfilePicture; 

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'sexe',
        'account_id',
        'email',
        'password',
        'whatsapp',
        'phone',
        'picture',
        'pays',
        'province',
        'ville',
        'address',
        'apropos',
        'status',
        'pack_de_publication_id',
        'is_admin',
        'role_id',
        'email_verified_at',
        'acquisition_source', // Comment l'utilisateur a connu SOLIFIN
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var list<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
        'status' => 'string',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'role',
    ];

    /**
     * Get the user's role.
     *
     * @return string
     */
    public function getRoleAttribute()
    {
        if ($this->role_id) {
            return $this->roleRelation->slug;
        }
        
        // Compatibilité avec l'ancien système
        return $this->is_admin ? 'admin' : 'user';
    }
    
    /**
     * Relation avec le rôle de l'utilisateur.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function roleRelation()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
    
    /**
     * Vérifie si l'utilisateur a une permission spécifique.
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        // Si l'utilisateur n'a pas de rôle assigné mais est admin
        if (!$this->role_id && $this->is_admin) {
            return true; // Les anciens admins ont toutes les permissions
        }
        
        // Vérifier les permissions via le rôle
        return $this->role_id && $this->roleRelation->hasPermission($permission);
    }
    
    /**
     * Vérifie si l'utilisateur a un rôle spécifique.
     *
     * @param string $role
     * @return bool
     */
    public function hasRole($role)
    {
        // Compatibilité avec l'ancien système
        if (!$this->role_id) {
            if ($role === 'admin' && $this->is_admin) {
                return true;
            }
            if ($role === 'user' && !$this->is_admin) {
                return true;
            }
            return false;
        }
        
        return $this->roleRelation->slug === $role;
    }
    
    /**
     * Vérifie si l'utilisateur est un gestionnaire.
     *
     * @return bool
     */
    public function isGestionnaire()
    {
        return $this->hasRole('gestionnaire');
    }
    
    /**
     * Vérifie si l'utilisateur est un administrateur.
     *
     * @return bool
     */
    public function isAdmin()
    {
        return $this->is_admin || $this->hasRole('admin') || $this->hasRole('super-admin');
    }
    
    /**
     * Vérifie si l'utilisateur est un super administrateur.
     *
     * @return bool
     */
    public function isSuperAdmin()
    {
        return $this->hasRole('super-admin');
    }

    // Relation avec les packs achetés
    public function packs()
    {
        return $this->belongsToMany(Pack::class, 'user_packs')
                    ->withTimestamps()
                    ->withPivot([
                        'status',
                        'purchase_date',
                        'referral_prefix',
                        'referral_pack_name',
                        'referral_letter',
                        'referral_number',
                        'referral_code',
                        'link_referral',
                        'sponsor_id',
                        'payment_status'
                    ]);
    }

    // Relation avec le wallet
    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    /**
     * Get the user's referrals (users who used this user's referral code)
     */
    public function referrals()
    {
        return $this->hasManyThrough(
            User::class,
            UserPack::class,
            'sponsor_id', // Foreign key on user_packs table
            'id', // Local key on users table
            'id', // Local key on users table
            'user_id' // Foreign key on user_packs table
        );
    }

    /**
     * Get the user's sponsor (user who referred this user)
     */
    public function sponsor()
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    /**
     * Get the user's pack de publication
     */
    public function pack_de_publication()
    {
        return $this->belongsTo(Pack::class, 'pack_de_publication_id');
    }

    /**
     * Get the referral counts grouped by pack
     */
    public function getReferralCounts()
    {
        return UserPack::query()
            ->join('user_packs as sponsor_packs', function ($join) {
                $join->on('user_packs.sponsor_id', '=', 'sponsor_packs.user_id');
            })
            ->where('sponsor_packs.user_id', $this->id)
            ->groupBy('user_packs.pack_id')
            ->selectRaw('user_packs.pack_id, COUNT(*) as count')
            ->get();
    }

    /**
     * Get the referrals list with optional pack filter
     */
    public function getReferrals($packId = null)
    {
        $query = $this->referrals();
        
        if ($packId) {
            $query->whereHas('packs', function ($q) use ($packId) {
                $q->where('pack_id', $packId);
            });
        }
        
        return $query->with(['packs' => function ($q) {
            $q->where('sponsor_id', $this->id);
        }])->get();
    }
    
    /**
     * Récupérer la page de l'utilisateur
     */
    public function page()
    {
        return $this->hasOne(Page::class);
    }

    /**
     * Compte le nombre de filleuls qui ont utilisé les codes parrain des packs de l'utilisateur
     * @param int|null $packId Optionnel, pour filtrer par pack spécifique
     * @return array Tableau avec le nombre de filleuls par pack et le total
     */
    public function getReferralCountsOld(?int $packId = null)
    {
        $query = UserPack::query()
            ->join('user_packs as sponsor_packs', function ($join) {
                $join->on('user_packs.sponsor_id', '=', 'sponsor_packs.user_id');
            })
            ->where('sponsor_packs.user_id', $this->id);

        if ($packId) {
            $query->where('sponsor_packs.pack_id', $packId);
        }

        // Grouper par pack pour avoir le détail
        $referralsByPack = $query->select('sponsor_packs.pack_id')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('sponsor_packs.pack_id')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->pack_id => $item->count];
            });

        // Calculer le total
        $total = $referralsByPack->sum();

        return [
            'by_pack' => $referralsByPack,
            'total' => $total
        ];
    }

    /**
     * Récupère les filleuls qui ont utilisé les codes parrain des packs de l'utilisateur
     * @param int|null $packId Optionnel, pour filtrer par pack spécifique
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getReferralsOld(?int $packId = null)
    {
        $query = User::query()
            ->join('user_packs', 'users.id', '=', 'user_packs.user_id')
            ->join('user_packs as sponsor_packs', function ($join) {
                $join->on('user_packs.sponsor_id', '=', 'sponsor_packs.user_id');
            })
            ->where('sponsor_packs.user_id', $this->id);

        if ($packId) {
            $query->where('sponsor_packs.pack_id', $packId);
        }

        return $query->select('users.*')
            ->with(['packs'])
            ->distinct()
            ->get();
    }

    public function getFilleulsStats() {
        return DB::select("
            WITH RECURSIVE filleuls AS (
                SELECT up1.user_id, up1.sponsor_id, 1 AS generation, p.name AS pack_name
                FROM user_packs up1
                JOIN packs p ON up1.pack_id = p.id
                WHERE up1.sponsor_id = ?

                UNION ALL

                SELECT up2.user_id, up2.sponsor_id, f.generation + 1, p.name AS pack_name
                FROM user_packs up2
                INNER JOIN filleuls f ON up2.sponsor_id = f.user_id
                JOIN packs p ON up2.pack_id = p.id
                WHERE f.generation < 4
            )
            SELECT generation, pack_name, COUNT(user_id) as total_filleuls
            FROM filleuls
            GROUP BY generation, pack_name
            ORDER BY generation, pack_name
        ", [$this->id]);
    }

    public function delete()
    {
        // Empêcher la suppression du dernier administrateur
        if ($this->is_admin && User::where('is_admin', true)->count() === 1) {
            throw new \Exception('Impossible de supprimer le dernier administrateur');
        }

        // Récupérer tous les filleuls directs (première génération)
        $directReferrals = $this->referrals;
        
        // Si c'est un admin qui est supprimé, on cherche un autre admin pour les filleuls
        if ($this->is_admin) {
            $newSponsor = User::where('is_admin', true)
                ->where('id', '!=', $this->id)
                ->first();
            
            if ($newSponsor) {
                foreach ($directReferrals as $referral) {
                    // Mettre à jour le sponsor_id avec le nouvel admin
                    $referral->sponsor_id = $newSponsor->id;
                    $referral->save();

                    // Mettre à jour les referralCodes des packs si le nouvel admin a les mêmes packs
                    $referralPacks = $referral->packs;
                    
                    foreach ($referralPacks as $referralPack) {
                        $sponsorPack = $newSponsor->packs()
                            ->where('pack_id', $referralPack->pack_id)
                            ->first();
                        
                        if ($sponsorPack) {
                            // Mettre à jour le referralCode pour correspondre à celui du nouvel admin
                            $referralPack->pivot->referral_code = $sponsorPack->pivot->referral_code;
                            $referralPack->pivot->save();
                        }
                    }
                }
            }
        } else {
            // Pour un utilisateur normal, on utilise la logique existante
            if ($this->sponsor_id) {
                foreach ($directReferrals as $referral) {
                    $referral->sponsor_id = $this->sponsor_id;
                    $referral->save();

                    $referralPacks = $referral->packs;
                    $newSponsor = User::find($this->sponsor_id);
                    
                    if ($newSponsor) {
                        foreach ($referralPacks as $referralPack) {
                            $sponsorPack = $newSponsor->packs()
                                ->where('pack_id', $referralPack->pack_id)
                                ->first();
                            
                            if ($sponsorPack) {
                                $referralPack->pivot->referral_code = $sponsorPack->pivot->referral_code;
                                $referralPack->pivot->save();
                            }
                        }
                    }
                }
            }
        }

        // Supprimer les packs de l'utilisateur
        $this->packs()->detach();

        // Supprimer l'utilisateur
        return parent::delete();
    }

    protected static function boot()
    {
        parent::boot();
        
        static::deleting(function($user) {
            $user->deleteProfilePicture();
        });
    }

    public function getProfilePictureUrlAttribute()
    {
        if (!$this->picture) {
            return null;
        }
        return Storage::disk('public')->url($this->picture);
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailFrench);
    }
    
    /**
     * Relation avec les témoignages soumis par l'utilisateur.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function testimonials()
    {
        return $this->hasMany(Testimonial::class);
    }
    
    /**
     * Relation avec les invitations à témoigner reçues par l'utilisateur.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function testimonialPrompts()
    {
        return $this->hasMany(TestimonialPrompt::class);
    }
    
    /**
     * Vérifie si l'utilisateur a reçu une invitation à témoigner récemment.
     *
     * @param int $days Nombre de jours à considérer
     * @return bool
     */
    public function hasRecentTestimonialPrompt(int $days = 30): bool
    {
        return $this->testimonialPrompts()
                    ->where('created_at', '>', now()->subDays($days))
                    ->exists();
    }
    
    /**
     * Vérifie si l'utilisateur est éligible pour recevoir une invitation à témoigner.
     *
     * @return bool
     */
    public function isEligibleForTestimonialPrompt(): bool
    {
        // Ne pas inviter si l'utilisateur a déjà reçu une invitation récemment
        if ($this->hasRecentTestimonialPrompt()) {
            return false;
        }
        
        // Vérifier si l'utilisateur est inscrit depuis au moins 30 jours
        if ($this->created_at->diffInDays(now()) < 30) {
            return false;
        }
        
        // Vérifier si l'utilisateur a déjà soumis un témoignage récemment
        $hasRecentTestimonial = $this->testimonials()
            ->where('created_at', '>', now()->subDays(90))
            ->exists();
            
        if ($hasRecentTestimonial) {
            return false;
        }
        
        return true;
    }
}
