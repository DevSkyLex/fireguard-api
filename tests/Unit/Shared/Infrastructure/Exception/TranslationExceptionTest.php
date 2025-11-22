<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Exception;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Infrastructure\Exception\TranslationException;
use Exception;

/**
 * Class TranslationExceptionTest
 *
 * Unit tests for the TranslationException.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Infrastructure\Exception
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * @covers \Shared\Infrastructure\Exception\TranslationException
 */
#[CoversClass(className: TranslationException::class)]
final class TranslationExceptionTest extends TestCase
{
  /**
   * Test the translateFailed factory method.
   */
  #[Test]
  public function testTranslateFailed(): void
  {
    $previous = new Exception('Translation error');
    $exception = TranslationException::translateFailed('message.id', $previous);

    $this->assertSame('Failed to translate message "message.id".', $exception->getMessage());
    $this->assertSame($previous, $exception->getPrevious());
  }
}

