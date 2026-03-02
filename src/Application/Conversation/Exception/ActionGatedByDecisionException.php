<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Exception;

class ActionGatedByDecisionException extends \RuntimeException
{
    private const ERROR_CODE = 'ACTION_GATED_BY_DECISION';
    private string $action;
    private array $decisionIds;

    public function __construct(string $action, array $decisionIds, string $message = '', \Throwable $previous = null)
    {
        $this->action = $action;
        $this->decisionIds = $decisionIds;
        
        $msg = $message ?: sprintf(
            "Action '%s' is gated by pending/rejected decision(s): %s",
            $action,
            implode(', ', $decisionIds)
        );

        parent::__construct($msg, 403, $previous);
    }

    public function getErrorCode(): string
    {
        return self::ERROR_CODE;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getDecisionIds(): array
    {
        return $this->decisionIds;
    }

    public function getRemediationPayload(): array
    {
        return [
            'action' => $this->action,
            'blocking_decisions' => $this->decisionIds,
            'remediation' => 'Resolve the blocking decisions before proceeding.',
        ];
    }
}
