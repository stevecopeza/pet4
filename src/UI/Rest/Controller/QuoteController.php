<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Commercial\Command\AddQuoteLineCommand;
use Pet\Application\Commercial\Command\AddQuoteLineHandler;
use Pet\Application\Commercial\Command\CreateQuoteCommand;
use Pet\Application\Commercial\Command\CreateQuoteHandler;
use Pet\Application\Commercial\Command\UpdateQuoteCommand;
use Pet\Application\Commercial\Command\UpdateQuoteHandler;
use Pet\Application\Commercial\Command\ArchiveQuoteCommand;
use Pet\Application\Commercial\Command\ArchiveQuoteHandler;
use Pet\Application\Commercial\Command\AddComponentCommand;
use Pet\Application\Commercial\Command\AddComponentHandler;
use Pet\Application\Commercial\Command\RemoveComponentCommand;
use Pet\Application\Commercial\Command\RemoveComponentHandler;
use Pet\Application\Commercial\Command\SendQuoteCommand;
use Pet\Application\Commercial\Command\SendQuoteHandler;
use Pet\Application\Commercial\Command\AcceptQuoteCommand;
use Pet\Application\Commercial\Command\AcceptQuoteHandler;
use Pet\Application\Commercial\Command\AddCostAdjustmentCommand;
use Pet\Application\Commercial\Command\AddCostAdjustmentHandler;
use Pet\Application\Commercial\Command\RemoveCostAdjustmentCommand;
use Pet\Application\Commercial\Command\RemoveCostAdjustmentHandler;
use Pet\Application\Commercial\Command\SetPaymentScheduleCommand;
use Pet\Application\Commercial\Command\SetPaymentScheduleHandler;
use Pet\Domain\Commercial\Repository\QuoteRepository;
use Pet\Domain\Commercial\Repository\QuoteSectionRepository;
use Pet\Application\Commercial\Command\AddQuoteSectionCommand;
use Pet\Application\Commercial\Command\AddQuoteSectionHandler;
use Pet\Application\Commercial\Command\CreateQuoteBlockCommand;
use Pet\Application\Commercial\Command\CreateQuoteBlockHandler;
use Pet\Application\Commercial\Command\UpdateQuoteBlockCommand;
use Pet\Application\Commercial\Command\UpdateQuoteBlockHandler;
use Pet\Application\Commercial\Command\DeleteQuoteBlockCommand;
use Pet\Application\Commercial\Command\DeleteQuoteBlockHandler;
use Pet\Application\Commercial\Command\UpdateQuoteSectionCommand;
use Pet\Application\Commercial\Command\UpdateQuoteSectionHandler;
use Pet\Application\Commercial\Command\CloneQuoteSectionCommand;
use Pet\Application\Commercial\Command\CloneQuoteSectionHandler;
use Pet\Application\Commercial\Command\DeleteQuoteSectionCommand;
use Pet\Application\Commercial\Command\DeleteQuoteSectionHandler;
use Pet\Application\Commercial\Service\QuoteBlockMarginCalculator;
use Pet\Domain\Commercial\Repository\QuoteBlockRepository;
use Pet\Domain\Commercial\Entity\Component\CatalogComponent;
use Pet\Domain\Commercial\Entity\Component\ImplementationComponent;
use Pet\Domain\Commercial\Entity\Component\OnceOffServiceComponent;
use Pet\Domain\Commercial\Entity\Component\Phase;
use Pet\Domain\Commercial\Entity\Component\QuoteMilestone;
use Pet\Domain\Commercial\Entity\Component\QuoteTask;
use Pet\Domain\Commercial\Entity\Component\RecurringServiceComponent;
use Pet\Domain\Commercial\Entity\Component\SimpleUnit;
use Pet\Domain\Commercial\Entity\CostAdjustment;
use Pet\Domain\Commercial\Entity\QuoteSection;
use Pet\UI\Rest\Validation\InputValidation as V;
use Pet\UI\Rest\Support\PortalPermissionHelper;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use DateTimeImmutable;

class QuoteController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'quotes';

    private QuoteRepository $quoteRepository;
    private CreateQuoteHandler $createQuoteHandler;
    private UpdateQuoteHandler $updateQuoteHandler;
    private AddQuoteLineHandler $addQuoteLineHandler;
    private ArchiveQuoteHandler $archiveQuoteHandler;
    private AddComponentHandler $addComponentHandler;
    private RemoveComponentHandler $removeComponentHandler;
    private SendQuoteHandler $sendQuoteHandler;
    private AcceptQuoteHandler $acceptQuoteHandler;
    private AddCostAdjustmentHandler $addCostAdjustmentHandler;
    private RemoveCostAdjustmentHandler $removeCostAdjustmentHandler;
    private SetPaymentScheduleHandler $setPaymentScheduleHandler;
    private QuoteSectionRepository $quoteSectionRepository;
    private AddQuoteSectionHandler $addQuoteSectionHandler;
    private UpdateQuoteSectionHandler $updateQuoteSectionHandler;
    private CloneQuoteSectionHandler $cloneQuoteSectionHandler;
    private DeleteQuoteSectionHandler $deleteQuoteSectionHandler;
    private QuoteBlockRepository $quoteBlockRepository;
    private CreateQuoteBlockHandler $createQuoteBlockHandler;
    private UpdateQuoteBlockHandler $updateQuoteBlockHandler;
    private DeleteQuoteBlockHandler $deleteQuoteBlockHandler;
    private QuoteBlockMarginCalculator $quoteBlockMarginCalculator;

    // Approval workflow
    private \Pet\Application\Commercial\Command\SubmitQuoteForApprovalHandler $submitForApprovalHandler;
    private \Pet\Application\Commercial\Command\ApproveQuoteHandler $approveQuoteHandler;
    private \Pet\Application\Commercial\Command\RejectQuoteApprovalHandler $rejectQuoteApprovalHandler;
    private \Pet\Application\Commercial\Service\QuoteApprovalRulesService $approvalRulesService;

    public function __construct(
        QuoteRepository $quoteRepository,
        CreateQuoteHandler $createQuoteHandler,
        UpdateQuoteHandler $updateQuoteHandler,
        AddQuoteLineHandler $addQuoteLineHandler,
        ArchiveQuoteHandler $archiveQuoteHandler,
        AddComponentHandler $addComponentHandler,
        RemoveComponentHandler $removeComponentHandler,
        SendQuoteHandler $sendQuoteHandler,
        AcceptQuoteHandler $acceptQuoteHandler,
        AddCostAdjustmentHandler $addCostAdjustmentHandler,
        RemoveCostAdjustmentHandler $removeCostAdjustmentHandler,
        SetPaymentScheduleHandler $setPaymentScheduleHandler,
        QuoteSectionRepository $quoteSectionRepository,
        AddQuoteSectionHandler $addQuoteSectionHandler,
        UpdateQuoteSectionHandler $updateQuoteSectionHandler,
        CloneQuoteSectionHandler $cloneQuoteSectionHandler,
        DeleteQuoteSectionHandler $deleteQuoteSectionHandler,
        QuoteBlockRepository $quoteBlockRepository,
        CreateQuoteBlockHandler $createQuoteBlockHandler,
        UpdateQuoteBlockHandler $updateQuoteBlockHandler,
        DeleteQuoteBlockHandler $deleteQuoteBlockHandler,
        QuoteBlockMarginCalculator $quoteBlockMarginCalculator,
        \Pet\Application\Commercial\Command\SubmitQuoteForApprovalHandler $submitForApprovalHandler,
        \Pet\Application\Commercial\Command\ApproveQuoteHandler $approveQuoteHandler,
        \Pet\Application\Commercial\Command\RejectQuoteApprovalHandler $rejectQuoteApprovalHandler,
        \Pet\Application\Commercial\Service\QuoteApprovalRulesService $approvalRulesService
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->createQuoteHandler = $createQuoteHandler;
        $this->updateQuoteHandler = $updateQuoteHandler;
        $this->addQuoteLineHandler = $addQuoteLineHandler;
        $this->archiveQuoteHandler = $archiveQuoteHandler;
        $this->addComponentHandler = $addComponentHandler;
        $this->removeComponentHandler = $removeComponentHandler;
        $this->sendQuoteHandler = $sendQuoteHandler;
        $this->acceptQuoteHandler = $acceptQuoteHandler;
        $this->addCostAdjustmentHandler = $addCostAdjustmentHandler;
        $this->removeCostAdjustmentHandler = $removeCostAdjustmentHandler;
        $this->setPaymentScheduleHandler = $setPaymentScheduleHandler;
        $this->quoteSectionRepository = $quoteSectionRepository;
        $this->addQuoteSectionHandler = $addQuoteSectionHandler;
        $this->updateQuoteSectionHandler = $updateQuoteSectionHandler;
        $this->cloneQuoteSectionHandler = $cloneQuoteSectionHandler;
        $this->deleteQuoteSectionHandler = $deleteQuoteSectionHandler;
        $this->quoteBlockRepository = $quoteBlockRepository;
        $this->createQuoteBlockHandler = $createQuoteBlockHandler;
        $this->updateQuoteBlockHandler = $updateQuoteBlockHandler;
        $this->deleteQuoteBlockHandler = $deleteQuoteBlockHandler;
        $this->quoteBlockMarginCalculator = $quoteBlockMarginCalculator;
        $this->submitForApprovalHandler   = $submitForApprovalHandler;
        $this->approveQuoteHandler        = $approveQuoteHandler;
        $this->rejectQuoteApprovalHandler = $rejectQuoteApprovalHandler;
        $this->approvalRulesService       = $approvalRulesService;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getQuotes'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createQuote'],
                'permission_callback' => [$this, 'checkPortalPermission'],
                'args' => [
                    'customerId' => V::requiredIntArg(),
                    'title' => V::requiredStringArg(),
                    'description' => ['required' => false, 'sanitize_callback' => [V::class, 'sanitizeTextarea']],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getQuote'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateQuote'],
                'permission_callback' => [$this, 'checkPortalPermission'],
                'args' => [
                    'title' => V::requiredStringArg(),
                    'description' => ['required' => false, 'sanitize_callback' => [V::class, 'sanitizeTextarea']],
                ],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'archiveQuote'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/lines', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'addLine'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/components', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'addComponent'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/components/(?P<componentId>\d+)', [
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'removeComponent'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/send', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'sendQuote'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/accept', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'acceptQuote'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/submit-for-approval', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'submitForApproval'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/approve', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'approveQuote'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/reject-approval', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'rejectQuoteApproval'],
                'permission_callback' => [$this, 'checkLoggedIn'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/adjustments', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'addCostAdjustment'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/adjustments/(?P<adjustmentId>\d+)', [
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'removeCostAdjustment'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/payment-schedule', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'setPaymentSchedule'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/sections', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'addSection'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/sections/(?P<sectionId>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateSection'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteSection'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/sections/(?P<sectionId>\d+)/clone', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'cloneSection'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/sections/reorder', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'reorderSections'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/sections/(?P<sectionId>[^/]+)/blocks', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'addBlockToSection'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/blocks/reorder', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'reorderBlocks'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)/blocks/(?P<blockId>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateBlock'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteBlock'],
                'permission_callback' => [$this, 'checkPortalPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/session', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getSession'],
                'permission_callback' => '__return_true', // Public, but we check login inside or relies on cookie
            ],
        ]);
    }

    public function getSession(): WP_REST_Response
    {
        if (!is_user_logged_in()) {
            return new WP_REST_Response(['code' => 'unauthorized'], 401);
        }
        return new WP_REST_Response([
            'nonce' => wp_create_nonce('wp_rest'),
            'user_id' => get_current_user_id()
        ], 200);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function checkPortalPermission(): bool
    {
        return PortalPermissionHelper::check('pet_sales', 'pet_manager');
    }

    /**
     * Approval actions only need an authenticated user — manager authorisation
     * is enforced at the domain layer inside the handler.
     */
    public function checkLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    public function getQuotes(WP_REST_Request $request): WP_REST_Response
    {
        // For now findAll, potentially filter by customer
        $quotes = $this->quoteRepository->findAll();

        $data = array_map(function ($quote) {
            return $this->serializeQuote($quote);
        }, $quotes);

        return new WP_REST_Response($data, 200);
    }

    public function getQuote(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $quote = $this->quoteRepository->findById($id);

        if (!$quote) {
            return new WP_REST_Response(['error' => 'Quote not found'], 404);
        }

        return new WP_REST_Response($this->serializeQuote($quote), 200);
    }

    private function serializeQuote($quote): array
    {
        $sections = $this->quoteSectionRepository->findByQuoteId($quote->id());
        $blocks = $this->quoteBlockRepository->findByQuoteId($quote->id());

        return [
            'id' => $quote->id(),
            'customerId' => $quote->customerId(),
            'leadId' => $quote->leadId(),
            'title' => $quote->title(),
            'description' => $quote->description(),
            'state' => $quote->state()->toString(),
            'createdByUserId' => $quote->createdByUserId(),
            'version' => $quote->version(),
            'totalValue' => $quote->totalValue(),
            'totalInternalCost' => $quote->totalInternalCost(),
            'adjustedTotalInternalCost' => $quote->adjustedTotalInternalCost(),
            'margin' => $quote->margin(),
            'currency' => $quote->currency(),
            'acceptedAt' => $quote->acceptedAt() ? $quote->acceptedAt()->format(\DateTimeImmutable::ATOM) : null,
            'malleableData' => $quote->malleableData(),
            'components' => array_map(function ($component) {
                $data = [
                    'id' => $component->id(),
                    'type' => $component->type(),
                    'section' => $component->section(),
                    'description' => $component->description(),
                    'sellValue' => $component->sellValue(),
                    'internalCost' => $component->internalCost(),
                ];

                if ($component instanceof ImplementationComponent) {
                    $data['milestones'] = array_map(function (QuoteMilestone $milestone) {
                        return [
                            'id' => $milestone->id(),
                            'title' => $milestone->title(),
                            'description' => $milestone->description(),
                            'tasks' => array_map(function (QuoteTask $task) {
                                return [
                                    'id' => $task->id(),
                                    'title' => $task->title(),
                                    'description' => $task->description(),
                                    'durationHours' => $task->durationHours(),
                                    'sellRate' => $task->sellRate(),
                                    'baseInternalRate' => $task->baseInternalRate(),
                                    'sellValue' => $task->sellValue(),
                                    'internalCost' => $task->internalCost(),
                                ];
                            }, $milestone->tasks()),
                            'sellValue' => $milestone->sellValue(),
                            'internalCost' => $milestone->internalCost(),
                        ];
                    }, $component->milestones());
                } elseif ($component instanceof CatalogComponent) {
                    $data['items'] = array_map(function ($item) {
                        return [
                            'description' => $item->description(),
                            'quantity' => $item->quantity(),
                            'unitSellPrice' => $item->unitSellPrice(),
                            'sellValue' => $item->sellValue(),
                        ];
                    }, $component->items());
                } elseif ($component instanceof OnceOffServiceComponent) {
                    $data['topology'] = $component->topology();

                    if ($component->topology() === OnceOffServiceComponent::TOPOLOGY_SIMPLE) {
                        $data['units'] = array_map(function (SimpleUnit $unit) {
                            return [
                                'id' => $unit->id(),
                                'title' => $unit->title(),
                                'description' => $unit->description(),
                                'quantity' => $unit->quantity(),
                                'unitSellPrice' => $unit->unitSellPrice(),
                                'unitInternalCost' => $unit->unitInternalCost(),
                                'sellValue' => $unit->sellValue(),
                                'internalCost' => $unit->internalCost(),
                            ];
                        }, $component->units());
                    } else {
                        $data['phases'] = array_map(function (Phase $phase) {
                            return [
                                'id' => $phase->id(),
                                'name' => $phase->name(),
                                'description' => $phase->description(),
                                'units' => array_map(function (SimpleUnit $unit) {
                                    return [
                                        'id' => $unit->id(),
                                        'title' => $unit->title(),
                                        'description' => $unit->description(),
                                        'quantity' => $unit->quantity(),
                                        'unitSellPrice' => $unit->unitSellPrice(),
                                        'unitInternalCost' => $unit->unitInternalCost(),
                                        'sellValue' => $unit->sellValue(),
                                        'internalCost' => $unit->internalCost(),
                                    ];
                                }, $phase->units()),
                                'sellValue' => $phase->sellValue(),
                                'internalCost' => $phase->internalCost(),
                            ];
                        }, $component->phases());
                    }
                } elseif ($component instanceof RecurringServiceComponent) {
                    $data['serviceName'] = $component->serviceName();
                    $data['cadence'] = $component->cadence();
                    $data['termMonths'] = $component->termMonths();
                    $data['renewalModel'] = $component->renewalModel();
                    $data['sellPricePerPeriod'] = $component->sellPricePerPeriod();
                    $data['internalCostPerPeriod'] = $component->internalCostPerPeriod();
                }

                return $data;
            }, $quote->components()),
            'costAdjustments' => array_map(function ($adjustment) {
                return [
                    'id' => $adjustment->id(),
                    'description' => $adjustment->description(),
                    'amount' => $adjustment->amount(),
                    'reason' => $adjustment->reason(),
                    'approvedBy' => $adjustment->approvedBy(),
                    'appliedAt' => $adjustment->appliedAt()->format(DateTimeImmutable::ATOM),
                ];
            }, $quote->costAdjustments()),
            'paymentSchedule' => array_map(function ($milestone) {
                return [
                    'id' => $milestone->id(),
                    'title' => $milestone->title(),
                    'amount' => $milestone->amount(),
                    'dueDate' => $milestone->dueDate() ? $milestone->dueDate()->format(DateTimeImmutable::ATOM) : null,
                    'isPaid' => $milestone->isPaid(),
                ];
            }, $quote->paymentSchedule()),
            'sections' => array_map(function ($section) {
                return [
                    'id' => $section->id(),
                    'quoteId' => $section->quoteId(),
                    'name' => $section->name(),
                    'orderIndex' => $section->orderIndex(),
                    'showTotalValue' => $section->showTotalValue(),
                    'showItemCount' => $section->showItemCount(),
                    'showTotalHours' => $section->showTotalHours(),
                ];
            }, $sections),
            'blocks' => array_map(function ($block) {
                $marginData = $this->quoteBlockMarginCalculator->calculate($block->type(), $block->payload());
                return [
                    'id' => $block->id(),
                    'quoteId' => null,
                    'sectionId' => $block->sectionId(),
                    'type' => $block->type(),
                    'orderIndex' => $block->position(),
                    'componentId' => $block->componentId(),
                    'priced' => $block->isPriced(),
                    'payload' => $marginData['payload'],
                    'lineSellValue' => $marginData['lineSellValue'],
                    'lineCostValue' => $marginData['lineCostValue'],
                    'marginAmount' => $marginData['marginAmount'],
                    'marginPercentage' => $marginData['marginPercentage'],
                    'hasMarginData' => $marginData['hasMarginData'],
                ];
            }, $blocks),
            // Approval workflow fields
            'approvalState' => [
                'rejectionNote'           => $quote->rejectionNote(),
                'submittedForApprovalAt'  => $quote->submittedForApprovalAt()
                    ? $quote->submittedForApprovalAt()->format(\DateTimeImmutable::ATOM) : null,
                'approvedAt'              => $quote->approvedAt()
                    ? $quote->approvedAt()->format(\DateTimeImmutable::ATOM) : null,
                'approvedByUserId'        => $quote->approvedByUserId(),
                'requiresApprovalForSend' => $this->approvalRulesService->requiresApproval($quote, 0.0),
                'approvalReasons'         => $this->approvalRulesService->approvalReasons($quote, 0.0),
            ],
        ];
    }

    public function addBlockToSection(WP_REST_Request $request): WP_REST_Response
    {
        $quoteId = (int) $request->get_param('id');
        $rawSectionId = $request->get_param('sectionId');
        $sectionId = null;

        if ($rawSectionId !== null && $rawSectionId !== '' && $rawSectionId !== 'null') {
            $sectionId = (int) $rawSectionId;
        }
        $params = $request->get_json_params();

        try {
            $type = isset($params['type']) && is_string($params['type']) ? $params['type'] : '';
            if ($type === '') {
                return new WP_REST_Response(['error' => 'Block type is required'], 400);
            }

            $payload = isset($params['payload']) && is_array($params['payload']) ? $params['payload'] : [];

            $command = new CreateQuoteBlockCommand($quoteId, $sectionId, $type, $payload);
            $this->createQuoteBlockHandler->handle($command);

            $quote = $this->quoteRepository->findById($quoteId);

            if (!$quote) {
                return new WP_REST_Response(['error' => 'Quote not found'], 404);
            }

            return new WP_REST_Response($this->serializeQuote($quote), 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function addSection(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $name = isset($params['name']) && is_string($params['name']) && $params['name'] !== ''
                ? $params['name']
                : 'New Section';

            $command = new AddQuoteSectionCommand($id, $name);
            $this->addQuoteSectionHandler->handle($command);

            $quote = $this->quoteRepository->findById($id);

            if (!$quote) {
                return new WP_REST_Response(['error' => 'Quote not found'], 404);
            }

            return new WP_REST_Response($this->serializeQuote($quote), 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function updateSection(WP_REST_Request $request): WP_REST_Response
    {
        $quoteId = (int) $request->get_param('id');
        $sectionId = (int) $request->get_param('sectionId');
        $params = $request->get_json_params();

        $name = isset($params['name']) && is_string($params['name']) && $params['name'] !== ''
            ? $params['name']
            : 'Section';

        $showTotalValue = isset($params['showTotalValue']) ? (bool) $params['showTotalValue'] : true;
        $showItemCount = isset($params['showItemCount']) ? (bool) $params['showItemCount'] : false;
        $showTotalHours = isset($params['showTotalHours']) ? (bool) $params['showTotalHours'] : false;

        try {
            $command = new UpdateQuoteSectionCommand(
                $quoteId,
                $sectionId,
                $name,
                $showTotalValue,
                $showItemCount,
                $showTotalHours
            );

            $this->updateQuoteSectionHandler->handle($command);

            $quote = $this->quoteRepository->findById($quoteId);

            if (!$quote) {
                return new WP_REST_Response(['error' => 'Quote not found'], 404);
            }

            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => 'Failed to update section'], 500);
        }
    }

    public function deleteSection(WP_REST_Request $request): WP_REST_Response
    {
        $quoteId = (int) $request->get_param('id');
        $sectionId = (int) $request->get_param('sectionId');

        try {
            $command = new DeleteQuoteSectionCommand($quoteId, $sectionId);
            $this->deleteQuoteSectionHandler->handle($command);

            $quote = $this->quoteRepository->findById($quoteId);

            if (!$quote) {
                return new WP_REST_Response(['error' => 'Quote not found'], 404);
            }

            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => 'Failed to delete section'], 500);
        }
    }

    public function cloneSection(WP_REST_Request $request): WP_REST_Response
    {
        $quoteId = (int) $request->get_param('id');
        $sectionId = (int) $request->get_param('sectionId');

        try {
            $command = new CloneQuoteSectionCommand($quoteId, $sectionId);
            $this->cloneQuoteSectionHandler->handle($command);

            $quote = $this->quoteRepository->findById($quoteId);

            if (!$quote) {
                return new WP_REST_Response(['error' => 'Quote not found'], 404);
            }

            return new WP_REST_Response($this->serializeQuote($quote), 201);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => 'Failed to clone section'], 500);
        }
    }

    public function reorderSections(WP_REST_Request $request): WP_REST_Response
    {
        $quoteId = (int) $request->get_param('id');
        $params = $request->get_json_params();
        $changes = isset($params['changes']) && is_array($params['changes']) ? $params['changes'] : [];

        if (empty($changes)) {
            return new WP_REST_Response(['error' => 'No changes provided'], 400);
        }

        try {
            $sections = $this->quoteSectionRepository->findByQuoteId($quoteId);
            $orderMap = [];
            foreach ($changes as $change) {
                $orderMap[(int) $change['id']] = (int) $change['orderIndex'];
            }

            $updated = [];
            foreach ($sections as $section) {
                if (isset($orderMap[$section->id()])) {
                    $updated[] = new QuoteSection(
                        $section->quoteId(),
                        $section->name(),
                        $orderMap[$section->id()],
                        $section->showTotalValue(),
                        $section->showItemCount(),
                        $section->showTotalHours(),
                        $section->id()
                    );
                }
            }

            $this->quoteSectionRepository->saveOrdering($quoteId, $updated);

            $quote = $this->quoteRepository->findById($quoteId);

            if (!$quote) {
                return new WP_REST_Response(['error' => 'Quote not found'], 404);
            }

            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => 'Failed to reorder sections'], 500);
        }
    }

    public function reorderBlocks(WP_REST_Request $request): WP_REST_Response
    {
        $quoteId = (int) $request->get_param('id');
        $params = $request->get_json_params();
        $changes = isset($params['changes']) && is_array($params['changes']) ? $params['changes'] : [];

        if (empty($changes)) {
            return new WP_REST_Response(['error' => 'No changes provided'], 400);
        }

        try {
            $this->quoteBlockRepository->reorder($quoteId, $changes);

            $quote = $this->quoteRepository->findById($quoteId);

            if (!$quote) {
                return new WP_REST_Response(['error' => 'Quote not found'], 404);
            }

            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => 'Failed to reorder blocks'], 500);
        }
    }

    public function updateBlock(WP_REST_Request $request): WP_REST_Response
    {
        $quoteId = (int) $request->get_param('id');
        $blockId = (int) $request->get_param('blockId');
        $params = $request->get_json_params();

        $payload = isset($params['payload']) && is_array($params['payload']) ? $params['payload'] : [];

        try {
            $command = new UpdateQuoteBlockCommand($quoteId, $blockId, $payload);
            $this->updateQuoteBlockHandler->handle($command);

            $quote = $this->quoteRepository->findById($quoteId);

            if (!$quote) {
                return new WP_REST_Response(['error' => 'Quote not found'], 404);
            }

            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => 'Failed to update block'], 500);
        }
    }

    public function deleteBlock(WP_REST_Request $request): WP_REST_Response
    {
        $quoteId = (int) $request->get_param('id');
        $blockId = (int) $request->get_param('blockId');

        try {
            $command = new DeleteQuoteBlockCommand($quoteId, $blockId);
            $this->deleteQuoteBlockHandler->handle($command);

            $quote = $this->quoteRepository->findById($quoteId);

            if (!$quote) {
                return new WP_REST_Response(['error' => 'Quote not found'], 404);
            }

            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\DomainException $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        } catch (\Throwable $e) {
            return new WP_REST_Response(['error' => 'Failed to delete block'], 500);
        }
    }

    public function createQuote(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        try {
            $command = new CreateQuoteCommand(
                (int) $params['customerId'],
                (string) ($params['title'] ?? ''),
                $params['description'] ?? null,
                (string) ($params['currency'] ?? 'USD'),
                !empty($params['acceptedAt']) ? new \DateTimeImmutable($params['acceptedAt']) : null,
                $params['malleableData'] ?? [],
                null,                       // leadId — set via separate linkage if needed
                get_current_user_id() ?: null
            );

            $quoteId = $this->createQuoteHandler->handle($command);
            
            $quote = $this->quoteRepository->findById($quoteId);
            return new WP_REST_Response($this->serializeQuote($quote), 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function updateQuote(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $command = new UpdateQuoteCommand(
                $id,
                (int) $params['customerId'],
                (string) ($params['title'] ?? ''),
                isset($params['description']) ? (string) $params['description'] : null,
                (string) ($params['currency'] ?? 'USD'),
                !empty($params['acceptedAt']) ? new \DateTimeImmutable($params['acceptedAt']) : null,
                $params['malleableData'] ?? []
            );

            $this->updateQuoteHandler->handle($command);

            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function addLine(WP_REST_Request $request): WP_REST_Response
    {
        $quoteId = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $command = new AddQuoteLineCommand(
                $quoteId,
                $params['description'],
                (float) $params['quantity'],
                (float) $params['unitPrice'],
                $params['lineGroupType']
            );

            $this->addQuoteLineHandler->handle($command);

            return new WP_REST_Response(['message' => 'Line added'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function addComponent(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $command = new AddComponentCommand(
                $id,
                $params['type'],
                $params['data']
            );

            $this->addComponentHandler->handle($command);

            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function removeComponent(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $componentId = (int) $request->get_param('componentId');

        try {
            $command = new RemoveComponentCommand($id, $componentId);
            $this->removeComponentHandler->handle($command);

            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function sendQuote(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        
        try {
            $command = new SendQuoteCommand($id);
            $this->sendQuoteHandler->handle($command);
            
            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function acceptQuote(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        
        try {
            $command = new AcceptQuoteCommand($id);
            $this->acceptQuoteHandler->handle($command);
            
            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function submitForApproval(WP_REST_Request $request): WP_REST_Response
    {
        $id     = (int) $request->get_param('id');
        $userId = get_current_user_id();

        try {
            $command = new \Pet\Application\Commercial\Command\SubmitQuoteForApprovalCommand($id, $userId);
            $this->submitForApprovalHandler->handle($command);

            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function approveQuote(WP_REST_Request $request): WP_REST_Response
    {
        $id     = (int) $request->get_param('id');
        $userId = get_current_user_id();

        try {
            $command = new \Pet\Application\Commercial\Command\ApproveQuoteCommand($id, $userId);
            $this->approveQuoteHandler->handle($command);

            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function rejectQuoteApproval(WP_REST_Request $request): WP_REST_Response
    {
        $id     = (int) $request->get_param('id');
        $userId = get_current_user_id();
        $params = $request->get_json_params();
        $note   = trim($params['note'] ?? '');

        try {
            $command = new \Pet\Application\Commercial\Command\RejectQuoteApprovalCommand($id, $userId, $note);
            $this->rejectQuoteApprovalHandler->handle($command);

            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function archiveQuote(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        try {
            $command = new ArchiveQuoteCommand($id);
            $this->archiveQuoteHandler->handle($command);

            return new WP_REST_Response(['message' => 'Quote archived'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function addCostAdjustment(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $command = new AddCostAdjustmentCommand(
                $id,
                $params['description'],
                (float) $params['amount'],
                $params['reason'],
                $params['approvedBy']
            );
            $this->addCostAdjustmentHandler->handle($command);

            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function removeCostAdjustment(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $adjustmentId = (int) $request->get_param('adjustmentId');

        try {
            $command = new RemoveCostAdjustmentCommand($id, $adjustmentId);
            $this->removeCostAdjustmentHandler->handle($command);

            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }

    public function setPaymentSchedule(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $command = new SetPaymentScheduleCommand($id, $params['milestones']);
            $this->setPaymentScheduleHandler->handle($command);

            $quote = $this->quoteRepository->findById($id);
            return new WP_REST_Response($this->serializeQuote($quote), 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => \Pet\UI\Rest\Support\RestError::message($e)], 400);
        }
    }
}
