<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Idempotency;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Http\HttpIdempotencyOperation;
use Shared\Application\Contract\Idempotency\IdempotencyDeniedException;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Infrastructure\Idempotency\HttpIdempotencyResultCodec;

/**
 * Class HttpIdempotencyResultCodecTest
 *
 * Pins typed immutable replay, enum/date/array semantics and precommit constructor compatibility.
 *
 * @category Adapter Tests
 */
#[CoversClass(HttpIdempotencyResultCodec::class)]
final class HttpIdempotencyResultCodecTest extends TestCase
{
  #[Test]
  public function immutableOwnerContractsRoundTripWithDateTimezoneEnumsAndOrderedKeys(): void
  {
    $operation = $this->operation(CodecTestResult::class, [CodecTestView::class, CodecTestStatus::class]);
    $result = new CodecTestResult(new CodecTestView('id', CodecTestStatus::READY), new DateTimeImmutable('2030-02-01T12:30:10.123456 Europe/Paris'), ['first' => null, 5 => true, 'last' => [1.0, '1']]);
    $codec = new HttpIdempotencyResultCodec();
    $body = $codec->encode($result, $operation);
    self::assertEquals($result, $codec->decode($body, $operation));
    $bundle = $codec->withProjection($body, '{"version":1,"queries":[]}');
    self::assertSame('{"version":1,"queries":[]}', $codec->projection($bundle));
    self::assertEquals($result, $codec->decode($bundle, $operation));
  }

  #[Test]
  public function undeclaredNestedTypesCannotBeCapturedBeforeCommit(): void
  {
    $operation = $this->operation(CodecTestResult::class);
    $result = new CodecTestResult(new CodecTestView('id', CodecTestStatus::READY), new DateTimeImmutable(), []);
    $this->expectException(IdempotencyDeniedException::class);
    new HttpIdempotencyResultCodec()->encode($result, $operation);
  }

  #[Test]
  public function readonlyComputedPropertiesWithIncompatibleConstructorsFailBeforeCommit(): void
  {
    $operation = $this->operation(CodecComputedResult::class);
    $this->expectException(IdempotencyDeniedException::class);
    $this->expectExceptionMessage('result_unavailable');
    new HttpIdempotencyResultCodec()->encode(new CodecComputedResult('input'), $operation);
  }

  #[Test]
  public function undeclaredClassesAndUnsupportedVersionsCannotBeConstructedOnReplay(): void
  {
    $codec = new HttpIdempotencyResultCodec();
    foreach (['{"version":2,"result":null}', '{"version":1,"result":{"kind":"object","class":"stdClass","fields":{}}}', '{"version":1,"result":{"kind":"array","entries":[[1,"a"],[1,"b"]]}}', '{broken'] as $body) {
      try {
        $codec->decode($body, $this->operation(CodecComputedResult::class));
        self::fail('Malformed or undeclared committed results must fail closed.');
      } catch (IdempotencyDeniedException $failure) {
        self::assertSame('result_unavailable', $failure->reason);
      }
    }
  }

  /**
   * @param class-string<ResultMessage> $resultClass
   * @param list<class-string> $contracts
   */
  private function operation(string $resultClass, array $contracts = []): HttpIdempotencyOperation
  {
    return new HttpIdempotencyOperation('codec', 'main', 'bus_owner', CodecTestCommand::class, $resultClass, $contracts);
  }
}

final readonly class CodecTestCommand implements CommandMessage
{
}

enum CodecTestStatus: string
{
  case READY = 'ready';
}

final readonly class CodecTestView
{
  public function __construct(public string $id, public CodecTestStatus $status)
  {
  }
}

final readonly class CodecTestResult implements ResultMessage
{
  /**
   * @param array<int|string, mixed> $values
   */
  public function __construct(public CodecTestView $view, public DateTimeImmutable $date, public array $values)
  {
  }
}

final readonly class CodecComputedResult implements ResultMessage
{
  public string $computed;

  public function __construct(string $input)
  {
    $this->computed = $input . '!';
  }
}
