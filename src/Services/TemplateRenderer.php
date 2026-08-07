<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Services;

use CraftCms\Cms\View\TemplateMode;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use Yxanu\MailStudio\Data\RenderedTemplate;

use function CraftCms\Cms\renderSandboxedString;

class TemplateRenderer
{
    /** @param array<string, mixed> $snapshot @param array<string, mixed> $variables */
    public function render(array $snapshot, array $variables = [], bool $resolveTokens = true): RenderedTemplate
    {
        $brand = array_replace(BrandProfileRepository::defaultConfig(), $snapshot['brand'] ?? []);
        $document = $snapshot['document'] ?? ['version' => 1, 'blocks' => []];
        $allowedTokens = is_array($snapshot['allowedTokens'] ?? null) ? $snapshot['allowedTokens'] : null;
        $subject = $this->renderValue((string) ($snapshot['subject'] ?? ''), $variables, $resolveTokens, true, $allowedTokens);
        $preheader = $this->renderValue((string) ($snapshot['preheader'] ?? ''), $variables, $resolveTokens, true, $allowedTokens);

        $htmlBlocks = [];
        $textBlocks = [];
        foreach (($document['blocks'] ?? []) as $block) {
            [$html, $text] = $this->renderBlock($block, $brand, $variables, $resolveTokens, $allowedTokens);
            if ($html !== '') {
                $htmlBlocks[] = $html;
            }
            if ($text !== '') {
                $textBlocks[] = $text;
            }
        }

        return new RenderedTemplate(
            subject: $this->plainHeader($subject),
            preheader: $this->plainHeader($preheader),
            html: $this->layout(implode("\n", $htmlBlocks), $preheader, $brand),
            text: trim(implode("\n\n", $textBlocks).$this->textFooter($brand)),
        );
    }

    /** @param array<string, mixed> $block @param array<string, mixed> $brand @param array<string, mixed> $variables @return array{string, string} */
    private function renderBlock(array $block, array $brand, array $variables, bool $resolveTokens, ?array $allowedTokens): array
    {
        $data = is_array($block['data'] ?? null) ? $block['data'] : [];
        $type = $block['type'] ?? '';
        $textColor = $this->color($brand['textColor']);
        $mutedColor = $this->color($brand['mutedColor']);
        $primaryColor = $this->color($brand['primaryColor']);
        $borderColor = $this->color($brand['borderColor']);

        return match ($type) {
            'heading' => (function () use ($data, $variables, $resolveTokens, $allowedTokens, $textColor): array {
                $text = $this->renderValue((string) ($data['text'] ?? ''), $variables, $resolveTokens, true, $allowedTokens);
                $level = min(3, max(1, (int) ($data['level'] ?? 1)));
                $size = [1 => 30, 2 => 24, 3 => 20][$level];

                return [
                    '<h'.$level.' style="margin:0 0 20px;color:'.$textColor.';font-family:Arial,sans-serif;font-size:'.$size.'px;line-height:1.25;font-weight:700;">'.$text.'</h'.$level.'>',
                    trim(strip_tags($text)),
                ];
            })(),
            'richText' => (function () use ($data, $variables, $resolveTokens, $allowedTokens, $textColor): array {
                $html = $this->sanitizeRichText((string) ($data['html'] ?? ''));
                $html = $this->renderValue($html, $variables, $resolveTokens, allowedTokens: $allowedTokens);

                return [
                    '<div style="margin:0 0 20px;color:'.$textColor.';font-family:Arial,sans-serif;font-size:16px;line-height:1.6;">'.$html.'</div>',
                    $this->plainText($html),
                ];
            })(),
            'button' => (function () use ($data, $variables, $resolveTokens, $allowedTokens, $primaryColor): array {
                $label = $this->renderValue((string) ($data['label'] ?? ''), $variables, $resolveTokens, true, $allowedTokens);
                $url = $this->safeUrl($this->renderValue((string) ($data['url'] ?? ''), $variables, $resolveTokens, allowedTokens: $allowedTokens), ! $resolveTokens);
                if ($label === '' || $url === '') {
                    return ['', ''];
                }

                return [
                    '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:4px 0 24px"><tr><td style="border-radius:4px;background:'.$primaryColor.'"><a href="'.$this->escape($url).'" style="display:inline-block;padding:13px 20px;color:#ffffff;text-decoration:none;font-family:Arial,sans-serif;font-size:16px;font-weight:700;">'.$label.'</a></td></tr></table>',
                    trim(strip_tags($label)).': '.$url,
                ];
            })(),
            'image' => (function () use ($data, $variables, $resolveTokens, $allowedTokens): array {
                $url = $this->safeUrl($this->renderValue((string) ($data['url'] ?? ''), $variables, $resolveTokens, allowedTokens: $allowedTokens), ! $resolveTokens);
                if ($url === '') {
                    return ['', ''];
                }
                $alt = trim(strip_tags((string) ($data['alt'] ?? '')));

                return [
                    '<img src="'.$this->escape($url).'" alt="'.$this->escape($alt).'" width="100%" style="display:block;width:100%;height:auto;margin:0 0 24px;border:0;">',
                    $alt !== '' ? '[Image: '.$alt.']' : '',
                ];
            })(),
            'callout' => (function () use ($data, $variables, $resolveTokens, $allowedTokens, $textColor, $mutedColor, $borderColor): array {
                $title = $this->renderValue((string) ($data['title'] ?? ''), $variables, $resolveTokens, true, $allowedTokens);
                $text = $this->renderValue((string) ($data['text'] ?? ''), $variables, $resolveTokens, true, $allowedTokens);

                return [
                    '<div style="margin:0 0 24px;padding:16px;border:1px solid '.$borderColor.';background:#f9fafb;color:'.$textColor.';font-family:Arial,sans-serif;"><strong style="display:block;margin-bottom:6px;">'.$title.'</strong><span style="color:'.$mutedColor.';line-height:1.5;">'.$text.'</span></div>',
                    trim(strip_tags($title."\n".$text)),
                ];
            })(),
            'divider' => (function () use ($data, $borderColor): array {
                $spacing = min(64, max(8, (int) ($data['spacing'] ?? 24)));

                return ['<div style="height:'.$spacing.'px;border-top:1px solid '.$borderColor.';line-height:'.$spacing.'px;">&nbsp;</div>', '---'];
            })(),
            'keyValue' => $this->renderKeyValues($data, $variables, $resolveTokens, $allowedTokens, $textColor, $mutedColor, $borderColor),
            default => ['', ''],
        };
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $variables @return array{string, string} */
    private function renderKeyValues(array $data, array $variables, bool $resolveTokens, ?array $allowedTokens, string $textColor, string $mutedColor, string $borderColor): array
    {
        $rows = [];
        $plain = [];
        foreach (($data['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $label = $this->renderValue((string) ($item['label'] ?? ''), $variables, $resolveTokens, true, $allowedTokens);
            $value = $this->renderValue((string) ($item['value'] ?? ''), $variables, $resolveTokens, true, $allowedTokens);
            $rows[] = '<tr><td style="padding:10px 12px;border-bottom:1px solid '.$borderColor.';color:'.$mutedColor.';font-family:Arial,sans-serif;font-size:14px;width:34%;vertical-align:top;">'.$label.'</td><td style="padding:10px 12px;border-bottom:1px solid '.$borderColor.';color:'.$textColor.';font-family:Arial,sans-serif;font-size:14px;vertical-align:top;">'.$value.'</td></tr>';
            $plain[] = trim(strip_tags($label)).': '.trim(strip_tags($value));
        }

        if ($rows === []) {
            return ['', ''];
        }

        return [
            '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:0 0 24px;border:1px solid '.$borderColor.';border-bottom:0;border-collapse:collapse;">'.implode('', $rows).'</table>',
            implode("\n", $plain),
        ];
    }

    /** @param array<string, mixed> $brand */
    private function layout(string $content, string $preheader, array $brand): string
    {
        $width = min(720, max(480, (int) $brand['containerWidth']));
        $logo = $this->safeUrl((string) $brand['logoUrl']);
        $logoHtml = $logo !== ''
            ? '<img src="'.$this->escape($logo).'" alt="'.$this->escape((string) $brand['senderName']).'" height="40" style="display:block;height:40px;width:auto;border:0;">'
            : '<strong style="font-family:Arial,sans-serif;font-size:18px;color:'.$this->color($brand['textColor']).';">'.$this->escape((string) $brand['senderName']).'</strong>';
        $preheaderHtml = $preheader === '' ? '' : '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">'.$preheader.'</div>';
        $support = array_filter([(string) $brand['supportEmail'], (string) $brand['supportPhone']]);
        $footer = $this->escape((string) $brand['footerText']);
        if ($support !== []) {
            $footer .= '<br>'.implode(' &middot; ', array_map($this->escape(...), $support));
        }

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title></title></head><body style="margin:0;padding:0;background:'.$this->color($brand['backgroundColor']).';">'.$preheaderHtml.'<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:'.$this->color($brand['backgroundColor']).';"><tr><td align="center" style="padding:32px 16px;"><table role="presentation" width="'.$width.'" cellspacing="0" cellpadding="0" style="width:100%;max-width:'.$width.'px;background:'.$this->color($brand['surfaceColor']).';border:1px solid '.$this->color($brand['borderColor']).';border-radius:6px;"><tr><td style="padding:28px 32px 16px;">'.$logoHtml.'</td></tr><tr><td style="padding:16px 32px 32px;">'.$content.'</td></tr></table><div style="max-width:'.$width.'px;padding:18px 24px;color:'.$this->color($brand['mutedColor']).';font-family:Arial,sans-serif;font-size:12px;line-height:1.5;text-align:center;">'.$footer.'</div></td></tr></table></body></html>';
    }

    /** @param array<string, mixed> $brand */
    private function textFooter(array $brand): string
    {
        $parts = array_filter([
            trim((string) $brand['footerText']),
            trim((string) $brand['supportEmail']),
            trim((string) $brand['supportPhone']),
        ]);

        return $parts === [] ? '' : "\n\n--\n".implode("\n", $parts);
    }

    /** @param array<string, mixed> $variables */
    private function renderValue(string $value, array $variables, bool $resolveTokens, bool $escapeStatic = false, ?array $allowedTokens = null): string
    {
        $this->assertSafeTokens($value, $allowedTokens);
        if (! $resolveTokens) {
            return $escapeStatic ? $this->escapePreservingTokens($value) : $value;
        }

        $template = $escapeStatic ? $this->escapePreservingTokens($value) : $value;

        return renderSandboxedString($template, $variables, TemplateMode::Site, true);
    }

    private function assertSafeTokens(string $value, ?array $allowedTokens = null): void
    {
        preg_match_all('/{{\s*(.*?)\s*}}|{%\s*(.*?)\s*%}/s', $value, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (($match[2] ?? '') !== '') {
                throw new InvalidArgumentException('Template control structures are not allowed in Mail Studio content.');
            }
            $token = trim($match[1] ?? '');
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*$/', $token)) {
                throw new InvalidArgumentException('Only simple Mail Studio variable tokens are allowed.');
            }
            if ($allowedTokens !== null && ! in_array($token, $allowedTokens, true)) {
                throw new InvalidArgumentException("Mail Studio variable token [$token] is not declared by the source.");
            }
        }
    }

    private function escapePreservingTokens(string $value): string
    {
        $tokens = [];
        $protected = preg_replace_callback('/{{\s*.*?\s*}}/s', function (array $match) use (&$tokens): string {
            $key = '__MAIL_STUDIO_TOKEN_'.count($tokens).'__';
            $tokens[$key] = $match[0];

            return $key;
        }, $value) ?? $value;

        return strtr($this->escape($protected), $tokens);
    }

    private function sanitizeRichText(string $html): string
    {
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><a><ul><ol><li>');
        if ($html === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach (iterator_to_array($document->getElementsByTagName('*')) as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }
            foreach (iterator_to_array($element->attributes) as $attribute) {
                if ($element->tagName === 'a' && in_array($attribute->name, ['href', 'title'], true)) {
                    continue;
                }
                $element->removeAttribute($attribute->name);
            }
            if ($element->tagName === 'a') {
                $href = $this->safeUrl($element->getAttribute('href'));
                if ($href === '') {
                    $element->removeAttribute('href');
                } else {
                    $element->setAttribute('href', $href);
                    $element->setAttribute('style', 'color:#155eef;text-decoration:underline;');
                }
            }
        }

        $wrapper = $document->getElementsByTagName('div')->item(0);
        $result = '';
        if ($wrapper) {
            foreach ($wrapper->childNodes as $node) {
                $result .= $document->saveHTML($node);
            }
        }

        return $result;
    }

    private function plainText(string $html): string
    {
        $html = preg_replace('/<\/(p|li|div|h[1-6])>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function safeUrl(string $url, bool $allowToken = false): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\r\n]/', $url)) {
            return '';
        }
        if (($allowToken && preg_match('/^{{\s*[a-zA-Z_][a-zA-Z0-9_.]*\s*}}$/', $url)) || str_starts_with($url, '/') || filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        return '';
    }

    private function plainHeader(string $value): string
    {
        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function color(mixed $value): string
    {
        $value = strtolower((string) $value);

        return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : '#000000';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
