<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Use the visitor's chosen locale, or the best match from the browser.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = array_keys(config('app.supported_locales'));

        $locale = $request->session()->get('locale');

        if (! in_array($locale, $supportedLocales, true)) {
            $locale = $this->browserLocale($request, $supportedLocales) ?? config('app.locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }

    /**
     * Find the first browser language that the application supports.
     *
     * @param  list<string>  $supportedLocales
     */
    private function browserLocale(Request $request, array $supportedLocales): ?string
    {
        foreach ($request->getLanguages() as $language) {
            $primaryLanguage = strtolower(strtok($language, '_'));

            if (in_array($primaryLanguage, $supportedLocales, true)) {
                return $primaryLanguage;
            }
        }

        return null;
    }
}
