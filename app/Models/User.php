<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

class User extends Authenticatable
{
    private const ROLE_PERMISSIONS = [
        'KASIR' => ['inbound.create', 'opname.create', 'closing.create'],
        'SPV' => ['inbound.view', 'opname.view', 'closing.verify', 'correction.request'],
        'MANAGER_OWNER' => ['branch.view', 'closing.verify', 'correction.approve', 'correction.apply', 'bank.confirm', 'payout.approve', 'payout.mark_paid', 'audit.view'],
        'OWNER' => ['branch.view', 'closing.verify', 'correction.approve', 'correction.apply', 'bank.confirm', 'payout.approve', 'payout.mark_paid', 'audit.view'],
        'ADMIN_PUSAT' => ['branch.view', 'master.manage', 'payout.export', 'payout.mark_paid', 'audit.view'],
    ];
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'home_branch_id',
        'role',
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
            'password' => 'hashed',
        ];
    }

    public function homeBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'home_branch_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branches')->withTimestamps();
    }

    public function hasPermission(string $permission): bool
    {
        $rolePermissions = self::ROLE_PERMISSIONS[$this->role] ?? [];
        if (in_array($permission, $rolePermissions, true)) {
            return true;
        }
        if (array_key_exists($this->role, self::ROLE_PERMISSIONS)) {
            return false;
        }

        return DB::table('roles')
            ->join('role_user', 'role_user.role_id', '=', 'roles.id')
            ->join('permission_role', 'permission_role.role_id', '=', 'roles.id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('role_user.user_id', $this->id)
            ->where('permissions.name', $permission)
            ->exists() || in_array($permission, self::ROLE_PERMISSIONS[$this->role] ?? [], true);
    }

    public function canAccessBranch(int $branchId): bool
    {
        if (in_array($this->role, ['MANAGER_OWNER', 'OWNER', 'ADMIN_PUSAT'], true)) {
            return $this->hasPermission('branch.view');
        }

        return $this->role === 'KASIR'
            ? (int) $this->home_branch_id === $branchId
            : $this->branches()->whereKey($branchId)->exists();
    }
}
