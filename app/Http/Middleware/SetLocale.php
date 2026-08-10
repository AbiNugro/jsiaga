<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    private const SUPPORTED = ['id', 'en', 'ko'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->session()->get('locale', 'id');

        app()->setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : 'id');

        return $next($request);
    }
}
