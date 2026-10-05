<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_WAREHOUSE = 'warehouse_staff';

    public const ROLE_POS = 'pos_staff';

    protected $fillable = ['name', 'username', 'password', 'role', 'active'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'active' => 'boolean'];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function canAccessWarehouse(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_WAREHOUSE], true);
    }

    public function canAccessPos(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_POS], true);
    }
}
