<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Attribute\Route;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\Url;
use App\Core\Http\View;
use App\Exception\InsufficientStockException;
use App\Exception\NotFoundException;
use App\Middleware\RateLimit;
use App\Middleware\RequireRole;
use App\Middleware\RequireSession;
use App\Middleware\VerifyCsrf;
use App\Model\Product;
use App\Model\ProductQuery;
use App\Repository\CategoryRepositoryInterface;
use App\Repository\ProductRepositoryInterface;
use App\Service\AuthServiceInterface;
use App\Service\PurchaseServiceInterface;
use App\Validation\ProductValidator;
use PDOException;

final class ProductsController extends WebController
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private CategoryRepositoryInterface $categories,
        private PurchaseServiceInterface $purchases,
        private ProductValidator $validator,
        AuthServiceInterface $auth,
        View $view,
        Url $url,
    ) {
        parent::__construct($auth, $view, $url);
    }

    #[Route('/products', methods: 'GET')]
    public function index(Request $request): Response
    {
        $query = ProductQuery::from($request->query);

        return $this->render('products/index', [
            'title' => 'Products',
            'activeNav' => 'products',
            'pageResult' => $this->products->paginate($query),
            'availableCount' => $this->products->countInStock($query),
            'query' => $query,
            'categories' => $this->categories->all(),
        ]);
    }

    #[Route('/admin/products', methods: 'GET', middleware: [RequireSession::class, RequireRole::class], role: 'admin')]
    public function manage(Request $request): Response
    {
        if ($denied = $this->requireAdmin()) {
            return $denied;
        }

        $query = ProductQuery::from($request->query);
        $pageResult = $this->products->paginate($query);
        $stats = $this->productStockStats();

        return $this->renderAdmin('products/manage', [
            'title' => 'Products',
            'activeNav' => 'products',
            'pageResult' => $pageResult,
            'query' => $query,
            'categories' => $this->categories->all(),
            'inStockCount' => $stats['in'],
            'lowStockCount' => $stats['low'],
            'outStockCount' => $stats['out'],
        ]);
    }

    #[Route('/admin/products/export', methods: 'GET', middleware: [RequireSession::class, RequireRole::class], role: 'admin')]
    public function export(Request $request): Response
    {
        if ($denied = $this->requireAdmin()) {
            return $denied;
        }

        $query = ProductQuery::from($request->query);
        $rows = [['name', 'slug', 'price', 'quantity_available', 'reorder_point', 'category', 'status']];
        $page = 1;

        do {
            $result = $this->products->paginate(ProductQuery::from([
                'page' => $page,
                'per_page' => 24,
                'sort' => $query->sort,
                'dir' => $query->direction,
                'q' => $query->search,
                'category' => $query->category,
            ]));

            foreach ($result->items as $product) {
                $qty = (int) $product->quantityAvailable;
                $status = match ($product->stockStatus()) {
                    'out' => 'out_of_stock',
                    'low' => 'low_stock',
                    default => 'in_stock',
                };
                $rows[] = [
                    $product->name,
                    $product->slug,
                    $product->price,
                    (string) $qty,
                    (string) $product->reorderPoint,
                    $product->category !== '' ? $product->category : 'Drink',
                    $status,
                ];
            }

            $page++;
        } while ($page <= $result->lastPage());

        $csv = $this->toCsv($rows);
        $filename = 'products-' . date('Y-m-d') . '.csv';

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    #[Route('/admin/products/import', methods: 'POST', middleware: [RequireSession::class, RequireRole::class, VerifyCsrf::class], role: 'admin')]
    public function import(Request $request): Response
    {
        if ($denied = $this->requireAdmin()) {
            return $denied;
        }

        $file = $_FILES['csv'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->flash('Choose a CSV file to import.', 'error');

            return $this->redirect('/admin/products');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $name = strtolower((string) ($file['name'] ?? ''));
        if ($tmp === '' || !is_uploaded_file($tmp) || !str_ends_with($name, '.csv')) {
            $this->flash('Only CSV files are supported.', 'error');

            return $this->redirect('/admin/products');
        }

        if ((int) ($file['size'] ?? 0) > 1_000_000) {
            $this->flash('CSV file must be 1MB or smaller.', 'error');

            return $this->redirect('/admin/products');
        }

        $handle = fopen($tmp, 'rb');
        if ($handle === false) {
            $this->flash('Could not read the CSV file.', 'error');

            return $this->redirect('/admin/products');
        }

        $created = 0;
        $skipped = 0;
        $rowIndex = 0;
        $map = ['name' => 0, 'price' => 1, 'quantity_available' => 2, 'category' => -1, 'reorder_point' => -1];

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowIndex++;
                if ($row === [null] || $row === []) {
                    continue;
                }

                $cells = array_map(static fn ($value): string => trim((string) $value), $row);
                if ($rowIndex === 1 && $this->isCsvHeaderRow($cells)) {
                    $map = $this->csvColumnMap($cells);
                    continue;
                }

                if ($created + $skipped >= 500) {
                    break;
                }

                $category = ($map['category'] >= 0 ? ($cells[$map['category']] ?? '') : '');
                if ($category === '') {
                    $names = $this->categories->names();
                    $category = $names[0] ?? 'Drink';
                }
                if ($this->categories->findByName($category) === null && mb_strlen($category) <= 50) {
                    try {
                        $this->categories->create($category);
                    } catch (PDOException) {
                        // Validator will reject unknown/invalid categories.
                    }
                }

                $reorderPoint = ($map['reorder_point'] >= 0 ? ($cells[$map['reorder_point']] ?? '') : '');
                if ($reorderPoint === '') {
                    $reorderPoint = '5';
                }

                $input = [
                    'name' => $cells[$map['name']] ?? '',
                    'price' => $cells[$map['price']] ?? '',
                    'quantity_available' => $cells[$map['quantity_available']] ?? '',
                    'reorder_point' => $reorderPoint,
                    'category' => $category,
                ];
                $errors = $this->validator->validate($input);
                if ($errors !== []) {
                    $skipped++;
                    continue;
                }

                $this->products->create(
                    $input['name'],
                    $this->validator->normalizePrice($input['price']),
                    (int) $input['quantity_available'],
                    $input['category'],
                    (int) $input['reorder_point'],
                );
                $created++;
            }
        } finally {
            fclose($handle);
        }

        if ($created === 0) {
            $this->flash('No valid products were imported.', 'error');
        } else {
            $message = $created . ' product' . ($created === 1 ? '' : 's') . ' imported.';
            if ($skipped > 0) {
                $message .= ' ' . $skipped . ' row' . ($skipped === 1 ? '' : 's') . ' skipped.';
            }
            $this->flash($message);
        }

        return $this->redirect('/admin/products');
    }

    #[Route('/admin/products/create', methods: 'GET', middleware: [RequireSession::class, RequireRole::class], role: 'admin')]
    public function create(Request $request): Response
    {
        if ($denied = $this->requireAdmin()) {
            return $denied;
        }

        return $this->productForm(null, 'Add product', $this->url->path('/admin/products'));
    }

    #[Route('/admin/products', methods: 'POST', middleware: [RequireSession::class, RequireRole::class, VerifyCsrf::class], role: 'admin')]
    public function store(Request $request): Response
    {
        if ($denied = $this->requireAdmin()) {
            return $denied;
        }

        $input = $this->productInput($request);
        $errors = $this->validator->validate($input);
        $imageError = $this->validateProductImage($_FILES['image'] ?? null);
        if ($imageError !== null) {
            $errors['image'] = $imageError;
        }
        if ($errors !== []) {
            return $this->productForm(null, 'Add product', $this->url->path('/admin/products'), $input, $errors, 422);
        }

        $product = $this->products->create(
            $input['name'],
            $this->validator->normalizePrice($input['price']),
            (int) $input['quantity_available'],
            $input['category'],
            (int) $input['reorder_point'],
        );

        $imageFile = $_FILES['image'] ?? null;
        $storeError = store_product_image($product->slug, is_array($imageFile) ? $imageFile : null);
        if ($storeError !== null) {
            $this->flash('Product created, but image was not saved: ' . $storeError, 'error');
        } else {
            $this->flash('Product created.');
        }

        return $this->redirect('/admin/products');
    }

    #[Route('/products/{slug}', methods: 'GET')]
    public function show(Request $request): Response
    {
        $product = $this->products->findBySlug((string) $request->route('slug'));
        if ($product === null) {
            return $this->notFound();
        }

        return $this->render('products/show', [
            'title' => $product->name,
            'activeNav' => 'products',
            'product' => $product,
        ]);
    }

    #[Route('/admin/products/{slug}/edit', methods: 'GET', middleware: [RequireSession::class, RequireRole::class], role: 'admin')]
    public function edit(Request $request): Response
    {
        if ($denied = $this->requireAdmin()) {
            return $denied;
        }

        $product = $this->products->findBySlug((string) $request->route('slug'));
        if ($product === null) {
            return $this->notFound();
        }

        return $this->productForm(
            $product,
            'Edit product',
            $this->url->path('/admin/products/' . $product->slug . '/update'),
        );
    }

    #[Route('/admin/products/{slug}/update', methods: 'POST', middleware: [RequireSession::class, RequireRole::class, VerifyCsrf::class], role: 'admin')]
    public function update(Request $request): Response
    {
        if ($denied = $this->requireAdmin()) {
            return $denied;
        }

        $product = $this->products->findBySlug((string) $request->route('slug'));
        if ($product === null) {
            return $this->notFound();
        }

        $input = $this->productInput($request);
        $errors = $this->validator->validate($input);
        $imageError = $this->validateProductImage($_FILES['image'] ?? null);
        if ($imageError !== null) {
            $errors['image'] = $imageError;
        }
        if ($errors !== []) {
            return $this->productForm(
                $product,
                'Edit product',
                $this->url->path('/admin/products/' . $product->slug . '/update'),
                $input,
                $errors,
                422,
            );
        }

        $product->name = $input['name'];
        $product->price = $this->validator->normalizePrice($input['price']);
        $product->quantityAvailable = (int) $input['quantity_available'];
        $product->category = $input['category'];
        $product->reorderPoint = (int) $input['reorder_point'];
        $this->products->update($product);

        $imageFile = $_FILES['image'] ?? null;
        $storeError = store_product_image($product->slug, is_array($imageFile) ? $imageFile : null);
        if ($storeError !== null) {
            $this->flash('Product updated, but image was not saved: ' . $storeError, 'error');
        } else {
            $this->flash('Product updated.');
        }

        return $this->redirect('/admin/products');
    }

    #[Route('/admin/products/{slug}/restock', methods: 'POST', middleware: [RequireSession::class, RequireRole::class, VerifyCsrf::class], role: 'admin')]
    public function restock(Request $request): Response
    {
        if ($denied = $this->requireAdmin()) {
            return $denied;
        }

        $product = $this->products->findBySlug((string) $request->route('slug'));
        if ($product === null) {
            return $this->notFound();
        }

        $amount = trim((string) $request->input('amount', ''));
        if (preg_match('/^\d+$/', $amount) !== 1 || (int) $amount < 1) {
            $this->flash('Enter a stock amount of at least 1.', 'error');

            return $this->redirect('/admin/products/' . $product->slug . '/edit');
        }

        $add = (int) $amount;
        if ($add > 10000) {
            $this->flash('You can add at most 10,000 units at a time.', 'error');

            return $this->redirect('/admin/products/' . $product->slug . '/edit');
        }

        $this->products->adjustQuantity($product->id, $add);
        $newQty = $product->quantityAvailable + $add;
        $this->flash('Added +' . $add . ' to ' . $product->name . '. Stock is now ' . $newQty . '.');

        return $this->redirect('/admin/products');
    }

    #[Route('/admin/products/{slug}/delete', methods: 'POST', middleware: [RequireSession::class, RequireRole::class, VerifyCsrf::class], role: 'admin')]
    public function destroy(Request $request): Response
    {
        if ($denied = $this->requireAdmin()) {
            return $denied;
        }

        $slug = (string) $request->route('slug');

        try {
            $this->products->deleteBySlug($slug);
            delete_product_image($slug);
            $this->flash('Product deleted.');
        } catch (PDOException $exception) {
            $message = (string) $exception->getCode() === '23000'
                ? 'This product has purchases and cannot be deleted.'
                : 'The product could not be deleted.';
            $this->flash($message, 'error');
        }

        return $this->redirect('/admin/products');
    }

    #[Route('/products/{slug}/purchase', methods: 'GET', middleware: [RequireSession::class])]
    public function purchaseForm(Request $request): Response
    {
        if ($redirect = $this->requireUser()) {
            return $redirect;
        }

        $slug = (string) $request->route('slug');
        $product = $this->products->findBySlug($slug);
        if ($product === null) {
            return $this->notFound();
        }

        return $this->redirect('/products/' . $slug);
    }

    #[Route('/products/{slug}/purchase', methods: 'POST', middleware: [RequireSession::class, RateLimit::class, VerifyCsrf::class])]
    public function purchase(Request $request): Response
    {
        if ($redirect = $this->requireUser()) {
            return $redirect;
        }

        $slug = (string) $request->route('slug');
        $product = $this->products->findBySlug($slug);
        if ($product === null) {
            return $this->notFound();
        }

        $quantity = trim((string) $request->input('quantity', ''));
        $error = $this->validator->validateQuantity($quantity, $product->quantityAvailable);
        if ($error !== null) {
            return $this->purchaseError($product, $quantity, $error);
        }

        $qty = (int) $quantity;

        try {
            $transaction = $this->purchases->purchase($this->auth->user(), $slug, $qty);
        } catch (NotFoundException) {
            return $this->notFound();
        } catch (InsufficientStockException $exception) {
            return $this->purchaseError($product, $quantity, $exception->getMessage());
        }

        $_SESSION['purchase_success'] = [
            'name' => $product->name,
            'slug' => $product->slug,
            'quantity' => $qty,
            'unit_price' => $product->price,
            'total' => $transaction->total,
            'image' => product_image($product->slug),
        ];

        return $this->redirect('/products/' . $slug);
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

    /** @param array<string, mixed>|null $file */
    private function validateProductImage(?array $file): ?string
    {
        if ($file === null || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ((int) ($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return 'Image upload failed.';
        }

        if ((int) ($file['size'] ?? 0) > 2_000_000) {
            return 'Image must be 2MB or smaller.';
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return 'Image upload failed.';
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($tmp);
        if (!in_array($mime, ['image/png', 'image/jpeg', 'image/webp'], true)) {
            return 'Use a PNG, JPG, or WebP image.';
        }

        return null;
    }

    /** @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function productForm(
        ?Product $product,
        string $heading,
        string $action,
        array $old = [],
        array $errors = [],
        int $status = 200,
    ): Response {
        return $this->renderAdmin('products/form', [
            'title' => $heading,
            'activeNav' => 'products',
            'heading' => $heading,
            'action' => $action,
            'product' => $product,
            'categories' => $this->categories->all(),
            'old' => $old,
            'errors' => $errors,
        ], $status);
    }

    private function purchaseError(Product $product, string $quantity, string $error): Response
    {
        return $this->render('products/show', [
            'title' => $product->name,
            'activeNav' => 'products',
            'product' => $product,
            'errors' => ['quantity' => $error],
            'old' => ['quantity' => $quantity],
        ], 422);
    }

    /** @param list<list<string>> $rows */
    private function toCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+b');
        if ($handle === false) {
            return '';
        }

        fwrite($handle, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    /** @param list<string> $cells */
    private function isCsvHeaderRow(array $cells): bool
    {
        $normalized = array_map(static fn (string $value): string => strtolower(str_replace([' ', '-'], '_', $value)), $cells);

        return in_array('name', $normalized, true) && in_array('price', $normalized, true);
    }

    /**
     * @param list<string> $cells
     * @return array{name: int, price: int, quantity_available: int, category: int, reorder_point: int}
     */
    private function csvColumnMap(array $cells): array
    {
        $map = ['name' => 0, 'price' => 1, 'quantity_available' => 2, 'category' => -1, 'reorder_point' => -1];
        foreach ($cells as $index => $cell) {
            $key = strtolower(str_replace([' ', '-'], '_', $cell));
            if ($key === 'name') {
                $map['name'] = $index;
            } elseif ($key === 'price') {
                $map['price'] = $index;
            } elseif (in_array($key, ['quantity_available', 'quantity', 'stock', 'qty'], true)) {
                $map['quantity_available'] = $index;
            } elseif ($key === 'category') {
                $map['category'] = $index;
            } elseif (in_array($key, ['reorder_point', 'reorder', 'reorder_level'], true)) {
                $map['reorder_point'] = $index;
            }
        }

        return $map;
    }

    /** @return array{in: int, low: int, out: int} */
    private function productStockStats(): array
    {
        $in = 0;
        $low = 0;
        $out = 0;
        $page = 1;

        do {
            $result = $this->products->paginate(ProductQuery::from([
                'page' => $page,
                'per_page' => 24,
            ]));

            foreach ($result->items as $product) {
                match ($product->stockStatus()) {
                    'out' => $out++,
                    'low' => $low++,
                    default => $in++,
                };
            }

            $page++;
        } while ($page <= $result->lastPage());

        return ['in' => $in, 'low' => $low, 'out' => $out];
    }

    private function notFound(): Response
    {
        return $this->render('errors/404', [
            'title' => 'Page not found',
            'message' => 'That product is missing or no longer available.',
            'layout' => 'errors/layout',
        ], 404);
    }
}
