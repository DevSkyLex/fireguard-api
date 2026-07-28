<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Message\EditMessage;

use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\UseCase\Command\Message\EditMessage\EditMessageResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test EditMessageResultTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EditMessageResult::class)]
final class EditMessageResultTest extends TestCase
{
  #[Test]
  public function testItCarriesTheEditedViewAndTheActingMemberId(): void
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $view = new MessageView('message-1', 'conversation-1', 'org-1', 'member-1', 'Edited body', [], null, null, null, $now, $now);

    $result = new EditMessageResult($view, 'member-1');

    self::assertSame($view, $result->message);
    self::assertSame('member-1', $result->currentMemberId);
  }
}
