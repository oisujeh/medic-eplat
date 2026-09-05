<?php

namespace App\Models;

use App\Enums\Permission;
use App\Models\Concerns\Auditable;
use App\Models\Contracts\AuditableRecord;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One permission granted to a role. Grants are audited like any other
 * administrative change.
 *
 * @property int $id
 * @property int $role_id
 * @property Permission $permission
 */
#[Fillable(['role_id', 'permission'])]
class RolePermission extends Model implements AuditableRecord
{
    use Auditable;

    protected $table = 'permission_role';

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permission' => Permission::class,
        ];
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function auditLabel(): string
    {
        return 'Permission '.$this->permission->value.' for role #'.$this->role_id;
    }
}
