<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Services;

use Illuminate\Support\Str;
use Yxanu\MailStudio\Models\BrandProfile;

class BrandProfileRepository
{
    /** @return array<string, mixed> */
    public static function defaultConfig(): array
    {
        return [
            'logoUrl' => '',
            'senderName' => 'Craft CMS',
            'backgroundColor' => '#f3f4f6',
            'surfaceColor' => '#ffffff',
            'textColor' => '#182230',
            'mutedColor' => '#667085',
            'primaryColor' => '#155eef',
            'borderColor' => '#d0d5dd',
            'containerWidth' => 600,
            'footerText' => 'This is an automated transactional email.',
            'supportEmail' => '',
            'supportPhone' => '',
        ];
    }

    public function ensureDefault(): BrandProfile
    {
        return BrandProfile::query()->firstOrCreate(
            ['handle' => 'default'],
            [
                'uid' => (string) Str::uuid(),
                'name' => 'Default',
                'config' => self::defaultConfig(),
                'is_default' => true,
            ],
        );
    }

    public function default(): BrandProfile
    {
        return BrandProfile::query()->where('is_default', true)->first()
            ?? $this->ensureDefault();
    }

    /** @param array<string, mixed> $config */
    public function create(string $name, array $config = []): BrandProfile
    {
        $baseHandle = Str::slug($name) ?: 'brand';
        $handle = $baseHandle;
        $suffix = 2;
        while (BrandProfile::query()->where('handle', $handle)->exists()) {
            $handle = $baseHandle.'-'.$suffix++;
        }

        return BrandProfile::query()->create([
            'uid' => (string) Str::uuid(),
            'name' => trim($name),
            'handle' => $handle,
            'config' => array_replace(self::defaultConfig(), $this->sanitize($config)),
            'is_default' => false,
        ]);
    }

    public function delete(BrandProfile $profile): void
    {
        if ($profile->is_default) {
            throw new \LogicException('The default Mail Studio brand profile cannot be deleted.');
        }

        $default = $this->default();
        $profile->templates()->update(['brand_profile_id' => $default->id]);
        $profile->delete();
    }

    /** @param array<string, mixed> $config */
    public function update(BrandProfile $profile, array $config, ?string $name = null): BrandProfile
    {
        $profile->name = $name ?: $profile->name;
        $profile->config = array_replace(self::defaultConfig(), $this->sanitize($config));
        $profile->save();

        return $profile->refresh();
    }

    /** @return array<string, mixed> */
    public function serialize(BrandProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'uid' => $profile->uid,
            'name' => $profile->name,
            'handle' => $profile->handle,
            'isDefault' => $profile->is_default,
            'config' => array_replace(self::defaultConfig(), $profile->config ?? []),
        ];
    }

    /** @param array<string, mixed> $config */
    private function sanitize(array $config): array
    {
        $result = [];
        $colors = ['backgroundColor', 'surfaceColor', 'textColor', 'mutedColor', 'primaryColor', 'borderColor'];

        foreach ($colors as $key) {
            $value = strtolower((string) ($config[$key] ?? ''));
            if (preg_match('/^#[0-9a-f]{6}$/', $value)) {
                $result[$key] = $value;
            }
        }

        foreach (['logoUrl', 'senderName', 'footerText', 'supportEmail', 'supportPhone'] as $key) {
            $result[$key] = trim(strip_tags((string) ($config[$key] ?? '')));
        }

        $result['containerWidth'] = min(720, max(480, (int) ($config['containerWidth'] ?? 600)));

        return $result;
    }
}
