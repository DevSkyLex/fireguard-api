<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Http;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Http\MergePatchFields;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

final class MergePatchFieldsTest extends TestCase
{
  #[Test]
  public function itPreservesExplicitNullFields(): void
  {
    $request = Request::create(
      '/api/interventions/intervention-1',
      'PATCH',
      server: ['CONTENT_TYPE' => 'application/merge-patch+json'],
      content: '{"responsible":null,"dueAt":null}',
    );
    $stack = new RequestStack();
    $stack->push($request);

    self::assertSame(
      ['responsible' => null, 'dueAt' => null],
      new MergePatchFields($stack)->all(),
    );
  }

  #[Test]
  public function itIgnoresNonPatchRequests(): void
  {
    $stack = new RequestStack();
    $stack->push(Request::create('/api/interventions', 'POST', content: '{"responsible":null}'));

    self::assertSame([], new MergePatchFields($stack)->all());
  }
}
