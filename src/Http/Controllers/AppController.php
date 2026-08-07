<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Http\Controllers;

use CraftCms\Cms\Http\Responses\CpScreenResponse;
use CraftCms\Cms\Support\Url;
use Illuminate\Http\Request;
use Yxanu\MailStudio\Http\Controllers\Concerns\AuthorizesMailStudio;

readonly class AppController
{
    use AuthorizesMailStudio;

    public function __invoke(Request $request): CpScreenResponse
    {
        $this->authorizeMailStudio('mailStudio:view');

        $view = in_array($request->query('view'), ['templates', 'sources', 'brand'], true)
            ? $request->query('view')
            : 'templates';

        return (new CpScreenResponse)
            ->title('Mail Studio')
            ->selectedSubnavItem($view)
            ->mainAttributes(['class' => ['mail-studio-screen']])
            ->contentTemplate('mail-studio/app.twig', [
                'bootstrapUrl' => Url::cpUrl('mail-studio/api/bootstrap'),
                'initialView' => $view,
                'csrfToken' => csrf_token(),
            ]);
    }
}
