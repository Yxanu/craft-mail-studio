<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yxanu\MailStudio\Data\SourceDefinition;
use Yxanu\MailStudio\Exceptions\InvalidSourceVariables;
use Yxanu\MailStudio\Exceptions\SourceNotFound;
use Yxanu\MailStudio\Services\SourceRegistry;

class SourceRegistryTest extends TestCase
{
    public function test_it_registers_and_validates_nested_variables(): void
    {
        $registry = new SourceRegistry;
        $source = new SourceDefinition(
            key: 'orders.receipt',
            label: 'Order receipt',
            variables: ['customer.email' => ['required' => true]],
        );
        $registry->register($source);

        $registry->validate($registry->get('orders.receipt'), ['customer' => ['email' => 'ada@example.test']]);
        self::assertTrue($registry->has('orders.receipt'));
    }

    public function test_it_rejects_missing_required_variables(): void
    {
        $registry = new SourceRegistry;
        $source = new SourceDefinition(
            key: 'orders.receipt',
            label: 'Order receipt',
            variables: ['order.id' => ['required' => true]],
        );

        $this->expectException(InvalidSourceVariables::class);
        $registry->validate($source, []);
    }

    public function test_it_throws_for_unknown_sources(): void
    {
        $this->expectException(SourceNotFound::class);
        (new SourceRegistry)->get('unknown.source');
    }
}
