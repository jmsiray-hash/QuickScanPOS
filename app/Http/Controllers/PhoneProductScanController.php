<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class PhoneProductScanController extends Controller
{
    private const SESSION_TTL_MINUTES = 5;

    public function create(Request $request): JsonResponse
    {
        do {
            $token = Str::random(40);
        } while (Cache::has($this->cacheKey($token)));

        $expiresAt = now()->addMinutes(self::SESSION_TTL_MINUTES);
        Cache::put(
            $this->cacheKey($token),
            ['status' => 'waiting', 'codes' => [], 'expires_at' => $expiresAt->getTimestamp()],
            $expiresAt,
        );

        $mobilePath = route('products.phone-scan.mobile', $token, false);

        return response()->json([
            'mobile_url' => rtrim($request->root(), '/').$mobilePath,
            'result_url' => route('products.phone-scan.result', $token),
            'expires_in' => self::SESSION_TTL_MINUTES * 60,
        ]);
    }

    public function show(string $token): View
    {
        $this->activeSession($token);

        return view('products.phone-scan', [
            'submitUrl' => route('products.phone-scan.submit', $token),
            'saveUrl' => route('products.phone-scan.save', $token),
        ]);
    }

    public function submit(Request $request, string $token): JsonResponse
    {
        $this->activeSession($token);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
            'photo' => ['nullable', 'string', 'max:2800000'],
        ]);
        $photoData = isset($validated['photo']) ? $this->validatedPhoto($validated['photo']) : null;

        $session = Cache::get($this->cacheKey($token));
        if (
            ! is_array($session)
            || ($session['status'] ?? null) !== 'waiting'
            || now()->getTimestamp() >= ($session['expires_at'] ?? 0)
        ) {
            Cache::forget($this->cacheKey($token));

            return response()->json(['message' => 'This phone scan session has expired. Start a new session from the cashier.'], 410);
        }

        $codes = $session['codes'] ?? [];
        if (count($codes) >= 10) {
            return response()->json(['message' => 'The cashier has not received the queued scans yet. Please wait a moment.'], 429);
        }
        $codes[] = [
            'code' => strtoupper(trim($validated['code'])),
            'photo_data' => $photoData,
        ];
        $session['codes'] = $codes;
        Cache::put(
            $this->cacheKey($token),
            $session,
            \Illuminate\Support\Carbon::createFromTimestamp($session['expires_at']),
        );

        return response()->json([
            'message' => $photoData === null
                ? 'Product code sent. Scan the next item when ready.'
                : 'Product code and photo sent. Scan the next item when ready.',
        ]);
    }

    public function saveProduct(Request $request, string $token): JsonResponse
    {
        $this->activeSession($token);
        $request->merge([
            'barcode' => strtoupper(trim((string) $request->input('barcode'))),
        ]);
        $validated = $request->validate([
            'barcode' => ['required', 'string', 'max:100', Rule::unique('products', 'barcode')],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'photo' => ['required', 'string', 'max:2800000'],
        ]);

        $photoData = $this->validatedPhoto($validated['photo']);
        $imageBytes = base64_decode(substr($photoData, strpos($photoData, ',') + 1), true);
        if ($imageBytes === false) {
            throw ValidationException::withMessages(['photo' => 'The product photo could not be decoded. Retake it and try again.']);
        }

        $image = getimagesizefromstring($imageBytes);
        if ($image === false) {
            throw ValidationException::withMessages(['photo' => 'The product photo is invalid. Retake it and try again.']);
        }

        $extension = match ($image['mime']) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw ValidationException::withMessages(['photo' => 'Use a JPEG, PNG, or WebP product photo.']),
        };
        $imagePath = 'products/'.Str::uuid().'.'.$extension;
        if (! Storage::disk('public')->put($imagePath, $imageBytes)) {
            throw new RuntimeException('The product photo could not be saved. Check that public storage is writable.');
        }

        try {
            $product = Product::create([
                'barcode' => $validated['barcode'],
                'name' => $validated['name'],
                'category' => $validated['category'] ?? null,
                'image_path' => $imagePath,
                'cost_price' => $validated['cost_price'],
                'selling_price' => $validated['selling_price'],
                'stock_quantity' => $validated['stock_quantity'],
                'is_active' => true,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($imagePath);
            throw $exception;
        }

        return response()->json([
            'message' => 'Product details and photo saved to inventory.',
            'product' => [
                'id' => $product->id,
                'barcode' => $product->barcode,
                'name' => $product->name,
                'selling_price' => (float) $product->selling_price,
                'stock_quantity' => $product->stock_quantity,
                'image_url' => route('products.image', $product).'?v='.hash('sha256', $product->image_path),
            ],
        ], 201);
    }

    public function result(string $token): JsonResponse
    {
        $session = Cache::get($this->cacheKey($token));
        if (
            ! is_array($session)
            || now()->getTimestamp() >= ($session['expires_at'] ?? 0)
        ) {
            Cache::forget($this->cacheKey($token));

            return response()->json(['message' => 'This phone scan session has expired.'], 410);
        }

        $codes = $session['codes'] ?? [];
        if ($codes === []) {
            return response()->json(['status' => 'waiting']);
        }

        $scan = array_shift($codes);
        $session['codes'] = $codes;
        Cache::put(
            $this->cacheKey($token),
            $session,
            \Illuminate\Support\Carbon::createFromTimestamp($session['expires_at']),
        );

        $result = [
            'status' => 'complete',
            'code' => is_array($scan) ? $scan['code'] : $scan,
        ];
        if (is_array($scan) && $scan['photo_data'] !== null) {
            $result['photo_data'] = $scan['photo_data'];
        }

        return response()->json($result);
    }

    private function validatedPhoto(string $photoData): string
    {
        if (! preg_match(
            '/\A(data:image\/(?:jpeg|png|webp);base64,)([A-Za-z0-9+\/]+={0,2})\z/',
            $photoData,
            $matches,
        )) {
            throw ValidationException::withMessages([
                'photo' => 'Capture a valid JPEG, PNG, or WebP product photo.',
            ]);
        }

        $contents = base64_decode($matches[2], true);
        $image = $contents === false ? false : getimagesizefromstring($contents);
        if (
            $contents === false
            || strlen($contents) > 2 * 1024 * 1024
            || $image === false
            || $image['mime'] !== substr($matches[1], 5, -8)
            || ($image[0] * $image[1]) > 16000000
        ) {
            throw ValidationException::withMessages([
                'photo' => 'The product photo is invalid or too large. Retake the photo and try again.',
            ]);
        }

        return $matches[1].base64_encode($contents);
    }

    private function activeSession(string $token): array
    {
        $session = Cache::get($this->cacheKey($token));
        if (
            ! is_array($session)
            || ($session['status'] ?? null) !== 'waiting'
            || now()->getTimestamp() >= ($session['expires_at'] ?? 0)
        ) {
            Cache::forget($this->cacheKey($token));
            abort(410, 'This phone product session has expired. Start a new session from the cashier.');
        }

        return $session;
    }

    private function cacheKey(string $token): string
    {
        return 'product-phone-scan:'.$token;
    }
}
