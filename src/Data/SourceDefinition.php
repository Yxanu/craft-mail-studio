<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Data;

use Closure;
use InvalidArgumentException;

final readonly class SourceDefinition
{
    /**
     * @param  array<string, array{label?: string, type?: string, required?: bool, example?: mixed}>  $variables
     * @param  Closure(): array<string, mixed>|array<string, mixed>  $sampleData
     * @param  array<string, mixed>|null  $defaultTemplate
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $variables = [],
        public Closure|array $sampleData = [],
        public ?array $defaultTemplate = null,
        public string $adapter = 'custom',
        public ?string $description = null,
    ) {
        if (! preg_match('/^[a-z][a-z0-9._-]+$/', $key)) {
            throw new InvalidArgumentException("Invalid Mail Studio source key [$key].");
        }
    }

    /** @return array<string, mixed> */
    public function samples(): array
    {
        return $this->sampleData instanceof Closure
            ? ($this->sampleData)()
            : $this->sampleData;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'adapter' => $this->adapter,
            'description' => $this->description,
            'variables' => $this->variables,
            'sampleData' => $this->samples(),
        ];
    }
}
