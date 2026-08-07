<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TemplateVariant extends Model
{
    protected $table = 'mailstudio_variants';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'draft_document' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    public function activeRevision(): BelongsTo
    {
        return $this->belongsTo(TemplateRevision::class, 'active_revision_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(TemplateRevision::class, 'variant_id')->orderByDesc('number');
    }
}
