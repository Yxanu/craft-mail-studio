<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Yxanu\MailStudio\Http\Controllers\Concerns\AuthorizesMailStudio;
use Yxanu\MailStudio\Mailables\ManagedMailable;
use Yxanu\MailStudio\Models\BrandProfile;
use Yxanu\MailStudio\Models\EmailTemplate;
use Yxanu\MailStudio\Services\BrandProfileRepository;
use Yxanu\MailStudio\Services\TemplateRenderer;
use Yxanu\MailStudio\Services\TemplateRepository;

readonly class PreviewController
{
    use AuthorizesMailStudio;

    public function __construct(
        private TemplateRepository $templates,
        private TemplateRenderer $renderer,
        private BrandProfileRepository $brands,
    ) {}

    public function render(Request $request, EmailTemplate $template): JsonResponse
    {
        $this->authorizeMailStudio('mailStudio:view');
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'document' => ['required', 'array'],
            'brandProfileId' => ['nullable', 'integer'],
            'variables' => ['nullable', 'array'],
        ]);
        $snapshot = $this->snapshot($template, $data);
        $rendered = $this->renderer->render($snapshot, $data['variables'] ?? []);

        return response()->json([
            'subject' => $rendered->subject,
            'preheader' => $rendered->preheader,
            'html' => $rendered->html,
            'text' => $rendered->text,
        ]);
    }

    public function send(Request $request, EmailTemplate $template): JsonResponse
    {
        $this->authorizeMailStudio('mailStudio:test');
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'preheader' => ['nullable', 'string', 'max:255'],
            'document' => ['required', 'array'],
            'brandProfileId' => ['nullable', 'integer'],
            'variables' => ['nullable', 'array'],
        ]);
        $snapshot = $this->snapshot($template, $data);
        Mail::to($data['email'])->send(new ManagedMailable(
            sourceKey: 'mail-studio.preview',
            snapshot: $snapshot,
            variables: $data['variables'] ?? [],
        ));

        return response()->json(['message' => 'Test email sent.']);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function snapshot(EmailTemplate $template, array $data): array
    {
        $profile = isset($data['brandProfileId'])
            ? BrandProfile::query()->find($data['brandProfileId'])
            : $template->brandProfile;
        $profile ??= $this->brands->default();

        return [
            'revisionId' => null,
            'templateId' => $template->id,
            'subject' => $data['subject'],
            'preheader' => $data['preheader'] ?? '',
            'document' => $data['document'],
            'brand' => array_replace(BrandProfileRepository::defaultConfig(), $profile->config ?? []),
        ];
    }
}
