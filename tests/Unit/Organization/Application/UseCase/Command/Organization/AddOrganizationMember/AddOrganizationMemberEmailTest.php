<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\AddOrganizationMember;

use DateTimeImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Notification\Application\Contract\Notification\NotificationType;
use Notification\Application\Port\Outbound\{MercureNotificationChannelPort, NotificationPreferenceRepositoryPort, NotificationRepositoryPort, RecipientDirectoryPort};
use Notification\Application\Service\NotificationService;
use Notification\Application\UseCase\Command\Notification\SendNotification\SendNotificationHandler;
use Notification\Domain\Model\Notification\Notification;
use Notification\Domain\ValueObject\NotificationId;
use Notification\Infrastructure\Adapter\Channel\EmailNotificationChannelAdapter;
use Organization\Application\Contract\Member\OrganizationMemberGrant;
use Organization\Application\Contract\Quota\OrganizationQuotaResource;
use Organization\Application\Port\Inbound\{OrganizationMemberGrantGuardPort, OrganizationQuotaPort};
use Organization\Application\Port\Outbound\{OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Command\Organization\AddOrganizationMember\{AddOrganizationMemberCommand, AddOrganizationMemberHandler};
use Organization\Domain\Model\Organization\{Organization, RestoredOrganizationCore};
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort, MailerPort, TransactionManagerPort};
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Translator;
use Tests\Support\Factory\UserTestFactory;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use User\Application\Port\Outbound\UserRepositoryPort;

use function dirname;

use const LIBXML_NOERROR;
use const LIBXML_NOWARNING;

#[CoversClass(AddOrganizationMemberHandler::class)]
#[CoversClass(NotificationService::class)]
#[CoversClass(SendNotificationHandler::class)]
#[CoversClass(EmailNotificationChannelAdapter::class)]
final class AddOrganizationMemberEmailTest extends TestCase
{
  #[Test]
  #[DataProvider('membershipChanges')]
  public function organizationMarkupIsTextInRenderedMembershipEmail(bool $reactivating): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655440100';
    $memberId = '550e8400-e29b-41d4-a716-446655440101';
    $userId = '550e8400-e29b-41d4-a716-446655440102';
    $roleId = '550e8400-e29b-41d4-a716-446655440103';
    $name = '<a href="https://untrusted.invalid">Review & "confirm"</a>';
    $plainBody = 'You now have access to ' . $name . '.';
    $organization = Organization::reconstitute(core: new RestoredOrganizationCore(
      id: new OrganizationId($organizationId),
      name: new OrganizationName($name),
      createdByUserId: $userId,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    ));
    $role = OrganizationRole::reconstitute(
      id: new OrganizationRoleId($roleId),
      organizationId: new OrganizationId($organizationId),
      name: new OrganizationRoleName('member'),
      permissions: ['organization.read'],
      isSystem: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );
    $inactiveMember = $reactivating ? OrganizationMember::reconstitute(
      id: new OrganizationMemberId($memberId),
      organizationId: new OrganizationId($organizationId),
      userId: $userId,
      isActive: false,
      joinedAt: new DateTimeImmutable('-5 days'),
    ) : null;

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())->method('findById')
      ->willReturn(UserTestFactory::createActive($userId, 'member@example.com'));
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())->method('findByIdsInOrganization')->willReturn([$role]);
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())->method('findByOrganizationAndUser')->willReturn($inactiveMember);
    $memberRepository->expects(self::once())->method('save');
    $memberRepository->expects(self::once())->method('assignRole');
    $memberRepository->expects(self::exactly($reactivating ? 2 : 1))->method('findRoleIdsForMember')->willReturn([$roleId]);
    $grantGuard = $this->createMock(OrganizationMemberGrantGuardPort::class);
    $grantGuard->expects(self::exactly($reactivating ? 2 : 1))->method('assertCanGrant')
      ->with(self::isInstanceOf(OrganizationMemberGrant::class), $organizationId, 'member@example.com', [$roleId]);
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::once())->method('assertCanAdd')->with($organizationId, OrganizationQuotaResource::MEMBERS);
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())->method('transactional')
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');
    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturnMap([
      [OrganizationMemberId::class, new OrganizationMemberId($memberId)],
      [NotificationId::class, new NotificationId('550e8400-e29b-41d4-a716-446655449010')],
    ]);
    $warnings = [];
    $logger = $this->createStub(LoggerPort::class);
    $logger->method('warning')->willReturnCallback(static function (string $message, array $context) use (&$warnings): void {
      $warnings[] = [$message, $context];
    });

    $notificationRepository = $this->createMock(NotificationRepositoryPort::class);
    $notificationRepository->expects(self::once())->method('save')
      ->with(self::callback(static fn (Notification $notification): bool => NotificationType::ORGANIZATION_MEMBER_ADDED === $notification->type()
        && $plainBody === $notification->body()
        && $name === $notification->payload()['organizationName']));
    $preferences = $this->createMock(NotificationPreferenceRepositoryPort::class);
    $preferences->expects(self::once())->method('findByUserIdAndCategory')
      ->with($userId, 'organization')->willReturn(null);
    $mercure = $this->createMock(MercureNotificationChannelPort::class);
    $mercure->expects(self::once())->method('publish')
      ->with(self::callback(static fn (Notification $notification): bool => $plainBody === $notification->body()), []);
    $renderedHtml = null;
    $mailer = $this->createMock(MailerPort::class);
    $mailer->expects(self::once())->method('send')
      ->with(['member@example.com'], 'You have been added to ' . $name, self::isString())
      ->willReturnCallback(static function (array $to, string $subject, string $body) use (&$renderedHtml): void {
        $renderedHtml = $body;
      });
    $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 8) . '/templates'), ['autoescape' => 'html']);
    $twig->addExtension(new TranslationExtension(new Translator('en')));
    $notificationService = new NotificationService(new SendNotificationHandler(
      notificationRepository: $notificationRepository,
      preferenceRepository: $preferences,
      emailChannel: new EmailNotificationChannelAdapter($mailer, $twig),
      mercureChannel: $mercure,
      recipientDirectory: $this->createStub(RecipientDirectoryPort::class),
      logger: $logger,
      uuidFactory: $uuidFactory,
    ));
    $handler = new AddOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      userRepository: $userRepository,
      notificationPort: $notificationService,
      logger: $logger,
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
      quota: $quota,
      eventDispatcher: $eventDispatcher,
      grantGuard: $grantGuard,
    );

    $result = $handler(new AddOrganizationMemberCommand(
      organizationId: $organizationId,
      userId: $userId,
      grant: OrganizationMemberGrant::forActor('actor'),
      roleIds: [$roleId],
    ));

    self::assertTrue($result->isActive);
    self::assertTrue($result->wasCreatedOrReactivated);
    self::assertSame([], $warnings);
    self::assertIsString($renderedHtml);
    self::assertStringContainsString('&lt;a href=&quot;https://untrusted.invalid&quot;&gt;Review &amp; &quot;confirm&quot;&lt;/a&gt;', $renderedHtml);
    $document = new DOMDocument();
    self::assertTrue($document->loadHTML($renderedHtml, LIBXML_NOERROR | LIBXML_NOWARNING));
    $bodyCells = new DOMXPath($document)->query('//td[@class="prose"]');
    self::assertNotFalse($bodyCells);
    self::assertCount(1, $bodyCells);
    $bodyCell = $bodyCells->item(0);
    self::assertInstanceOf(DOMElement::class, $bodyCell);
    self::assertSame($plainBody, $bodyCell->textContent);
    $elements = new DOMXPath($document)->query('.//*', $bodyCell);
    self::assertNotFalse($elements);
    self::assertCount(0, $elements);
  }

  /**
   * @return iterable<string, array{bool}>
   */
  public static function membershipChanges(): iterable
  {
    yield 'new member' => [false];
    yield 'reactivated member' => [true];
  }
}
