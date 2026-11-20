<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Core\Attribute\Route;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Exception\InsufficientStockException;
use App\Exception\NotFoundException;
use App\Middleware\RateLimit;
use App\Middleware\RequireJwt;
use App\Middleware\RequireRole;
use App\Model\ProductQuery;
use App\Repository\ProductRepositoryInterface;
use App\Service\PurchaseServiceInterface;
use App\Validation\ProductValidator;

final class ProductsApiController
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private PurchaseServiceInterface $purchases,
        private ProductValidator $validator,
    ) {
    }

    #[Route('/api/products', methods: 'GET')]
    public function index(Request $request): Response
    {
        $query = ProductQuery::from($request->query);
        $page = $this->products->paginate($query);

        return Response::json([
            'data' => array_map(static fn ($product) => $product->toArray(), $page->items),
            'meta' => [
                'page' => $page->page,
                'per_page' => $page->perPage,
                'total' => $page->total,
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    #[Route('/api/products/{slug}', methods: 'GET')]
    public function show(Request $request): Response
    {
        $product = $this->products->findBySlug((string) $request->route('slug'));
        if ($product === null) {
            return Response::json(['message' => 'Product not found.'], 404);
        }

        return Response::json(['data' => $product->toArray()]);
    }

    #[Route('/api/products', methods: 'POST', middleware: [RequireJwt::class, RequireRole::class], role: 'admin')]
    public function store(Request $request): Response
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $input = $this->productInput($request);
        $errors = $this->validator->validate($input);
        if ($errors !== []) {
            return $this->invalid($errors);
        }

        $product = $this->products->create(
            $input['name'],
            $this->validator->normalizePrice($input['price']),
            (int) $input['quantity_available'],
            $input['category'],
            (int) $input['reorder_point'],
        );

        return Response::json(['data' => $product->toArray()], 201);
    }

    #[Route('/api/products/{slug}', methods: 'PUT', middleware: [RequireJwt::class, RequireRole::class], role: 'admin')]
    public function update(Request $request): Response
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $product = $this->products->findBySlug((string) $request->route('slug'));
        if ($product === null) {
            return Response::json(['message' => 'Product not found.'], 404);
        }

        $input = $this->productInput($request);
        $errors = $this->validator->validate($input);
        if ($errors !== []) {
            return $this->invalid($errors);
        }

        $product->name = $input['name'];
        $product->price = $this->validator->normalizePrice($input['price']);
        $product->quantityAvailable = (int) $input['quantity_available'];
        $product->category = $input['category'];
        $product->reorderPoint = (int) $input['reorder_point'];
        $this->products->update($product);

        return Response::json(['data' => $product->toArray()]);
    }

    #[Route('/api/products/{slug}', methods: 'DELETE', middleware: [RequireJwt::class, RequireRole::class], role: 'admin')]
    public function destroy(Request $request): Response
    {
        if ($denied = $this->requireAdmin($request)) {
            return $denied;
        }

        $slug = (string) $request->route('slug');
        if ($this->products->findBySlug($slug) === null) {
            return Response::json(['message' => 'Product not found.'], 404);
        }

        $this->products->deleteBySlug($slug);

        return Response::empty();
    }

    #[Route('/api/products/{slug}/purchase', methods: 'POST', middleware: [RequireJwt::class, RateLimit::class])]
    public function purchase(Request $request): Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::json(['message' => 'Authentication is required.'], 401);
        }

        $slug = (string) $request->route('slug');
        $product = $this->products->findBySlug($slug);
        if ($product === null) {
            return Response::json(['message' => 'Product not found.'], 404);
        }

        $quantity = trim((string) $request->input('quantity', ''));
        $error = $this->validator->validateQuantity($quantity, $product->quantityAvailable);
        if ($error !== null) {
            return $this->invalid(['quantity' => $error]);
        }

        try {
            $transaction = $this->purchases->purchase($user, $slug, (int) $quantity);
        } catch (NotFoundException) {
            return Response::json(['message' => 'Product not found.'], 404);
        } catch (InsufficientStockException $exception) {
            return Response::json(['message' => $exception->getMessage()], 422);
        }

        return Response::json([
            'data' => [
                'id' => $transaction->id,
                'quantity' => $transaction->quantity,
                'unit_price' => $transaction->unitPrice,
                'total' => $transaction->total,
            ],
        ], 201);
    }

    private function requireAdmin(Request $request): ?Response
    {
        $user = $request->user();
        if ($user === null) {
            return Response::json(['message' => 'Authentication is required.'], 401);
        }
        if (!$user->isAdmin()) {
            return Response::json(['message' => 'This action requires an admin.'], 403);
        }

        return null;
    }

    /** @return array{name: string, price: string, quantity_available: string, reorder_point: string, category: string} */
    private function productInput(Request $request): array
    {
        return [
            'name' => trim((string) $request->input('name', '')),
            'price' => trim((string) $request->input('price', '')),
            'quantity_available' => trim((string) $request->input('quantity_available', '')),
            'reorder_point' => trim((string) $request->input('reorder_point', '')),
            'category' => trim((string) $request->input('category', '')),
        ];
    }

    /** @param array<string, string> $errors */
    private function invalid(array $errors): Response
    {
        return Response::json([
            'message' => 'The given data was invalid.',
            'errors' => $errors,
        ], 422);
    }
}
