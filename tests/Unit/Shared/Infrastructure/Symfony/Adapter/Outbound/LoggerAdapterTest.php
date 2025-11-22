<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Symfony\Adapter\Outbound;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Shared\Application\Log\LogLevel;
use Shared\Infrastructure\Symfony\Adapter\Outbound\LoggerAdapter;

#[CoversClass(className: LoggerAdapter::class)]
final class LoggerAdapterTest extends TestCase
{
  private LoggerInterface&MockObject $psrLogger;
  private LoggerAdapter $adapter;

  protected function setUp(): void
  {
    $this->psrLogger = $this->createMock(LoggerInterface::class);
    $this->adapter = new LoggerAdapter($this->psrLogger);
  }

  #[Test]
  public function testLog(): void
  {
    $level = LogLevel::INFO;
    $message = 'test message';
    $context = ['key' => 'value'];

    $this->psrLogger->expects($this->once())
      ->method('log')
      ->with($level->value, $message, $context);

    $this->adapter->log($level, $message, $context);
  }

  #[Test]
  public function testCritical(): void
  {
    $message = 'critical error';
    $context = ['key' => 'value'];

    $this->psrLogger->expects($this->once())
      ->method('critical')
      ->with($message, $context);

    $this->adapter->critical($message, $context);
  }

  #[Test]
  public function testError(): void
  {
    $message = 'error message';
    $context = ['key' => 'value'];

    $this->psrLogger->expects($this->once())
      ->method('error')
      ->with($message, $context);

    $this->adapter->error($message, $context);
  }

  #[Test]
  public function testWarning(): void
  {
    $message = 'warning message';
    $context = ['key' => 'value'];

    $this->psrLogger->expects($this->once())
      ->method('warning')
      ->with($message, $context);

    $this->adapter->warning($message, $context);
  }

  #[Test]
  public function testNotice(): void
  {
    $message = 'notice message';
    $context = ['key' => 'value'];

    $this->psrLogger->expects($this->once())
      ->method('notice')
      ->with($message, $context);

    $this->adapter->notice($message, $context);
  }

  #[Test]
  public function testInfo(): void
  {
    $message = 'info message';
    $context = ['key' => 'value'];

    $this->psrLogger->expects($this->once())
      ->method('info')
      ->with($message, $context);

    $this->adapter->info($message, $context);
  }

  #[Test]
  public function testDebug(): void
  {
    $message = 'debug message';
    $context = ['key' => 'value'];

    $this->psrLogger->expects($this->once())
      ->method('debug')
      ->with($message, $context);

    $this->adapter->debug($message, $context);
  }
}

