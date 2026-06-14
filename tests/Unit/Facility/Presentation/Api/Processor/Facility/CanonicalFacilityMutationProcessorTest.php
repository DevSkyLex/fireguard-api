<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\Delete;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Facility\Presentation\Api\Dto\Input\Facility\PatchCanonicalFacilityInput;
use Facility\Presentation\Api\Processor\Facility\CanonicalFacilityMutationProcessor;
use Facility\Presentation\Api\Provider\Facility\CanonicalFacilityProvider;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionResourceManager;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Http\{MergePatchFields, RevisionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

#[CoversClass(CanonicalFacilityMutationProcessor::class)]
final class CanonicalFacilityMutationProcessorTest extends TestCase
{
  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440011';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440012';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440013';

  #[Test]
  public function testDeletingPublishedFacilityArchivesIt(): void
  {
    $record = $this->record();
    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('remove');
    $entityManager->expects(self::once())->method('flush');

    $result = $this->processor(
      $record,
      $this->request('DELETE'),
      $entityManager,
    )->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);

    self::assertNull($result);
    self::assertSame('archived', $record->status);
    self::assertSame(4, $record->revision);
  }

  private function processor(
    FacilityRecord $record,
    RequestStack $requestStack,
    ?EntityManagerInterface $entityManager = null,
  ): CanonicalFacilityMutationProcessor {
    $entityManager ??= $this->entityManager($record);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->user());
    $manager = new InterventionResourceManager($this->createStub(InterventionResourceGatewayPort::class));
    $provider = new CanonicalFacilityProvider(
      $entityManager,
      $authorization,
      $security,
      $requestStack,
      $manager,
    );

    return new CanonicalFacilityMutationProcessor(
      $entityManager,
      $authorization,
      $security,
      $requestStack,
      $provider,
      $manager,
      new RevisionGuard($requestStack),
      new MergePatchFields($requestStack),
    );
  }

  /**
   * @return EntityManagerInterface&MockObject
   */
  private function entityManager(FacilityRecord $record): EntityManagerInterface
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->with(FacilityRecord::class, self::FACILITY_ID)->willReturn($record);

    return $entityManager;
  }

  private function record(): FacilityRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $record = new FacilityRecord();
    $record->id = self::FACILITY_ID;
    $record->organization = $organization;
    $record->recordStatus = 'published';
    $record->revision = 3;
    $record->status = 'active';

    return $record;
  }

  private function request(string $method): RequestStack
  {
    $request = Request::create('/api/facilities/' . self::FACILITY_ID, $method);
    $request->headers->set('If-Match', '"revision-3"');
    $stack = new RequestStack();
    $stack->push($request);

    return $stack;
  }

  private function user(): SecurityUser
  {
    return new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true);
  }
}
