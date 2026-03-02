<?php

declare(strict_types=1);

namespace Pet\Domain\Conversation\ValueObject;

class ApprovalPolicy implements \JsonSerializable
{
    private string $mode;
    private array $eligibleUserIds;

    public function __construct(string $mode, array $eligibleUserIds)
    {
        if (!in_array($mode, ['any_of', 'all_of'], true)) {
            throw new \InvalidArgumentException("Invalid approval mode: $mode");
        }
        $this->mode = $mode;
        $this->eligibleUserIds = array_map('intval', $eligibleUserIds);
    }

    public function mode(): string
    {
        return $this->mode;
    }

    public function eligibleUserIds(): array
    {
        return $this->eligibleUserIds;
    }

    public function isEligible(int $userId): bool
    {
        return in_array($userId, $this->eligibleUserIds, true);
    }

    public function jsonSerialize(): array
    {
        return [
            'mode' => $this->mode,
            'eligible_user_ids' => $this->eligibleUserIds,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            $data['mode'],
            $data['eligible_user_ids']
        );
    }
}
