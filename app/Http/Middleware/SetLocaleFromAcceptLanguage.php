<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleFromAcceptLanguage
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // return $next($request);
        $acceptLanguage = $request->header('Accept-Language');
        $availableLocales = config('app.available_locales', ['en']);

        if ($acceptLanguage) {
            // Parse Accept-Language header (e.g., "en-US,es;q=0.9")
            $locales = array_map(function ($locale) {
                // Extract primary language code (e.g., "en" from "en-US")
                return explode('-', trim(explode(';', $locale)[0]))[0];
            }, explode(',', $acceptLanguage));

            // Find the first supported locale
            foreach ($locales as $locale) {
                if (in_array($locale, $availableLocales)) {
                    App::setLocale($locale);
                    break;
                }
            }
        }

        // Fallback to default locale if no match
        if (!in_array(App::getLocale(), $availableLocales)) {
            App::setLocale(config('app.fallback_locale', 'en'));
        }

        return $next($request);
    }
}
