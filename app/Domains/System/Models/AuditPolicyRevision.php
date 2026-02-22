<?php

declare(strict_types=1);

namespace App\Domains\System\Models;

use App\Domains\Access\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditPolicyRevision extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'scope',
        'changed_by_user_id',
        'change_reason',
        'changed_count',
        'changed_actions',
        'changes',
        'policy_snapshot',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changed_by_user_id' => 'integer',
            'changed_count' => 'integer',
            'changed_actions' => 'array',
            'changes' => 'array',
            'policy_snapshot' => 'array',
        ];
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
