<?php

namespace Pet\Domain\Feed\Entity;

use DateTimeImmutable;

class AnnouncementAcknowledgement
{
    public function __construct(
        private string $id,
        private string $announcementId,
        private string $userId,
        private DateTimeImmutable $acknowledgedAt,
        private ?string $deviceInfo,
        private ?float $gpsLat,
        private ?float $gpsLng
    ) {}

    public static function create(
        string $id,
        string $announcementId,
        string $userId,
        ?string $deviceInfo = null,
        ?float $gpsLat = null,
        ?float $gpsLng = null
    ): self {
        return new self(
            $id,
            $announcementId,
            $userId,
            new DateTimeImmutable(),
            $deviceInfo,
            $gpsLat,
            $gpsLng
        );
    }

    public function getId(): string { return $this->id; }
    public function getAnnouncementId(): string { return $this->announcementId; }
    public function getUserId(): string { return $this->userId; }
    public function getAcknowledgedAt(): DateTimeImmutable { return $this->acknowledgedAt; }
    public function getDeviceInfo(): ?string { return $this->deviceInfo; }
    public function getGpsLat(): ?float { return $this->gpsLat; }
    public function getGpsLng(): ?float { return $this->gpsLng; }
}
