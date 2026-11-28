<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictStaffToCashierRegister
{
    private const CASHIER_ROUTES = [
        'cashier-dashboard',
        'cashier-dashboard.scan-report',
        'cashier-dashboard.sales-report',
        'logout',
        'pos.store-qr',
        'pos.import-cart',
        'pos.verification.create',
        'pos.verify-status',
        'pos.index',
        'pos.cart.contents',
        'pos.scan',
        'pos.cart.update',
        'pos.customer.select',
        'pos.checkout',
        'pos.shift.open',
        'products.image',
        'products.phone-scan.create',
        'products.phone-scan.mobile',
        'products.phone-scan.submit',
        'products.phone-scan.save',
        'products.phone-scan.result',
        'sales.show',
        'sales.shift.close',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $routeName = $request->route()?->getName();

        if ($user === null || $user->isAdmin() || $routeName === 'logout' || $request->is('logout')) {
            return $next($request);
        }

        if ($request->is('broadcasting/auth')) {
            return $next($request);
        }

        if (str_starts_with((string) $routeName, 'users.')) {
            if ($user->role === 'cashier' && $request->isMethod('GET') && ! $request->expectsJson()) {
                return to_route('cashier-dashboard');
            }

            abort(403, 'Only administrators can manage staff accounts.');
        }

        if ($user->role !== 'cashier') {
            return $next($request);
        }

        if (in_array($routeName, self::CASHIER_ROUTES, true)) {
            return $next($request);
        }

        if ($request->isMethod('GET') && ! $request->expectsJson()) {
            return to_route('cashier-dashboard');
        }

        abort(403, 'Staff accounts can only use the cashier register.');
    }
}
