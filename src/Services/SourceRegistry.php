<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Services;

use Illuminate\Support\Arr;
use Yxanu\MailStudio\Data\SourceDefinition;
use Yxanu\MailStudio\Exceptions\InvalidSourceVariables;
use Yxanu\MailStudio\Exceptions\SourceNotFound;

class SourceRegistry
{
    /** @var array<string, SourceDefinition> */
    private array $sources = [];

    public function register(SourceDefinition $definition): void
    {
        $this->sources[$definition->key] = $definition;
    }

    public function has(string $key): bool
    {
        return isset($this->sources[$key]);
    }

    public function get(string $key): SourceDefinition
    {
        return $this->sources[$key]
            ?? throw new SourceNotFound("Mail Studio source [$key] is not registered.");
    }

    /** @return array<string, SourceDefinition> */
    public function all(): array
    {
        ksort($this->sources);

        return $this->sources;
    }

    /** @param array<string, mixed> $variables */
    public function validate(SourceDefinition $definition, array $variables): void
    {
        $missing = [];

        foreach ($definition->variables as $path => $config) {
            if (($config['required'] ?? false) && ! Arr::has($variables, $path)) {
                $missing[] = $path;
            }
        }

        if ($missing !== []) {
            throw new InvalidSourceVariables(
                'Missing required Mail Studio variables: '.implode(', ', $missing)
            );
        }
    }
}
