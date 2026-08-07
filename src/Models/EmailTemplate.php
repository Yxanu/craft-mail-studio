<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    protected $table = 'mailstudio_templates';

    protected $guarded = [];

    public function brandProfile(): BelongsTo
    {
        return $this->belongsTo(BrandProfile::class, 'brand_profile_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(TemplateVariant::class, 'template_id');
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(SourceMapping::class, 'template_id');
    }
}
