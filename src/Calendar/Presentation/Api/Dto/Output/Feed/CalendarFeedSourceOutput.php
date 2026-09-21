<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Dto\Output\Feed;

use Calendar\Presentation\Api\Serialization\CalendarSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/** Source status without internal exception messages or unauthorized source details. */
final class CalendarFeedSourceOutput
{
  #[Groups([CalendarSerializationGroup::READ])]
  public string $sourceKey = '';

  #[Groups([CalendarSerializationGroup::READ])]
  public bool $available = true;

  #[Groups([CalendarSerializationGroup::READ])]
  public bool $truncated = false;
}
