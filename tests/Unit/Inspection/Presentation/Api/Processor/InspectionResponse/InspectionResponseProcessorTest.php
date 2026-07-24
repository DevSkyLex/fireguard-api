<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Processor\InspectionResponse;

use ApiPlatform\Metadata\Put;
use Auth\Infrastructure\Security\User\SecurityUser;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionResponseRecord;
use Inspection\Presentation\Api\Dto\Input\InspectionResponse\CreateInspectionResponseInput;
use Inspection\Presentation\Api\Processor\InspectionResponse\InspectionResponseProcessor;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionResourceManager;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Presentation\Api\Http\ClientResourceAlreadyExistsHttpException;
use Shared\Presentation\Api\Http\{CreationPreconditionGuard, RevisionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

#[CoversClass(InspectionResponseProcessor::class)]
final class InspectionResponseProcessorTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string RESPONSE_ID = '550e8400-e29b-41d4-a716-446655440002';

  #[Test]
  public function testExistingClientUuidPutReturnsPreconditionFailed(): void
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $repository = $this->createStub(EntityRepository::class);
    $repository->method('findOneBy')->willReturn(new InspectionResponseRecord());
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->willReturnCallback(
      static fn (string $class): ?OrganizationRecord => OrganizationRecord::class === $class
        ? $organization
        : null,
    );
    $entityManager->method('getRepository')->willReturn($repository);
    $requestStack = new RequestStack();
    $request = Request::create('/api/inspection-responses/' . self::RESPONSE_ID, 'PUT');
    $request->headers->set('If-None-Match', '*');
    $requestStack->push($request);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(
      new SecurityUser('user-id', 'user@example.com', 'password', ['ROLE_USER'], [], true),
    );
    $input = new CreateInspectionResponseInput();
    $input->organization = '/api/organizations/' . self::ORGANIZATION_ID;
    $input->inspection = '/api/inspections/inspection-id';
    $input->itemKey = 'pressure';

    $processor = new InspectionResponseProcessor(
      $entityManager,
      $this->createStub(UuidFactory::class),
      $authorization,
      $security,
      $requestStack,
      new InterventionResourceManager($this->createStub(InterventionResourceGatewayPort::class)),
      new CreationPreconditionGuard($requestStack),
      new RevisionGuard($requestStack),
    );

    $this->expectException(ClientResourceAlreadyExistsHttpException::class);
    $this->expectExceptionMessage('A resource with this client identifier already exists.');

    $processor->process($input, new Put(), ['id' => self::RESPONSE_ID]);
  }
}
