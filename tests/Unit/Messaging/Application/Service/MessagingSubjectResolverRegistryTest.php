<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Service;

use InvalidArgumentException;
use Messaging\Application\Contract\Subject\{MessagingSubjectDescriptor, MessagingSubjectResolution};
use Messaging\Application\Port\Outbound\MessagingSubjectResolverPort;
use Messaging\Application\Service\MessagingSubjectResolverRegistry;
use Messaging\Domain\Exception\MessagingSubjectNotFoundException;
use Messaging\Domain\ValueObject\MessagingSubjectType;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Test MessagingSubjectResolverRegistryTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingSubjectResolverRegistry::class)]
final class MessagingSubjectResolverRegistryTest extends TestCase
{
  #[Test]
  public function testNewContributorPublishesMetadataAndResolvesWithoutChangingMessaging(): void
  {
    $resolver = $this->createMock(MessagingSubjectResolverPort::class);
    $resolver->method('descriptor')->willReturn(new MessagingSubjectDescriptor('service_request', 'Service request', 'service-requests', 'organization.service_requests.read'));
    $resolver->expects(self::once())->method('resolve')->with('org-1', 'request-42')->willReturn(new MessagingSubjectResolution(true, 'Request #42', 'organization.service_requests.read'));
    $registry = new MessagingSubjectResolverRegistry([$resolver]);

    $resolution = $registry->resolve(MessagingSubjectType::from('service_request'), 'org-1', 'request-42');

    self::assertSame('service_request', $registry->descriptors()[0]->type);
    self::assertSame('service-requests', $registry->find('service_request')?->resourceSegment);
    self::assertSame('Request #42', $resolution->label);
    self::assertSame('organization.service_requests.read', $resolution->requiredReadPermission);
  }

  #[Test]
  public function testDuplicateKeysFailAtComposition(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Duplicate messaging subject type "service_request".');

    new MessagingSubjectResolverRegistry([$this->resolver('service_request'), $this->resolver('service_request')]);
  }

  #[Test]
  public function testReservedParticipantKindsCannotBeContributed(): void
  {
    $this->expectException(InvalidArgumentException::class);

    new MessagingSubjectResolverRegistry([$this->resolver('channel')]);
  }

  #[Test]
  public function testUnknownSubjectFailsClosedEvenWithAValidPersistedKey(): void
  {
    $registry = new MessagingSubjectResolverRegistry([]);
    self::assertNull($registry->find('retired_owner'));
    self::assertSame('retired_owner', MessagingSubjectType::from('retired_owner')->value);

    $this->expectException(MessagingSubjectNotFoundException::class);
    $registry->resolve(MessagingSubjectType::from('retired_owner'), 'org-1', 'subject-1');
  }

  #[Test]
  public function testForeignOrMissingSubjectFailsClosed(): void
  {
    $registry = new MessagingSubjectResolverRegistry([$this->resolver('service_request', false)]);

    $this->expectException(MessagingSubjectNotFoundException::class);
    $registry->resolve(MessagingSubjectType::from('service_request'), 'foreign-org', 'subject-1');
  }

  #[Test]
  public function testCatalogPermissionCannotBeWeakenedByAResolution(): void
  {
    $resolver = $this->resolver('service_request');
    $resolver->method('resolve')->willReturn(new MessagingSubjectResolution(true, 'Request #42', 'organization.messaging.read'));
    $registry = new MessagingSubjectResolverRegistry([$resolver]);

    self::assertSame('organization.service_requests.read', $registry->resolve(MessagingSubjectType::from('service_request'), 'org-1', 'subject-1')->requiredReadPermission);
  }

  private function resolver(
    string $type,
    bool $exists = true,
    ?string $label = null,
    string $permission = 'organization.service_requests.read',
  ): MessagingSubjectResolverPort&Stub {
    $resolver = $this->createStub(MessagingSubjectResolverPort::class);
    $resolver->method('descriptor')->willReturn(new MessagingSubjectDescriptor($type, 'Service request', 'service-requests', $permission));
    if (!$exists) {
      $resolver->method('resolve')->willReturn(new MessagingSubjectResolution(false, $label, $permission));
    }

    return $resolver;
  }
}
