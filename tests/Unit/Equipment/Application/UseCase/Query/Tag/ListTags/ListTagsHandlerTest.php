<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\UseCase\Query\Tag\ListTags;

use Equipment\Application\Port\Outbound\TagRepositoryPort;
use Equipment\Application\UseCase\Query\Tag\ListTags\{ListTagsHandler, ListTagsQuery, ListTagsResult};
use Equipment\Domain\Model\Tag\Tag;
use Equipment\Domain\ValueObject\{EquipmentOrganizationId, TagId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\Pagination;
use Shared\Domain\Exception\InvalidValueException;

use function sprintf;

#[CoversClass(ListTagsHandler::class)]
final class ListTagsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655480001';

  private const string TAG_ID = '550e8400-e29b-41d4-a716-446655480010';

  #[Test]
  public function testInvokeThrowsInvalidArgumentOnInvalidOrganizationId(): void
  {
    $handler = new ListTagsHandler(
      tagRepository: $this->createStub(TagRepositoryPort::class),
    );

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new ListTagsQuery(
      organizationId: 'not-a-uuid',
      search: null,
      pagination: new Pagination(offset: 0, limit: 30),
    ));
  }

  #[Test]
  public function testInvokeReturnsEmptyResultWhenNoTags(): void
  {
    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->willReturn(0);
    $tagRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([]);

    $handler = new ListTagsHandler(tagRepository: $tagRepository);

    $result = $handler->__invoke(new ListTagsQuery(
      organizationId: self::ORG_ID,
      search: null,
      pagination: new Pagination(offset: 0, limit: 30),
    ));

    self::assertInstanceOf(ListTagsResult::class, $result);
    self::assertSame([], $result->tags);
    self::assertSame(0, $result->total);
  }

  #[Test]
  public function testInvokeReturnsTagsMappedToArrays(): void
  {
    $tag = Tag::create(
      id: TagId::fromString(self::TAG_ID),
      organizationId: EquipmentOrganizationId::fromString(self::ORG_ID),
      name: 'extinguisher',
    );

    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('countByOrganizationId')->willReturn(1);
    $tagRepository->method('findByOrganizationId')->willReturn([$tag]);

    $handler = new ListTagsHandler(tagRepository: $tagRepository);

    $result = $handler->__invoke(new ListTagsQuery(
      organizationId: self::ORG_ID,
      search: null,
      pagination: new Pagination(offset: 0, limit: 30),
    ));

    self::assertCount(1, $result->tags);
    self::assertSame(self::TAG_ID, $result->tags[0]['id']);
    self::assertSame('extinguisher', $result->tags[0]['name']);
    self::assertSame(self::ORG_ID, $result->tags[0]['organizationId']);
    self::assertSame(1, $result->total);
  }

  #[Test]
  public function testInvokeAppliesPaginationSlice(): void
  {
    $orgId = EquipmentOrganizationId::fromString(self::ORG_ID);

    $tags = [];
    for ($i = 0; $i < 5; ++$i) {
      $tags[] = Tag::create(
        id: TagId::fromString(sprintf('550e8400-e29b-41d4-a716-44665548%04d', $i)),
        organizationId: $orgId,
        name: 'tag-' . $i,
      );
    }

    $tagRepository = $this->createStub(TagRepositoryPort::class);
    $tagRepository->method('countByOrganizationId')->willReturn(5);
    $tagRepository->method('findByOrganizationId')->willReturn($tags);

    $handler = new ListTagsHandler(tagRepository: $tagRepository);

    // Request page 2, limit 2 → offset 2 → should return tags[2..3]
    $result = $handler->__invoke(new ListTagsQuery(
      organizationId: self::ORG_ID,
      search: null,
      pagination: new Pagination(offset: 2, limit: 2),
    ));

    self::assertCount(2, $result->tags);
    self::assertSame('tag-2', $result->tags[0]['name']);
    self::assertSame('tag-3', $result->tags[1]['name']);
    self::assertSame(5, $result->total);
  }

  #[Test]
  public function testInvokeForwardsSearchToRepository(): void
  {
    /** @var TagRepositoryPort&MockObject $tagRepository */
    $tagRepository = $this->createMock(TagRepositoryPort::class);
    $tagRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->with(
        self::callback(static fn (EquipmentOrganizationId $id): bool => self::ORG_ID === (string) $id),
        'fire',
      )
      ->willReturn(0);
    $tagRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->with(
        self::callback(static fn (EquipmentOrganizationId $id): bool => self::ORG_ID === (string) $id),
        'fire',
      )
      ->willReturn([]);

    $handler = new ListTagsHandler(tagRepository: $tagRepository);

    $handler->__invoke(new ListTagsQuery(
      organizationId: self::ORG_ID,
      search: 'fire',
      pagination: new Pagination(offset: 0, limit: 30),
    ));
  }
}
