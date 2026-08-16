<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Provider\Attachment;

use ApiPlatform\Metadata\{Get, GetCollection};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Application\UseCase\Query\Attachment\ListFacilityAttachments\ListFacilityAttachmentsResult;
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityAttachmentRecord, FacilityRecord};
use Facility\Presentation\Api\Dto\Output\Attachment\FacilityAttachmentOutput;
use Facility\Presentation\Api\Provider\Attachment\FacilityMediaProvider;
use InvalidArgumentException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

#[CoversClass(FacilityMediaProvider::class)]
final class FacilityMediaProviderTest extends TestCase
{
  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testProvideListsAttachmentsWhenAuthorized(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($facility);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ListFacilityAttachmentsResult([
      ['id' => 'attachment-id', 'fileName' => 'photo.jpg', 'mimeType' => 'image/jpeg', 'size' => 5, 'label' => null, 'uploadedAt' => '2026-01-01T00:00:00+00:00', 'kind' => 'document', 'isPrimaryPlan' => false, 'imageWidth' => null, 'imageHeight' => null],
    ]));

    $result = new FacilityMediaProvider($entityManager, $queryBus, $authorization, $security, new RequestStack())
      ->provide(new GetCollection(), ['facilityId' => self::FACILITY_ID]);

    self::assertIsArray($result);
    self::assertCount(1, $result);
    self::assertInstanceOf(FacilityAttachmentOutput::class, $result[0]);
    self::assertSame('attachment-id', $result[0]->id);
    self::assertSame('document', $result[0]->kind);
  }

  #[Test]
  public function testProvideListThrowsAccessDeniedWithoutPermission(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($facility);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $queryBus = $this->createStub(QueryBusPort::class);

    $this->expectException(AccessDeniedHttpException::class);

    new FacilityMediaProvider($entityManager, $queryBus, $authorization, $security, new RequestStack())
      ->provide(new GetCollection(), ['facilityId' => self::FACILITY_ID]);
  }

  #[Test]
  public function testProvideListThrowsNotFoundWhenOrganizationOutsideCallerScope(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($facility);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $queryBus = $this->createStub(QueryBusPort::class);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Facility not found.');

    new FacilityMediaProvider($entityManager, $queryBus, $authorization, $security, new RequestStack())
      ->provide(new GetCollection(), ['facilityId' => self::FACILITY_ID]);
  }

  #[Test]
  public function testProvideGetOneReturnsOutput(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;
    $attachment = new FacilityAttachmentRecord();
    $attachment->id = 'attachment-id';
    $attachment->facility = $facility;
    $attachment->fileName = 'photo.jpg';
    $attachment->mimeType = 'image/jpeg';
    $attachment->size = 5;
    $attachment->uploadedAt = new DateTimeImmutable();

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($attachment);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $queryBus = $this->createStub(QueryBusPort::class);

    $result = new FacilityMediaProvider($entityManager, $queryBus, $authorization, $security, new RequestStack())
      ->provide(new Get(), ['id' => 'attachment-id']);

    self::assertInstanceOf(FacilityAttachmentOutput::class, $result);
    self::assertSame('attachment-id', $result->id);
  }

  #[Test]
  public function testProvideGetOneThrowsNotFoundWhenMissing(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn(null);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $security = $this->createStub(Security::class);
    $queryBus = $this->createStub(QueryBusPort::class);

    $this->expectException(NotFoundHttpException::class);

    new FacilityMediaProvider($entityManager, $queryBus, $authorization, $security, new RequestStack())
      ->provide(new Get(), ['id' => 'missing-id']);
  }

  #[Test]
  public function testOutputThrowsWhenAttachmentHasNoFacility(): void
  {
    $attachment = new FacilityAttachmentRecord();
    $attachment->id = 'orphan-attachment';

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Attachment facility not found.');

    FacilityMediaProvider::output($attachment);
  }

  #[Test]
  public function testProvideListRejectsBlankFacilityId(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('The facilityId URI parameter is required.');

    $this->provider()->provide(new GetCollection(), ['facilityId' => '']);
  }

  #[Test]
  public function testProvideListRequiresAnAuthenticatedSecurityUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $provider = new FacilityMediaProvider(
      $this->createStub(EntityManagerInterface::class),
      $this->createStub(QueryBusPort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
      $security,
      new RequestStack(),
    );

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $provider->provide(new GetCollection(), ['facilityId' => self::FACILITY_ID]);
  }

  #[Test]
  public function testProvideListThrowsNotFoundWhenFacilityIsMissing(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Facility not found.');

    $this->provider(found: null)->provide(new GetCollection(), ['facilityId' => self::FACILITY_ID]);
  }

  #[Test]
  public function testProvideListMapsDirectNotFoundToHttp404(): void
  {
    $provider = $this->provider(exception: FacilityNotFoundException::withId(self::FACILITY_ID));

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new GetCollection(), ['facilityId' => self::FACILITY_ID]);
  }

  #[Test]
  public function testProvideListMapsDirectInvalidArgumentToHttp400(): void
  {
    $provider = $this->provider(exception: new InvalidArgumentException('Malformed identifier.'));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Malformed identifier.');

    $provider->provide(new GetCollection(), ['facilityId' => self::FACILITY_ID]);
  }

  #[Test]
  public function testProvideListUnwrapsWrappedNotFound(): void
  {
    $provider = $this->provider(exception: MessengerRuntimeException::wrap(
      FacilityNotFoundException::withId(self::FACILITY_ID),
    ));

    $this->expectException(NotFoundHttpException::class);

    $provider->provide(new GetCollection(), ['facilityId' => self::FACILITY_ID]);
  }

  #[Test]
  public function testProvideListUnwrapsWrappedInvalidArgument(): void
  {
    $provider = $this->provider(exception: MessengerRuntimeException::wrap(
      new InvalidArgumentException('Wrapped invalid argument.'),
    ));

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Wrapped invalid argument.');

    $provider->provide(new GetCollection(), ['facilityId' => self::FACILITY_ID]);
  }

  #[Test]
  public function testProvideListRethrowsUnrecognisedMessengerFailure(): void
  {
    $provider = $this->provider(exception: MessengerRuntimeException::wrap(new RuntimeException('infrastructure down')));

    $this->expectException(MessengerRuntimeException::class);
    $this->expectExceptionMessage('infrastructure down');

    $provider->provide(new GetCollection(), ['facilityId' => self::FACILITY_ID]);
  }

  #[Test]
  public function testProvideGetOneThrowsNotFoundWhenIdentifierIsNotAString(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Attachment not found.');

    $this->provider()->provide(new Get(), ['id' => 42]);
  }

  #[Test]
  public function testProvideGetOneThrowsAccessDeniedWithoutPermission(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;
    $attachment = new FacilityAttachmentRecord();
    $attachment->id = 'attachment-id';
    $attachment->facility = $facility;

    $provider = $this->provider(found: $attachment, decision: OrganizationAccessDecision::MISSING_PERMISSION);

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.facilities.read permission.');

    $provider->provide(new Get(), ['id' => 'attachment-id']);
  }

  #[Test]
  public function testProvideGetOneThrowsNotFoundWhenOrganizationOutsideCallerScope(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $facility = new FacilityRecord();
    $facility->id = self::FACILITY_ID;
    $facility->organization = $organization;
    $attachment = new FacilityAttachmentRecord();
    $attachment->id = 'attachment-id';
    $attachment->facility = $facility;

    $provider = $this->provider(found: $attachment, decision: OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Attachment not found.');

    $provider->provide(new Get(), ['id' => 'attachment-id']);
  }

  private function provider(
    ?Throwable $exception = null,
    OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED,
    object|false|null $found = false,
  ): FacilityMediaProvider {
    if (false === $found) {
      $organization = new OrganizationRecord();
      $organization->id = self::ORGANIZATION_ID;
      $found = new FacilityRecord();
      $found->id = self::FACILITY_ID;
      $found->organization = $organization;
    }

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($found);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn($decision);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $queryBus = $this->createStub(QueryBusPort::class);

    if (null !== $exception) {
      $queryBus->method('ask')->willThrowException($exception);
    }

    return new FacilityMediaProvider($entityManager, $queryBus, $authorization, $security, new RequestStack());
  }
}
