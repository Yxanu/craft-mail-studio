<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceMapping extends Model
{
    protected $table = 'mailstudio_source_mappings';

    protected $guarded = [];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }
}
