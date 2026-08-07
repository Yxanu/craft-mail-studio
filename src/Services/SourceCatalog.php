<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Services;

use CraftCms\Cms\SystemMessage\SystemMessages;
use Solspace\Freeform\Bundles\Notifications\Providers\NotificationsProvider;
use Solspace\Freeform\Freeform;
use Throwable;
use Yxanu\MailStudio\Data\SourceDefinition;

class SourceCatalog
{
    public function __construct(
        private readonly SourceRegistry $registry,
        private readonly PresetLibrary $presets,
    ) {}

    /** @return array<int, SourceDefinition> */
    public function all(bool $includeFreeform = true): array
    {
        $sources = array_values($this->registry->all());
        $sources = [...$sources, ...$this->systemMessages()];

        if ($includeFreeform) {
            $sources = [...$sources, ...$this->freeform()];
        }

        usort($sources, fn (SourceDefinition $a, SourceDefinition $b): int => [$a->adapter, $a->label] <=> [$b->adapter, $b->label]);

        return $sources;
    }

    /** @return array<int, SourceDefinition> */
    private function systemMessages(): array
    {
        try {
            return app(SystemMessages::class)->messages()
                ->map(function ($message): SourceDefinition {
                    $variables = [];
                    preg_match_all('/{([a-zA-Z_][a-zA-Z0-9_.]*)}/', $message->subject."\n".$message->body, $matches);
                    foreach (array_unique($matches[1] ?? []) as $path) {
                        $variables[$path] = ['label' => $path, 'type' => 'mixed', 'required' => false, 'example' => $path];
                    }
                    $variables['user.fullName'] ??= ['label' => 'User full name', 'type' => 'string', 'required' => false, 'example' => 'Ada Lovelace'];
                    $variables['user.email'] ??= ['label' => 'User email', 'type' => 'string', 'required' => false, 'example' => 'ada@example.test'];

                    return new SourceDefinition(
                        key: 'craft.system.'.$message->key,
                        label: $message->heading ?: $message->key,
                        variables: $variables,
                        sampleData: [
                            'user' => ['fullName' => 'Ada Lovelace', 'email' => 'ada@example.test'],
                            'systemName' => 'Craft CMS',
                        ],
                        defaultTemplate: $this->presets->get('simple-message'),
                        adapter: 'craft-system',
                        description: 'Craft system message: '.$message->key,
                    );
                })
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<int, SourceDefinition> */
    private function freeform(): array
    {
        if (! class_exists(Freeform::class)) {
            return [];
        }

        try {
            $freeform = Freeform::getInstance();
            $provider = app(NotificationsProvider::class);
            $sources = [];

            foreach ($freeform->forms->getAllNonArchivedForms(true) as $form) {
                $variables = [
                    'form.name' => ['label' => 'Form name', 'type' => 'string', 'example' => $form->getName()],
                    'submission.id' => ['label' => 'Submission ID', 'type' => 'integer', 'example' => 1001],
                    'dateCreated' => ['label' => 'Date created', 'type' => 'date', 'example' => date('Y-m-d H:i')],
                ];
                $samples = [
                    'form' => ['name' => $form->getName()],
                    'submission' => ['id' => 1001],
                    'dateCreated' => date('Y-m-d H:i'),
                ];
                foreach ($form->getFields() as $field) {
                    $handle = $field->getHandle();
                    if (! $handle) {
                        continue;
                    }
                    $variables[$handle.'.value'] = [
                        'label' => $field->getLabel() ?: $handle,
                        'type' => 'mixed',
                        'example' => 'Example value',
                    ];
                    $samples[$handle] = ['value' => 'Example value'];
                }

                foreach ($provider->getByForm($form) as $notification) {
                    $key = $form->getUid().'.'.$notification->getUid();
                    $sources[] = new SourceDefinition(
                        key: 'freeform.'.$key,
                        label: $form->getName().' / '.$notification->getName(),
                        variables: $variables,
                        sampleData: $samples,
                        defaultTemplate: $this->presets->get('request-received'),
                        adapter: 'freeform',
                        description: $notification->getClassName(),
                    );
                }
            }

            return $sources;
        } catch (Throwable) {
            return [];
        }
    }
}
