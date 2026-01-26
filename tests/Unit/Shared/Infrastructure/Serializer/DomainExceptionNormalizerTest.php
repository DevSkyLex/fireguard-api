<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Serializer;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Domain\Exception\{DomainException, EntityNotFoundException};
use Shared\Infrastructure\Serializer\DomainExceptionNormalizer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

use function preg_replace;
use function strrpos;
use function strtolower;
use function substr;

/**
 * Test DomainExceptionNormalizerTest.
 *
 * @category Serializer Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DomainExceptionNormalizer::class)]
final class DomainExceptionNormalizerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testNormalizeDomainException(): void
  {
    $normalizer = new DomainExceptionNormalizer();
    $exception = new class ('Domain error') extends DomainException {};

    $data = $normalizer->normalize($exception);

    self::assertSame(400, $data['status']);
    self::assertSame('Domain Error', $data['hydra:title']);
    self::assertSame('Domain error', $data['detail']);
    self::assertSame('/errors/' . $this->expectedType($exception::class), $data['type']);
  }

  #[Test]
  public function testNormalizeEntityNotFound(): void
  {
    $normalizer = new DomainExceptionNormalizer();
    $exception = EntityNotFoundException::forId('User', 'user-123');

    /** @var array{status: int, 'hydra:title': string, detail: string, type: string} $data */
    $data = $normalizer->normalize($exception);

    self::assertSame(404, $data['status']);
    self::assertSame('Resource Not Found', $data['hydra:title']);
    self::assertStringContainsString('User with ID', $data['detail']);
  }

  #[Test]
  public function testSupportsNormalization(): void
  {
    $normalizer = new DomainExceptionNormalizer();
    $exception = new class ('Domain error') extends DomainException {};
    $flatten = FlattenException::createFromThrowable($exception);

    self::assertTrue($normalizer->supportsNormalization($exception));
    self::assertTrue($normalizer->supportsNormalization($flatten));
    self::assertFalse($normalizer->supportsNormalization(new RuntimeException('boom')));
  }

  private function expectedType(string $className): string
  {
    $shortName = substr($className, strrpos($className, '\\') + 1);
    $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $shortName) ?? $shortName);

    return $snake;
  }
  // #endregion
}
