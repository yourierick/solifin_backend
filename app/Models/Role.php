<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nom',
        'slug',
        'description',
    ];

    /**
     * Les permissions associées à ce rôle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission')
            ->withTimestamps();
    }

    /**
     * Les utilisateurs qui ont ce rôle.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Vérifie si le rôle a une permission spécifique.
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        return $this->permissions->contains('slug', $permission);
    }

    /**
     * Attribue des permissions à ce rôle.
     *
     * @param array $permissions
     * @return $this
     */
    public function givePermissionsTo(array $permissions)
    {
        $permissions = Permission::whereIn('slug', $permissions)->get();
        $this->permissions()->syncWithoutDetaching($permissions);
        return $this;
    }

    /**
     * Retire des permissions de ce rôle.
     *
     * @param array $permissions
     * @return $this
     */
    public function revokePermissionsTo(array $permissions)
    {
        $permissions = Permission::whereIn('slug', $permissions)->get();
        $this->permissions()->detach($permissions);
        return $this;
    }

    /**
     * Synchronise les permissions de ce rôle.
     *
     * @param array $permissions
     * @return $this
     */
    public function syncPermissions(array $permissions)
    {
        $permissions = Permission::whereIn('slug', $permissions)->get();
        $this->permissions()->sync($permissions);
        return $this;
    }
}
