<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Http\Controllers;

use CraftCms\Cms\Site\Sites;
use Illuminate\Http\JsonResponse;
use Yxanu\MailStudio\Http\Controllers\Concerns\AuthorizesMailStudio;
use Yxanu\MailStudio\MailStudio;
use Yxanu\MailStudio\Models\BrandProfile;
use Yxanu\MailStudio\Services\BrandProfileRepository;
use Yxanu\MailStudio\Services\PresetLibrary;
use Yxanu\MailStudio\Services\SourceCatalog;
use Yxanu\MailStudio\Services\TemplateRepository;
use Yxanu\MailStudio\Settings;

use function CraftCms\Cms\currentUser;

readonly class BootstrapController
{
    use AuthorizesMailStudio;

    public function __construct(
        private TemplateRepository $templates,
        private SourceCatalog $sources,
        private PresetLibrary $presets,
        private BrandProfileRepository $brands,
        private Sites $sites,
    ) {}

    public function __invoke(): JsonResponse
    {
        $this->authorizeMailStudio('mailStudio:view');
        $plugin = MailStudio::getInstance();
        $user = currentUser();
        /** @var Settings $settings */
        $settings = $plugin->getSettings();

        return response()->json([
            'edition' => $plugin->edition,
            'isPro' => $plugin->is(MailStudio::EDITION_PRO),
            'permissions' => [
                'edit' => $user?->isAdmin() || $user?->can('mailStudio:edit'),
                'publish' => $user?->isAdmin() || $user?->can('mailStudio:publish'),
                'test' => $user?->isAdmin() || $user?->can('mailStudio:test'),
                'manage' => $user?->isAdmin() || $user?->can('mailStudio:manage'),
            ],
            'templates' => $this->templates->all()->map($this->templates->serialize(...))->values(),
            'sources' => collect($this->sources->all($plugin->is(MailStudio::EDITION_PRO)))
                ->map(fn ($source): array => $source->toArray())
                ->values(),
            'presets' => collect($this->presets->all($plugin->is(MailStudio::EDITION_PRO)))->map(fn (array $preset, string $key): array => [
                'key' => $key,
                ...$preset,
            ])->values(),
            'brands' => BrandProfile::query()->orderBy('name')->get()->map($this->brands->serialize(...))->values(),
            'sites' => $this->sites->getAllSites(true)->map(fn ($site): array => [
                'id' => $site->id,
                'name' => $site->name,
                'handle' => $site->handle,
                'language' => $site->language,
                'primary' => $site->primary,
            ])->values(),
            'defaultLocale' => $settings->defaultLocale,
        ]);
    }
}
