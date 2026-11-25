<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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
        $session = Cache::get($this->cacheKey($token));
        abort_unless(
            is_array($session)
                && ($session['status'] ?? null) === 'waiting'
                && now()->getTimestamp() < ($session['expires_at'] ?? 0),
            410,
        );

        return view('products.phone-scan', [
            'submitUrl' => route('products.phone-scan.submit', $token),
        ]);
    }

    public function submit(Request $request, string $token): JsonResponse
    {
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

    private function cacheKey(string $token): string
    {
        return 'product-phone-scan:'.$token;
    }
}
