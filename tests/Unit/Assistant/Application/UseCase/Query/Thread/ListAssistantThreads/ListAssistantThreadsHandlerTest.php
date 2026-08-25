<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\UseCase\Query\Thread\ListAssistantThreads;

use Assistant\Application\Port\Outbound\AssistantThreadRepositoryPort;
use Assistant\Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort;
use Assistant\Application\Service\AssistantAccessPolicy;
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
    $accessPolicy = $this->enabledAccessPolicy($authorization);

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler = new ListAssistantThreadsHandler($this->createStub(AssistantThreadRepositoryPort::class), $accessPolicy);

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

    $accessPolicy = $this->enabledAccessPolicy();

    $handler = new ListAssistantThreadsHandler($threads, $accessPolicy);

    $result = $handler(new ListAssistantThreadsQuery(self::ORG_ID, self::USER_ID));

    self::assertCount(1, $result->items);
    self::assertSame(self::USER_ID, $result->items[0]->memberId);
    self::assertSame(1, $result->total);
  }

  /**
   * Builds the real policy over doubled ports, with the organization toggle on.
   */
  private function enabledAccessPolicy(
    ?OrganizationAuthorizationPort $authorization = null,
    bool $assistantEnabled = true,
  ): AssistantAccessPolicy {
    $settings = $this->createStub(AssistantOrganizationSettingsPort::class);
    $settings->method('isEnabledFor')->willReturn($assistantEnabled);

    return new AssistantAccessPolicy(
      $authorization ?? $this->createStub(OrganizationAuthorizationPort::class),
      $settings,
    );
  }
}
