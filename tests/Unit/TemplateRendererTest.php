<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Yxanu\MailStudio\Services\BrandProfileRepository;
use Yxanu\MailStudio\Services\PresetLibrary;
use Yxanu\MailStudio\Services\TemplateRenderer;

class TemplateRendererTest extends TestCase
{
    public function test_generic_presets_do_not_require_source_specific_tokens(): void
    {
        foreach ((new PresetLibrary)->all(false) as $preset) {
            $rendered = (new TemplateRenderer)->render([
                ...$preset,
                'brand' => BrandProfileRepository::defaultConfig(),
                'allowedTokens' => [],
            ], resolveTokens: false);

            self::assertNotEmpty($rendered->html);
            self::assertNotEmpty($rendered->text);
        }
    }

    public function test_it_renders_email_safe_html_and_plain_text(): void
    {
        $rendered = (new TemplateRenderer)->render($this->snapshot([
            $this->block('heading', ['text' => 'Hello <script>alert(1)</script>', 'level' => 1]),
            $this->block('richText', ['html' => '<p onclick="alert(1)">A <strong>safe</strong> message.</p><img src=x>']),
            $this->block('keyValue', ['items' => [['label' => 'Reference', 'value' => 'MS-42']]]),
        ]), resolveTokens: false);

        self::assertStringContainsString('role="presentation"', $rendered->html);
        self::assertStringNotContainsString('<script>', $rendered->html);
        self::assertStringNotContainsString('onclick=', $rendered->html);
        self::assertStringNotContainsString('<img src=x>', $rendered->html);
        self::assertStringContainsString("A safe message.\n\nReference: MS-42", $rendered->text);
    }

    public function test_it_rejects_control_structures_and_undeclared_tokens(): void
    {
        $renderer = new TemplateRenderer;

        $this->expectException(InvalidArgumentException::class);
        $renderer->render([
            ...$this->snapshot([$this->block('heading', ['text' => '{% if admin %}Secret{% endif %}'])]),
            'allowedTokens' => ['customer.name'],
        ], resolveTokens: false);
    }

    public function test_it_rejects_an_unknown_simple_token(): void
    {
        $renderer = new TemplateRenderer;

        $this->expectException(InvalidArgumentException::class);
        $renderer->render([
            ...$this->snapshot([$this->block('heading', ['text' => '{{ internal.secret }}'])]),
            'allowedTokens' => ['customer.name'],
        ], resolveTokens: false);
    }

    public function test_it_preserves_a_deferred_token_url(): void
    {
        $rendered = (new TemplateRenderer)->render([
            ...$this->snapshot([$this->block('button', ['label' => 'Download', 'url' => '{{ downloadUrl }}'])]),
            'allowedTokens' => ['downloadUrl'],
        ], resolveTokens: false);

        self::assertStringContainsString('href="{{ downloadUrl }}"', $rendered->html);
        self::assertStringContainsString('Download: {{ downloadUrl }}', $rendered->text);
    }

    public function test_it_removes_unsafe_urls_from_every_html_surface(): void
    {
        foreach ([
            'javascript://example.test/%0Aalert(1)',
            'file:///tmp/qa',
            'ftp://example.test/file',
            'data:text/html,unsafe',
            '//example.test/file',
            '/\\example.test/file',
            "https://example.test/\tfile",
            "https://example.test/\nfile",
        ] as $url) {
            $snapshot = $this->snapshot([
                $this->block('button', ['label' => 'Unsafe button', 'url' => $url]),
                $this->block('image', ['url' => $url, 'alt' => 'Unsafe image']),
                $this->block('richText', ['html' => '<p><a href="'.htmlspecialchars($url, ENT_QUOTES).'">Link</a></p>']),
            ]);
            $snapshot['brand']['logoUrl'] = $url;
            $rendered = (new TemplateRenderer)->render($snapshot, resolveTokens: false);

            self::assertStringNotContainsString('href=', $rendered->html, $url);
            self::assertStringNotContainsString('src=', $rendered->html, $url);
            self::assertStringNotContainsString('Unsafe button', $rendered->text, $url);
            self::assertStringNotContainsString('Unsafe image', $rendered->text, $url);
            self::assertStringContainsString('Link', $rendered->text, $url);
        }
    }

    public function test_it_keeps_http_https_and_root_relative_urls(): void
    {
        foreach (['https://example.test/file', 'http://example.test/file', '/downloads/file'] as $url) {
            $rendered = (new TemplateRenderer)->render($this->snapshot([
                $this->block('button', ['label' => 'Download', 'url' => $url]),
                $this->block('image', ['url' => $url, 'alt' => 'Cover']),
                $this->block('richText', ['html' => '<p><a href="'.$url.'">Link</a></p>']),
            ]), resolveTokens: false);

            self::assertStringContainsString('href="'.$url.'"', $rendered->html);
            self::assertStringContainsString('src="'.$url.'"', $rendered->html);
            self::assertStringContainsString('Download: '.$url, $rendered->text);
        }
    }

    /** @param array<int, array<string, mixed>> $blocks @return array<string, mixed> */
    private function snapshot(array $blocks): array
    {
        return [
            'subject' => 'Message <test>',
            'preheader' => 'Preview',
            'document' => ['version' => 1, 'blocks' => $blocks],
            'brand' => BrandProfileRepository::defaultConfig(),
        ];
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function block(string $type, array $data): array
    {
        return ['id' => $type.'-1', 'type' => $type, 'data' => $data];
    }
}
