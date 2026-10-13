<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Services;

class PresetLibrary
{
    /** @var array<string, array<string, mixed>> */
    private array $registered = [];

    /** @param array<string, mixed> $preset */
    public function register(string $key, array $preset): void
    {
        if (! preg_match('/^[a-z][a-z0-9-]+$/', $key)) {
            throw new \InvalidArgumentException("Invalid Mail Studio preset key [$key].");
        }

        foreach (['name', 'description', 'subject', 'document'] as $required) {
            if (! array_key_exists($required, $preset)) {
                throw new \InvalidArgumentException("Mail Studio preset [$key] is missing [$required].");
            }
        }

        $preset['preheader'] ??= '';
        $this->registered[$key] = $preset;
    }

    /** @return array<string, array<string, mixed>> */
    public function all(bool $includeRegistered = true): array
    {
        $builtIn = [
            'simple-message' => $this->preset(
                'Simple message',
                'A restrained transactional message with a single content area.',
                'A message for you',
                [
                    $this->block('heading', ['text' => 'Hello']),
                    $this->block('richText', ['html' => '<p>Here is the information you requested.</p>']),
                ],
            ),
            'request-received' => $this->preset(
                'Request received',
                'Confirmation that a request or form submission arrived.',
                'We received your request',
                [
                    $this->block('heading', ['text' => 'Thank you for your request']),
                    $this->block('richText', ['html' => '<p>We received your message and will get back to you shortly.</p>']),
                    $this->block('callout', ['title' => 'What happens next?', 'text' => 'Our team reviews the details and contacts you directly.']),
                ],
            ),
            'internal-notification' => $this->preset(
                'Internal notification',
                'Compact operational notification for a team inbox.',
                'New notification',
                [
                    $this->block('heading', ['text' => 'New notification']),
                    $this->block('keyValue', ['items' => [
                        ['label' => 'Name', 'value' => 'Customer name'],
                        ['label' => 'Email', 'value' => 'customer@example.com'],
                    ]]),
                    $this->block('richText', ['html' => '<p>Review the details in Craft CMS.</p>']),
                ],
            ),
            'action-required' => $this->preset(
                'Action required',
                'A message focused on one clear next action.',
                'Action required',
                [
                    $this->block('heading', ['text' => 'Action required']),
                    $this->block('richText', ['html' => '<p>Please complete the following step to continue.</p>']),
                    $this->block('button', ['label' => 'Continue', 'url' => 'https://example.com']),
                    $this->block('callout', ['title' => 'Link not working?', 'text' => 'Contact our support team for assistance.']),
                ],
            ),
            'confirmation-receipt' => $this->preset(
                'Confirmation or receipt',
                'Confirmation with reference details and optional follow-up action.',
                'Your confirmation',
                [
                    $this->block('heading', ['text' => 'Your confirmation']),
                    $this->block('richText', ['html' => '<p>Your request has been processed successfully.</p>']),
                    $this->block('keyValue', ['items' => [
                        ['label' => 'Reference', 'value' => 'Reference number'],
                        ['label' => 'Date', 'value' => 'Confirmation date'],
                    ]]),
                    $this->block('divider', ['spacing' => 24]),
                    $this->block('richText', ['html' => '<p>Keep this email for your records.</p>']),
                ],
            ),
        ];

        return $includeRegistered ? array_replace($builtIn, $this->registered) : $builtIn;
    }

    /** @return array<string, mixed> */
    public function get(string $key): array
    {
        return $this->all()[$key] ?? $this->all()['simple-message'];
    }

    /** @param array<int, array<string, mixed>> $blocks */
    private function preset(string $name, string $description, string $subject, array $blocks): array
    {
        return [
            'name' => $name,
            'description' => $description,
            'subject' => $subject,
            'preheader' => '',
            'document' => ['version' => 1, 'blocks' => $blocks],
        ];
    }

    /** @param array<string, mixed> $data */
    private function block(string $type, array $data): array
    {
        return [
            'id' => $type.'-'.bin2hex(random_bytes(4)),
            'type' => $type,
            'data' => $data,
        ];
    }
}
