<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocaleMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            App::setLocale($request->user()->locale);
        }

        return $next($request);
    }
}