<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Provider\Attachment;

use ApiPlatform\Metadata\{Get, GetCollection};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Application\UseCase\Query\Attachment\ListInterventionAttachments\ListInterventionAttachmentsResult;
use Intervention\Domain\Exception\InterventionAccessDeniedException;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionAttachmentRecord, InterventionRecord};
use Intervention\Presentation\Api\Dto\Output\Attachment\InterventionAttachmentOutput;
use Intervention\Presentation\Api\Provider\Attachment\InterventionMediaProvider;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

/**
 * Test InterventionMediaProviderTest.
 *
 * The collection route (`list()`) makes NO authorization decision of its
 * own — the flat `organization.interventions.read` permission is enforced
 * authoritatively inside `ListInterventionAttachmentsHandler` (see
 * `tests/Architecture/Unit/InterventionAuthorizationEnforcementTest`); this
 * test only asserts the exception -> HTTP mapping. The single-item route
 * (`getOne()`) has no backing handler and remains the sole enforcement point
 * for that path.
 *
 * @category Provider Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionMediaProvider::class)]
final class InterventionMediaProviderTest extends TestCase
{
  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440003';

  #[Test]
  public function testProvideListsAttachments(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ListInterventionAttachmentsResult([
      ['id' => 'attachment-id', 'fileName' => 'evidence.jpg', 'mimeType' => 'image/jpeg', 'size' => 5, 'label' => null, 'uploadedAt' => '2026-01-01T00:00:00+00:00'],
    ]));

    $result = new InterventionMediaProvider(
      $this->createStub(EntityManagerInterface::class),
      $queryBus,
      $this->createStub(OrganizationAuthorizationPort::class),
      $security,
    )->provide(new GetCollection(), ['interventionId' => self::INTERVENTION_ID]);

    self::assertIsArray($result);
    self::assertCount(1, $result);
    self::assertInstanceOf(InterventionAttachmentOutput::class, $result[0]);
    self::assertSame('attachment-id', $result[0]->id);
  }

  #[Test]
  public function testProvideListMapsAccessDeniedExceptionFromTheHandlerTo403(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willThrowException(
      new InterventionAccessDeniedException('Missing organization.interventions.read permission.'),
    );

    $this->expectException(AccessDeniedHttpException::class);

    new InterventionMediaProvider(
      $this->createStub(EntityManagerInterface::class),
      $queryBus,
      $this->createStub(OrganizationAuthorizationPort::class),
      $security,
    )->provide(new GetCollection(), ['interventionId' => self::INTERVENTION_ID]);
  }

  #[Test]
  public function testProvideGetOneReturnsOutput(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $intervention = new InterventionRecord();
    $intervention->id = self::INTERVENTION_ID;
    $intervention->organization = $organization;
    $attachment = new InterventionAttachmentRecord();
    $attachment->id = 'attachment-id';
    $attachment->intervention = $intervention;
    $attachment->fileName = 'evidence.jpg';
    $attachment->mimeType = 'image/jpeg';
    $attachment->size = 5;
    $attachment->uploadedAt = new DateTimeImmutable();

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($attachment);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $result = new InterventionMediaProvider(
      $entityManager,
      $this->createStub(QueryBusPort::class),
      $authorization,
      $security,
    )->provide(new Get(), ['id' => 'attachment-id']);

    self::assertInstanceOf(InterventionAttachmentOutput::class, $result);
    self::assertSame('attachment-id', $result->id);
  }

  #[Test]
  public function testProvideGetOneThrowsAccessDeniedWithoutReadPermission(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $intervention = new InterventionRecord();
    $intervention->id = self::INTERVENTION_ID;
    $intervention->organization = $organization;
    $attachment = new InterventionAttachmentRecord();
    $attachment->id = 'attachment-id';
    $attachment->intervention = $intervention;

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($attachment);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );

    $this->expectException(AccessDeniedHttpException::class);

    new InterventionMediaProvider(
      $entityManager,
      $this->createStub(QueryBusPort::class),
      $authorization,
      $security,
    )->provide(new Get(), ['id' => 'attachment-id']);
  }

  #[Test]
  public function testProvideGetOneThrowsNotFoundWhenMissing(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn(null);

    $this->expectException(NotFoundHttpException::class);

    new InterventionMediaProvider(
      $entityManager,
      $this->createStub(QueryBusPort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->createStub(Security::class),
    )->provide(new Get(), ['id' => 'missing-id']);
  }

  #[Test]
  public function testProvideGetOneThrowsNotFoundWhenTheIdUriVariableIsAbsent(): void
  {
    $this->expectException(NotFoundHttpException::class);

    new InterventionMediaProvider(
      $this->createStub(EntityManagerInterface::class),
      $this->createStub(QueryBusPort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->createStub(Security::class),
    )->provide(new Get(), []);
  }

  #[Test]
  public function testProvideListRejectsAnEmptyInterventionIdUriVariable(): void
  {
    $this->expectException(BadRequestHttpException::class);

    new InterventionMediaProvider(
      $this->createStub(EntityManagerInterface::class),
      $this->createStub(QueryBusPort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->createStub(Security::class),
    )->provide(new GetCollection(), ['interventionId' => '']);
  }

  #[Test]
  public function testProvideListRejectsAnUnauthenticatedUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $this->expectException(AccessDeniedHttpException::class);

    new InterventionMediaProvider(
      $this->createStub(EntityManagerInterface::class),
      $this->createStub(QueryBusPort::class),
      $this->createStub(OrganizationAuthorizationPort::class),
      $security,
    )->provide(new GetCollection(), ['interventionId' => self::INTERVENTION_ID]);
  }

  #[Test]
  public function testOutputThrowsNotFoundWhenTheRecordHasNoIntervention(): void
  {
    $attachment = new InterventionAttachmentRecord();
    $attachment->id = 'attachment-id';

    $this->expectException(NotFoundHttpException::class);

    InterventionMediaProvider::output($attachment);
  }
}
