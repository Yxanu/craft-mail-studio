<?php

declare(strict_types=1);

namespace Yxanu\MailStudio\Tests\Unit;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BrandAssetsTest extends TestCase
{
    public static function assets(): array
    {
        return [
            'plugin icon' => ['icon.svg'],
            'navigation mask' => ['icon-mask.svg'],
            'light wordmark' => ['brand/mail-studio-logo.svg'],
            'dark wordmark' => ['brand/mail-studio-logo-dark.svg'],
        ];
    }

    #[DataProvider('assets')]
    public function test_brand_assets_are_self_contained_svg_files(string $relativePath): void
    {
        $path = dirname(__DIR__, 2).'/resources/'.$relativePath;
        self::assertFileExists($path);
        self::assertLessThan(2_000_000, filesize($path));

        $document = new DOMDocument;
        self::assertTrue($document->load($path, LIBXML_NONET));
        $root = $document->documentElement;
        self::assertInstanceOf(DOMElement::class, $root);
        self::assertSame('svg', $root->localName);
        self::assertSame('http://www.w3.org/2000/svg', $root->namespaceURI);
        self::assertNotEmpty($root->getAttribute('viewBox'));

        $query = new DOMXPath($document);
        $query->registerNamespace('svg', 'http://www.w3.org/2000/svg');
        self::assertGreaterThan(0, $query->query('//svg:path')->length);
        self::assertSame(0, $query->query('//svg:text | //svg:image | //svg:script | //svg:foreignObject | //svg:style | //@href')->length);
    }

    public function test_plugin_icon_uses_the_cobalt_stamp_palette(): void
    {
        $document = new DOMDocument;
        self::assertTrue($document->load(dirname(__DIR__, 2).'/resources/icon.svg', LIBXML_NONET));
        $query = new DOMXPath($document);
        $query->registerNamespace('svg', 'http://www.w3.org/2000/svg');
        self::assertSame('0 0 64 64', $document->documentElement->getAttribute('viewBox'));
        self::assertSame(1, $query->query('//svg:path[@fill="#2347D9"]')->length);
        self::assertSame(1, $query->query('//svg:path[@fill="#FF8A3D"]')->length);
    }

    public function test_navigation_mask_inherits_the_control_panel_color(): void
    {
        $document = new DOMDocument;
        self::assertTrue($document->load(dirname(__DIR__, 2).'/resources/icon-mask.svg', LIBXML_NONET));
        $query = new DOMXPath($document);
        $query->registerNamespace('svg', 'http://www.w3.org/2000/svg');
        self::assertSame('0 0 32 32', $document->documentElement->getAttribute('viewBox'));
        self::assertSame(1, $query->query('//svg:path')->length);
        self::assertSame(1, $query->query('//svg:path[@fill="currentColor"][@fill-rule="evenodd"]')->length);
    }
}
