<?php

declare(strict_types=1);

namespace Auth\Presentation\Dto\Response;

use Auth\Presentation\Serialization\AuthSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO JwksOutput
 * @final
 *
 * @category Dto
 * @package Auth\Presentation\Dto\Response
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class JwksOutput
{
  /** @var list<array<string, string>> */
  #[Groups(groups: [AuthSerializationGroup::READ])]
  public array $keys = [];
}
