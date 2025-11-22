<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Exception;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Infrastructure\Exception\MailSendingException;
use Exception;

/**
 * Class MailSendingExceptionTest
 *
 * Unit tests for the MailSendingException.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Infrastructure\Exception
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * @covers \Shared\Infrastructure\Exception\MailSendingException
 */
#[CoversClass(className: MailSendingException::class)]
final class MailSendingExceptionTest extends TestCase
{
  /**
   * Test the dispatchFailed factory method.
   */
  #[Test]
  public function testDispatchFailed(): void
  {
    $previous = new Exception('Previous error');
    $exception = MailSendingException::dispatchFailed('Subject', $previous);

    $this->assertSame('Failed to send email with subject "Subject".', $exception->getMessage());
    $this->assertSame($previous, $exception->getPrevious());
  }
}

