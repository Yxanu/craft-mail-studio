<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Data;

final readonly class RenderedTemplate
{
    public function __construct(
        public string $subject,
        public string $preheader,
        public string $html,
        public string $text,
    ) {}
}
