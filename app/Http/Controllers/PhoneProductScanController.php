<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PhoneProductScanController extends Controller
{
    private const SESSION_TTL_MINUTES = 5;

    public function create(Request $request): JsonResponse
    {
        do {
            $token = Str::random(40);
        } while (Cache::has($this->cacheKey($token)));

        Cache::put(
            $this->cacheKey($token),
            ['status' => 'waiting'],
            now()->addMinutes(self::SESSION_TTL_MINUTES),
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
        abort_unless(Cache::get($this->cacheKey($token)) === ['status' => 'waiting'], 410);

        return view('products.phone-scan', [
            'submitUrl' => route('products.phone-scan.submit', $token),
        ]);
    }

    public function submit(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:100'],
        ]);

        $session = Cache::get($this->cacheKey($token));
        if (! is_array($session) || ($session['status'] ?? null) !== 'waiting') {
            return response()->json(['message' => 'This phone scan session has expired or was already used.'], 410);
        }

        Cache::put(
            $this->cacheKey($token),
            [
                'status' => 'complete',
                'code' => strtoupper(trim($validated['code'])),
            ],
            now()->addMinutes(self::SESSION_TTL_MINUTES),
        );

        return response()->json(['message' => 'Product code sent to the registration screen.']);
    }

    public function result(string $token): JsonResponse
    {
        $session = Cache::get($this->cacheKey($token));
        if (! is_array($session)) {
            return response()->json(['message' => 'This phone scan session has expired.'], 410);
        }

        if (($session['status'] ?? null) !== 'complete') {
            return response()->json(['status' => 'waiting']);
        }

        Cache::forget($this->cacheKey($token));

        return response()->json([
            'status' => 'complete',
            'code' => $session['code'],
        ]);
    }

    private function cacheKey(string $token): string
    {
        return 'product-phone-scan:'.$token;
    }
}
