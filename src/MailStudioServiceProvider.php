<?php

declare(strict_types=1);

namespace Yxanu\MailStudio;

use Illuminate\Support\ServiceProvider;
use Yxanu\MailStudio\Services\BrandProfileRepository;
use Yxanu\MailStudio\Services\ImportExportService;
use Yxanu\MailStudio\Services\ManagedMailableFactory;
use Yxanu\MailStudio\Services\PresetLibrary;
use Yxanu\MailStudio\Services\SourceCatalog;
use Yxanu\MailStudio\Services\SourceRegistry;
use Yxanu\MailStudio\Services\TemplateRenderer;
use Yxanu\MailStudio\Services\TemplateRepository;

class MailStudioServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SourceRegistry::class);
        $this->app->singleton(SourceCatalog::class);
        $this->app->singleton(PresetLibrary::class);
        $this->app->singleton(BrandProfileRepository::class);
        $this->app->singleton(ImportExportService::class);
        $this->app->singleton(TemplateRepository::class);
        $this->app->singleton(TemplateRenderer::class);
        $this->app->singleton(ManagedMailableFactory::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__).'/resources/views/mail', 'mail-studio');
    }
}
