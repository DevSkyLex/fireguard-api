<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\UseCase\Query\Thread\ListAssistantThreads;

use Assistant\Application\Port\Outbound\AssistantThreadRepositoryPort;
use Assistant\Application\UseCase\Query\Thread\ListAssistantThreads\{ListAssistantThreadsHandler, ListAssistantThreadsQuery};
use Assistant\Domain\Model\Thread\AssistantThread;
use Assistant\Domain\ValueObject\AssistantThreadId;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListAssistantThreadsHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListAssistantThreadsHandler::class)]
final class ListAssistantThreadsHandlerTest extends TestCase
{
  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c03';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c04';

  #[Test]
  public function testInvokeThrowsWhenPermissionMissing(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(
      OrganizationAccessDeniedException::missingPermission('organization.assistant.use'),
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler = new ListAssistantThreadsHandler($this->createStub(AssistantThreadRepositoryPort::class), $authorization);

    $handler(new ListAssistantThreadsQuery(self::ORG_ID, self::USER_ID));
  }

  #[Test]
  public function testInvokeListsOnlyTheRequestingUsersOwnThreads(): void
  {
    $thread = AssistantThread::start(
      id: AssistantThreadId::fromString('018f0b68-6758-7a12-8a1d-3f0d97f64c05'),
      organizationId: self::ORG_ID,
      memberId: self::USER_ID,
      title: 'Fire safety questions',
      now: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );

    $threads = $this->createMock(AssistantThreadRepositoryPort::class);
    $threads->expects(self::once())
      ->method('listByOrganizationAndMember')
      ->with(self::ORG_ID, self::USER_ID, 30, 0)
      ->willReturn([$thread]);
    $threads->method('countByOrganizationAndMember')->with(self::ORG_ID, self::USER_ID)->willReturn(1);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $handler = new ListAssistantThreadsHandler($threads, $authorization);

    $result = $handler(new ListAssistantThreadsQuery(self::ORG_ID, self::USER_ID));

    self::assertCount(1, $result->items);
    self::assertSame(self::USER_ID, $result->items[0]->memberId);
    self::assertSame(1, $result->total);
  }
}
