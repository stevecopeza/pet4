<?php

declare(strict_types=1);

namespace Pet\UI\Rest\Controller;

use Pet\Application\Knowledge\Command\CreateArticleCommand;
use Pet\Application\Knowledge\Command\CreateArticleHandler;
use Pet\Application\Knowledge\Command\UpdateArticleCommand;
use Pet\Application\Knowledge\Command\UpdateArticleHandler;
use Pet\Application\Knowledge\Command\ArchiveArticleCommand;
use Pet\Application\Knowledge\Command\ArchiveArticleHandler;
use Pet\Domain\Knowledge\Repository\ArticleRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class ArticleController implements RestController
{
    private const NAMESPACE = 'pet/v1';
    private const RESOURCE = 'articles';

    private ArticleRepository $articleRepository;
    private CreateArticleHandler $createArticleHandler;
    private UpdateArticleHandler $updateArticleHandler;
    private ArchiveArticleHandler $archiveArticleHandler;

    public function __construct(
        ArticleRepository $articleRepository,
        CreateArticleHandler $createArticleHandler,
        UpdateArticleHandler $updateArticleHandler,
        ArchiveArticleHandler $archiveArticleHandler
    ) {
        $this->articleRepository = $articleRepository;
        $this->createArticleHandler = $createArticleHandler;
        $this->updateArticleHandler = $updateArticleHandler;
        $this->archiveArticleHandler = $archiveArticleHandler;
    }

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE, [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getArticles'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createArticle'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::RESOURCE . '/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateArticle'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'archiveArticle'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
        ]);
    }

    public function checkPermission(): bool
    {
        return current_user_can('manage_options');
    }

    public function getArticles(WP_REST_Request $request): WP_REST_Response
    {
        $category = $request->get_param('category');
        
        if ($category) {
            $articles = $this->articleRepository->findByCategory($category);
        } else {
            $articles = $this->articleRepository->findAll();
        }

        $data = array_map(function ($article) {
            return [
                'id' => $article->id(),
                'title' => $article->title(),
                'content' => $article->content(),
                'category' => $article->category(),
                'status' => $article->status(),
                'malleableData' => $article->malleableData(),
                'createdAt' => $article->createdAt()->format('Y-m-d H:i:s'),
                'updatedAt' => $article->updatedAt() ? $article->updatedAt()->format('Y-m-d H:i:s') : null,
            ];
        }, $articles);

        return new WP_REST_Response($data, 200);
    }

    public function createArticle(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params();
        
        try {
            $command = new CreateArticleCommand(
                $params['title'],
                $params['content'],
                $params['category'] ?? 'general',
                $params['status'] ?? 'draft',
                $params['malleableData'] ?? []
            );

            $this->createArticleHandler->handle($command);

            return new WP_REST_Response(['message' => 'Article created'], 201);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function updateArticle(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params();

        try {
            $command = new UpdateArticleCommand(
                $id,
                $params['title'],
                $params['content'],
                $params['category'] ?? 'general',
                $params['status'] ?? 'draft',
                $params['malleableData'] ?? []
            );

            $this->updateArticleHandler->handle($command);

            return new WP_REST_Response(['message' => 'Article updated'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    public function archiveArticle(WP_REST_Request $request): WP_REST_Response
    {
        $id = (int) $request->get_param('id');

        try {
            $command = new ArchiveArticleCommand($id);
            $this->archiveArticleHandler->handle($command);

            return new WP_REST_Response(['message' => 'Article archived'], 200);
        } catch (\Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }
}
