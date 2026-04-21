<?php

declare(strict_types=1);

namespace Pet\Application\Conversation\Service;

use Pet\Domain\Conversation\Repository\ConversationRepository;
use Pet\Domain\Conversation\Repository\DecisionRepository;
use Pet\Application\Conversation\Exception\ActionGatedByDecisionException;
use Pet\Domain\Conversation\Entity\Decision;

class ActionGatingService
{
    private const CLEAN_BASELINE_QUOTE_ACTION_GATING_BYPASS_TRANSIENT = 'pet_clean_baseline_quote_action_gating_bypass';
    private ConversationRepository $conversationRepository;
    private DecisionRepository $decisionRepository;

    private const REQUIRED_DECISIONS = [
        'send_quote' => ['send_quote_approval'],
        'accept_quote' => ['accept_quote_approval'],
    ];

    public function __construct(
        ConversationRepository $conversationRepository,
        DecisionRepository $decisionRepository
    ) {
        $this->conversationRepository = $conversationRepository;
        $this->decisionRepository = $decisionRepository;
    }

    public function check(string $contextType, string $contextId, string $action, ?int $versionId = null): void
    {
        if ($this->shouldBypassDuringCleanBaseline($contextType, $action)) {
            return;
        }
        // 1. Check if action requires any decisions
        $requiredTypes = self::REQUIRED_DECISIONS[$action] ?? [];

        if (empty($requiredTypes)) {
            return; // No gating required
        }

        // 2. Find conversation (with version isolation)
        // Convert int versionId to string if present, as context_version is varchar
        $contextVersion = $versionId !== null ? (string)$versionId : null;
        $conversation = $this->conversationRepository->findByContext($contextType, $contextId, $contextVersion);
        
        if (!$conversation) {
            // If decisions are required but no conversation exists -> BLOCK
            throw new ActionGatedByDecisionException(
                $action, 
                [], 
                "Action '$action' requires decisions: " . implode(', ', $requiredTypes) . ", but no conversation found."
            );
        }

        $decisions = $this->decisionRepository->findByConversationId((int)$conversation->id());

        // 3. Verify each required decision type
        foreach ($requiredTypes as $type) {
            $latestDecision = $this->getLatestDecision($decisions, $type);

            if (!$latestDecision) {
                // Required decision type not found -> BLOCK
                throw new ActionGatedByDecisionException(
                    $action,
                    [],
                    "Action '$action' requires decision '$type', which is missing."
                );
            }

            if ($latestDecision->state() !== 'approved') {
                // Decision exists but not approved -> BLOCK
                throw new ActionGatedByDecisionException(
                    $action,
                    [(string)$latestDecision->id()],
                    "Action '$action' is blocked by decision '{$type}' in state '{$latestDecision->state()}'."
                );
            }
        }
    }

    /**
     * @param Decision[] $decisions
     * @param string $type
     * @return Decision|null
     */
    private function getLatestDecision(array $decisions, string $type): ?Decision
    {
        $matching = array_filter($decisions, fn($d) => $d->decisionType() === $type);
        
        if (empty($matching)) {
            return null;
        }

        // Sort by requestedAt descending
        usort($matching, function(Decision $a, Decision $b) {
            return $b->requestedAt() <=> $a->requestedAt();
        });

        return $matching[0];
    }

    private function shouldBypassDuringCleanBaseline(string $contextType, string $action): bool
    {
        if ($contextType !== 'quote' || !in_array($action, ['send_quote', 'accept_quote'], true)) {
            return false;
        }
        if (!function_exists('get_transient')) {
            return false;
        }

        return (bool) get_transient(self::CLEAN_BASELINE_QUOTE_ACTION_GATING_BYPASS_TRANSIENT);
    }
}
