<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yxanu\MailStudio\Http\Controllers\Concerns\AuthorizesMailStudio;
use Yxanu\MailStudio\MailStudio;
use Yxanu\MailStudio\Models\BrandProfile;
use Yxanu\MailStudio\Models\EmailTemplate;
use Yxanu\MailStudio\Models\TemplateRevision;
use Yxanu\MailStudio\Services\PresetLibrary;
use Yxanu\MailStudio\Services\TemplateRepository;

use function CraftCms\Cms\currentUser;

readonly class TemplateController
{
    use AuthorizesMailStudio;

    public function __construct(
        private TemplateRepository $templates,
        private PresetLibrary $presets,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $this->authorizeMailStudio('mailStudio:edit');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'presetKey' => ['required', 'string', Rule::in(array_keys($this->presets->all(MailStudio::getInstance()->is(MailStudio::EDITION_PRO))))],
        ]);

        $template = $this->templates->create($data['name'], $data['presetKey']);

        return response()->json(['template' => $this->templates->serialize($template)], 201);
    }

    public function update(Request $request, EmailTemplate $template): JsonResponse
    {
        $this->authorizeMailStudio('mailStudio:edit');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'brandProfileId' => ['nullable', 'integer', 'exists:mailstudio_brand_profiles,id'],
            'siteId' => ['nullable', 'integer'],
            'locale' => ['nullable', 'string', 'max:32'],
            'subject' => ['required', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'document' => ['required', 'array'],
            'document.version' => ['required', 'integer', 'in:1'],
            'document.blocks' => ['required', 'array', 'max:100'],
            'document.blocks.*.id' => ['required', 'string', 'max:80'],
            'document.blocks.*.type' => ['required', Rule::in(['heading', 'richText', 'button', 'image', 'callout', 'divider', 'keyValue'])],
            'document.blocks.*.data' => ['required', 'array'],
        ]);

        if (! MailStudio::getInstance()->is(MailStudio::EDITION_PRO)) {
            $data['brandProfileId'] = BrandProfile::query()->where('is_default', true)->value('id');
            $data['siteId'] = null;
            $data['locale'] = null;
        }

        $updated = $this->templates->saveDraft($template, $data);

        return response()->json(['template' => $this->templates->serialize($updated)]);
    }

    public function publish(Request $request, EmailTemplate $template): JsonResponse
    {
        $this->authorizeMailStudio('mailStudio:publish');
        $data = $request->validate([
            'siteId' => ['nullable', 'integer'],
            'locale' => ['nullable', 'string', 'max:32'],
        ]);
        if (! MailStudio::getInstance()->is(MailStudio::EDITION_PRO)) {
            $data = ['siteId' => null, 'locale' => null];
        }

        $revision = $this->templates->publish(
            $template,
            $data['siteId'] ?? null,
            $data['locale'] ?? null,
            currentUser()?->getCraftUserId(),
        );

        return response()->json([
            'revisionId' => $revision->id,
            'template' => $this->templates->serialize($this->templates->find($template->id)),
        ]);
    }

    public function rollback(EmailTemplate $template, TemplateRevision $revision): JsonResponse
    {
        $this->authorizeMailStudio('mailStudio:publish');
        abort_unless(MailStudio::getInstance()->is(MailStudio::EDITION_PRO), 403);

        $newRevision = $this->templates->rollback($template, $revision, currentUser()?->getCraftUserId());

        return response()->json([
            'revisionId' => $newRevision->id,
            'template' => $this->templates->serialize($this->templates->find($template->id)),
        ]);
    }

    public function destroy(EmailTemplate $template): JsonResponse
    {
        $this->authorizeMailStudio('mailStudio:edit');
        $this->templates->delete($template);

        return response()->json(status: 204);
    }
}
