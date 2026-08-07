<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Yxanu\MailStudio\Http\Controllers\Concerns\AuthorizesMailStudio;
use Yxanu\MailStudio\MailStudio;
use Yxanu\MailStudio\Services\SourceCatalog;
use Yxanu\MailStudio\Services\TemplateRepository;

readonly class MappingController
{
    use AuthorizesMailStudio;

    public function __construct(
        private TemplateRepository $templates,
        private SourceCatalog $sources,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $this->authorizeMailStudio('mailStudio:manage');
        $data = $request->validate([
            'sourceKey' => ['required', 'string', 'max:255'],
            'templateId' => ['nullable', 'integer', 'exists:mailstudio_templates,id'],
            'siteId' => ['nullable', 'integer'],
            'locale' => ['nullable', 'string', 'max:32'],
        ]);

        $source = collect($this->sources->all(MailStudio::getInstance()->is(MailStudio::EDITION_PRO)))
            ->first(fn ($source): bool => $source->key === $data['sourceKey']);
        abort_unless($source, 422, 'Unknown source key.');
        if ($source->adapter === 'freeform') {
            abort_unless(MailStudio::getInstance()->is(MailStudio::EDITION_PRO), 403);
        }
        if (! MailStudio::getInstance()->is(MailStudio::EDITION_PRO)) {
            $data['siteId'] = null;
            $data['locale'] = null;
        }

        if (empty($data['templateId'])) {
            $this->templates->unmapSource($source->adapter, $source->key, $data['siteId'] ?? null, $data['locale'] ?? null);
        } else {
            $template = $this->templates->find((int) $data['templateId']);
            $this->templates->mapSource($source->adapter, $source->key, $template, $data['siteId'] ?? null, $data['locale'] ?? null);
        }

        return response()->json([
            'templates' => $this->templates->all()->map($this->templates->serialize(...))->values(),
        ]);
    }
}
