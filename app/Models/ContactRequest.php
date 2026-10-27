<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A lead left through the landing's contact form. No account behind it — just
 * a name and a number for a manager to call back. Status is admin-only, so it
 * stays out of #[Fillable].
 */
#[Fillable(['name', 'phone', 'note', 'locale'])]
class ContactRequest extends Model
{
    public const STATUS_NEW = 'new';
    public const STATUS_DONE = 'done';

    // Mirror the DB default so a freshly created model reports its status.
    protected $attributes = [
        'status' => self::STATUS_NEW,
    ];

    public function isNew(): bool
    {
        return $this->status === self::STATUS_NEW;
    }
}
