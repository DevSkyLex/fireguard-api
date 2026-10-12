<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Command\Conversation\GetOrCreateConversation;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use Messaging\Application\Contract\Subject\MessagingSubjectResolution;
use Messaging\Application\Port\Outbound\Internal\MessagingConversationRepositoryPort;
use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort, MessagingSubjectResolverPort};
use Messaging\Application\Service\{MessagingAccessPolicy, MessagingSubjectResolverRegistry};
use Messaging\Application\UseCase\Command\Conversation\GetOrCreateConversation\{GetOrCreateConversationCommand, GetOrCreateConversationHandler};
use Messaging\Domain\Exception\{MessagingSubjectNotFoundException, MessagingValidationException};
use Organization\Application\Contract\Exception\OrganizationAccessDeniedException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetOrCreateConversationHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetOrCreateConversationHandler::class)]
final class GetOrCreateConversationHandlerTest extends TestCase
{
  private const string ORG_ID = 'org-1';

  private const string USER_ID = 'user-1';

  #[Test]
  public function testInvokeIsIdempotentAndReturnsTheSameConversationTwice(): void
  {
    $view = $this->view();

    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver(exists: true, label: 'Site nord')]);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions');
    $accessPolicy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class));

    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->expects(self::exactly(2))->method('getOrCreate')->willReturn($view);

    $handler = new GetOrCreateConversationHandler($registry, $accessPolicy, $conversations);

    $command = new GetOrCreateConversationCommand(self::USER_ID, self::ORG_ID, 'facility', 'facility-1');
    $first = $handler->__invoke($command);
    $second = $handler->__invoke($command);

    self::assertSame($view->id, $first->conversation->id);
    self::assertSame($first->conversation->id, $second->conversation->id);
    self::assertSame('Site nord', $first->subjectLabel);
  }

  #[Test]
  public function testInvokeThrowsOnAnUnsupportedSubjectType(): void
  {
    $registry = new MessagingSubjectResolverRegistry([]);
    $accessPolicy = new MessagingAccessPolicy(
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->createStub(MessagingMemberDirectoryPort::class),
      $this->createStub(MessagingParticipantRepositoryPort::class),
    );
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);

    $handler = new GetOrCreateConversationHandler($registry, $accessPolicy, $conversations);

    $this->expectException(MessagingValidationException::class);

    $handler->__invoke(new GetOrCreateConversationCommand(self::USER_ID, self::ORG_ID, 'not-a-type', 'subject-1'));
  }

  #[Test]
  public function testInvokeThrowsWhenTheSubjectDoesNotExist(): void
  {
    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver(exists: false)]);
    $accessPolicy = new MessagingAccessPolicy(
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->createStub(MessagingMemberDirectoryPort::class),
      $this->createStub(MessagingParticipantRepositoryPort::class),
    );
    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);

    $handler = new GetOrCreateConversationHandler($registry, $accessPolicy, $conversations);

    $this->expectException(MessagingSubjectNotFoundException::class);

    $handler->__invoke(new GetOrCreateConversationCommand(self::USER_ID, self::ORG_ID, 'facility', 'facility-1'));
  }

  #[Test]
  public function testInvokeThrowsWhenTheSubjectReadPermissionIsMissing(): void
  {
    $registry = new MessagingSubjectResolverRegistry([$this->facilityResolver(exists: true, label: 'Site nord')]);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(new OrganizationAccessDeniedException('Missing permission.'));
    $accessPolicy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class));

    $conversations = $this->createStub(MessagingConversationRepositoryPort::class);

    $handler = new GetOrCreateConversationHandler($registry, $accessPolicy, $conversations);

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new GetOrCreateConversationCommand(self::USER_ID, self::ORG_ID, 'facility', 'facility-1'));
  }

  #[Test]
  public function testUnknownWellFormedSubjectIsRejectedBeforeAnyPersistence(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('assertGrantedPermissions');
    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->expects(self::never())->method('getOrCreate');
    $handler = new GetOrCreateConversationHandler(
      new MessagingSubjectResolverRegistry([]),
      new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
      $conversations,
    );

    $this->expectException(MessagingSubjectNotFoundException::class);
    $handler->__invoke(new GetOrCreateConversationCommand(self::USER_ID, self::ORG_ID, 'service_request', 'request-1'));
  }

  #[Test]
  public function testExtraSubjectContributorReusesTheExistingConversationFlow(): void
  {
    $resolver = $this->createStub(MessagingSubjectResolverPort::class);
    $resolver->method('descriptor')->willReturn(new \Messaging\Application\Contract\Subject\MessagingSubjectDescriptor('service_request', 'Service request', 'service-requests', 'organization.service_requests.read'));
    $resolver->method('resolve')->willReturn(new MessagingSubjectResolution(true, 'Request #1', 'organization.service_requests.read'));
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())->method('assertGrantedPermissions')->with(self::USER_ID, self::ORG_ID, ['organization.service_requests.read', 'organization.messaging.read']);
    $conversations = $this->createMock(MessagingConversationRepositoryPort::class);
    $conversations->expects(self::once())->method('getOrCreate')->with(self::ORG_ID, \Messaging\Domain\ValueObject\MessagingSubjectType::from('service_request'), 'request-1', \Messaging\Domain\ValueObject\ConversationVisibility::SUBJECT)->willReturn($this->view());
    $handler = new GetOrCreateConversationHandler(
      new MessagingSubjectResolverRegistry([$resolver]),
      new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class)),
      $conversations,
    );

    self::assertSame('Request #1', $handler->__invoke(new GetOrCreateConversationCommand(self::USER_ID, self::ORG_ID, 'service_request', 'request-1'))->subjectLabel);
  }

  private function facilityResolver(bool $exists, ?string $label = null): MessagingSubjectResolverPort
  {
    $resolver = $this->createStub(MessagingSubjectResolverPort::class);
    $resolver->method('descriptor')->willReturn(new \Messaging\Application\Contract\Subject\MessagingSubjectDescriptor('facility', 'Facility', 'facilities', 'organization.facilities.read'));
    $resolver->method('resolve')->willReturn(new MessagingSubjectResolution($exists, $label, 'organization.facilities.read'));

    return $resolver;
  }

  private function view(): ConversationView
  {
    return new ConversationView(
      'conversation-1',
      self::ORG_ID,
      'facility',
      'facility-1',
      'subject',
      null,
      0,
      false,
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }
}
