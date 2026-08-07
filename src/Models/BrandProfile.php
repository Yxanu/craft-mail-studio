<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BrandProfile extends Model
{
    protected $table = 'mailstudio_brand_profiles';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_default' => 'boolean',
        ];
    }

    public function templates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class, 'brand_profile_id');
    }
}
