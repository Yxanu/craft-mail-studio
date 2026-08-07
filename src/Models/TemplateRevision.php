<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateRevision extends Model
{
    public $timestamps = false;

    protected $table = 'mailstudio_revisions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'document' => 'array',
            'brand_snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(TemplateVariant::class, 'variant_id');
    }
}
