<?php

declare(strict_types=1);

namespace Otp\Domain\ValueObject;

use function is_string;

/**
 * ValueObject OtpMetadata.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OtpMetadata
{
    // #region Constructor
    /**
     * Constructor.
     *
     * Initializes a new instance of
     * the OtpMetadata class.
     *
     * @since 1.0.0
     *
     * @param string|null $ipAddress the client IP address
     * @param string|null $userAgent the client user agent
     * @param string|null $deviceId  the device identifier
     */
    public function __construct(
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $deviceId = null,
    ) {
    }
    // #endregion

    // #region Methods
    /**
     * Method create.
     *
     * @static
     *
     * Creates a new OtpMetadata instance.
     *
     * @since 1.0.0
     *
     * @param string|null $ipAddress the client IP address
     * @param string|null $userAgent the client user agent
     * @param string|null $deviceId  the device identifier
     */
    public static function create(
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $deviceId = null,
    ): self {
        return new self(
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            deviceId: $deviceId,
        );
    }

    /**
     * Method fromArray.
     *
     * @static
     *
     * Creates from an array.
     *
     * @since 1.0.0
     *
     * @param array<string, mixed> $data the data array
     */
    public static function fromArray(array $data): self
    {
        $ipAddress = $data['ip_address'] ?? $data['ipAddress'] ?? null;
        $userAgent = $data['user_agent'] ?? $data['userAgent'] ?? null;
        $deviceId = $data['device_id'] ?? $data['deviceId'] ?? null;

        return new self(
            ipAddress: is_string($ipAddress) ? $ipAddress : null,
            userAgent: is_string($userAgent) ? $userAgent : null,
            deviceId: is_string($deviceId) ? $deviceId : null,
        );
    }

    /**
     * Method toArray.
     *
     * Converts to an array.
     *
     * @since 1.0.0
     *
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'device_id' => $this->deviceId,
        ];
    }

    /**
     * Method isEmpty.
     *
     * Checks if all fields are null.
     *
     * @since 1.0.0
     */
    public function isEmpty(): bool
    {
        return null === $this->ipAddress
          && null === $this->userAgent
          && null === $this->deviceId;
    }
    // #endregion
}
