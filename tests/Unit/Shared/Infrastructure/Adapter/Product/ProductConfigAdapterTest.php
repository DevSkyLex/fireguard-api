<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Adapter\Product;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Product\EmailBrand;
use Shared\Infrastructure\Adapter\Product\ProductConfigAdapter;
use Tests\Support\Shared\ProductConfigFixture;

/**
 * Class ProductConfigAdapterTest
 *
 * @category Test
 */
#[CoversClass(ProductConfigAdapter::class)]
final class ProductConfigAdapterTest extends TestCase
{
  #[Test]
  public function preservesTheShippedFireguardDefaults(): void
  {
    $product = ProductConfigFixture::create();

    self::assertSame('Fireguard', $product->identity()->publicName);
    self::assertSame('FireGuard API', $product->identity()->apiTitle);
    self::assertSame('Fire-equipment park and maintenance API — internal customers, sites, equipment, controls, interventions, parts, procurement and private maintenance costs', $product->identity()->apiDescription);
    self::assertSame('1.0.0', $product->identity()->version);
    self::assertSame('fireguard-mark', $product->emailBrand()->contentId);
    self::assertFileExists($product->emailBrand()->markPath);
    self::assertSame('http://localhost:4200', $product->frontendUrl());
    self::assertSame('You are the Fireguard assistant, helping an organization member with fire-safety compliance questions about their facilities, equipment, and interventions. Answer concisely and only from information you are actually given; say so plainly when you do not know.', $product->assistantInstructions());
  }

  #[Test]
  public function publishesConfiguredProductValues(): void
  {
    $product = ProductConfigFixture::create([
      'product.public_name' => 'Workshop',
      'product.api_title' => 'Workshop API',
      'product.api_description' => 'Workshop operations',
      'product.version' => '2.4.0',
      'product.email_mark_path' => '/configured/workshop.png',
      'product.email_mark_cid' => 'workshop-mark',
      'product.frontend_url' => 'https://workshop.example.test/app/',
      'product.assistant_instructions' => 'Answer questions about workshop operations.',
    ]);

    self::assertSame('Workshop', $product->identity()->publicName);
    self::assertSame('Workshop API', $product->identity()->apiTitle);
    self::assertSame('Workshop operations', $product->identity()->apiDescription);
    self::assertSame('2.4.0', $product->identity()->version);
    self::assertSame('/configured/workshop.png', $product->emailBrand()->markPath);
    self::assertSame('workshop-mark', $product->emailBrand()->contentId);
    self::assertSame('https://workshop.example.test/app/', $product->frontendUrl());
    self::assertSame('Answer questions about workshop operations.', $product->assistantInstructions());
  }

  #[Test]
  #[DataProvider('invalidFrontendUrls')]
  public function rejectsInvalidFrontendConfiguration(string $frontendUrl): void
  {
    $this->expectException(InvalidArgumentException::class);

    ProductConfigFixture::create(['product.frontend_url' => $frontendUrl]);
  }

  #[Test]
  public function rejectsAnInvalidEmailContentId(): void
  {
    $this->expectException(InvalidArgumentException::class);

    new EmailBrand('/configured/mark.png', 'mark" onerror="alert(1)');
  }

  /**
   * @return iterable<string, array{string}> invalid trusted frontend values
   */
  public static function invalidFrontendUrls(): iterable
  {
    yield 'relative URL' => ['/app'];
    yield 'protocol-relative URL' => ['//outside.example.test'];
    yield 'non HTTP scheme' => ['javascript:alert(1)'];
    yield 'credentials' => ['https://user:password@app.example.test'];
    yield 'username only' => ['https://user@app.example.test'];
    yield 'query' => ['https://app.example.test?redirect=outside'];
    yield 'fragment' => ['https://app.example.test#outside'];
    yield 'backslash' => ['https://app.example.test/\\outside'];
    yield 'control character' => ["https://app.example.test/\noutside"];
  }
}
