<?php

declare(strict_types=1);

namespace Tests\Functional\Api;

use Auth\Infrastructure\Security\User\SecurityUser;
use Authorization\Application\Port\Inbound\AuthorizationPort;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Session\Domain\Model\Session\Session;
use Session\Domain\ValueObject\{SessionId, SessionMetadata};
use Session\Infrastructure\Persistence\Doctrine\Repository\SessionRepository;
use Shared\Domain\ValueObject\{IpAddress, UserAgent};
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Real owner filtering and HTTP geography projection even with a granted coarse permission.
 *
 * @category Functional Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class SessionLocationApiTest extends WebTestCase
{
  /**
   * @return array<string, array{bool}> ownership cases
   */
  public static function sessionCases(): array
  {
    return ['own session' => [false], 'foreign session' => [true]];
  }

  #[DataProvider('sessionCases')]
  public function testOwnLocationIsProjectedAndForeignSessionIsNotDisclosed(bool $foreign): void
  {
    $client = self::createClient();
    $client->disableReboot();
    $container = self::getContainer();
    $authorization = $this->createStub(AuthorizationPort::class);
    $authorization->method('hasPermission')->willReturnCallback(static fn (string $userId, string $permission): bool => 'sessions.read' === $permission);
    $container->set(AuthorizationPort::class, $authorization);
    $manager = $container->get('doctrine.orm.auth_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $manager);
    $sessions = new SessionRepository($manager);
    $ownId = '123e4567-e89b-12d3-a456-426614174091';
    $foreignId = '123e4567-e89b-12d3-a456-426614174092';
    foreach ([$ownId => 'geo-owner', $foreignId => 'foreign-owner'] as $id => $owner) {
      $sessions->save(Session::create(new SessionId($id), $owner, new IpAddress('8.8.8.8'), new UserAgent('unknown'), new SessionMetadata(country: 'FR', city: 'Paris')));
    }
    $client->loginUser(new SecurityUser('geo-owner', 'geo-owner@example.com', ''), 'api');
    $client->request('GET', '/api/sessions/' . ($foreign ? $foreignId : $ownId), server: ['HTTP_ACCEPT' => 'application/ld+json']);
    if ($foreign) {
      self::assertResponseStatusCodeSame(404);
      self::assertStringNotContainsString('Paris', $client->getResponse()->getContent() ?: '');

      return;
    }
    self::assertResponseIsSuccessful();
    $body = $client->getResponse()->getContent();
    self::assertIsString($body);
    $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
    self::assertIsArray($data);
    self::assertSame('FR', $data['country']);
    self::assertSame('Paris', $data['city']);
  }
}
