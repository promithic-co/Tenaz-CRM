<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateTwilioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('testing')) {
            return $next($request);
        }

        $authToken = config('services.twilio.token');

        $validator = new \Twilio\Security\RequestValidator($authToken);
        $signature = $request->header('X-Twilio-Signature', '');
        $url = config('app.url').$request->getRequestUri();

        if (! $validator->validate($signature, $url, $request->post())) {
            abort(403, 'Invalid Twilio signature');
        }

        return $next($request);
    }
}
