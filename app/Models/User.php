<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'role_id',
        'default_store_id',
        'legacy_login_id',
        'legacy_user_id',
        'legacy_department_id',
        'legacy_owner_user_id',
        'legacy_kind',
        'name',
        'username',
        'email',
        'email_verified_at',
        'password',
        'is_active',
        'can_open',
        'can_add',
        'can_edit',
        'can_delete',
        'is_legacy_user',
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'can_open' => 'boolean',
            'can_add' => 'boolean',
            'can_edit' => 'boolean',
            'can_delete' => 'boolean',
            'is_legacy_user' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function defaultStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'default_store_id');
    }

    public function legacyDepartmentName(): ?string
    {
        $departmentId = $this->legacy_department_id;

        if (! $departmentId) {
            return null;
        }

        return [
            1 => 'Admin',
            2 => 'Accounts',
            3 => 'POS',
            4 => 'Purchases',
            5 => 'Center 5',
            6 => 'Center 6',
            7 => 'Center 7',
            8 => 'Center 8',
            9 => 'Center 9',
            10 => 'Center 10',
            11 => 'Center 11',
        ][$departmentId] ?? 'Department '.$departmentId;
    }

    public function importSourceLabel(): string
    {
        return $this->is_legacy_user ? 'Imported from Access' : 'Created in Laravel';
    }

    public function displayRoleName(): string
    {
        return Str::headline($this->role?->name ?? 'none');
    }
}
