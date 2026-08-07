<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yxanu\MailStudio\Http\Controllers\Concerns\AuthorizesMailStudio;
use Yxanu\MailStudio\MailStudio;
use Yxanu\MailStudio\Services\ImportExportService;

readonly class ImportExportController
{
    use AuthorizesMailStudio;

    public function __construct(private ImportExportService $transfer) {}

    public function export(): StreamedResponse
    {
        $this->authorizeMailStudio('mailStudio:manage');
        abort_unless(MailStudio::getInstance()->is(MailStudio::EDITION_PRO), 403);
        $payload = $this->transfer->export();

        return response()->streamDownload(
            fn () => print json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'mail-studio-'.now()->format('Y-m-d-His').'.json',
            ['Content-Type' => 'application/json'],
        );
    }

    public function import(Request $request): JsonResponse
    {
        $this->authorizeMailStudio('mailStudio:manage');
        abort_unless(MailStudio::getInstance()->is(MailStudio::EDITION_PRO), 403);
        $data = $request->validate(['payload' => ['required', 'array']]);

        return response()->json(['imported' => $this->transfer->import($data['payload'])]);
    }
}
