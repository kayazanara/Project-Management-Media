<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

class ForceDailyLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $loginAt = session('login_at');

            if (!$loginAt) {
                session(['login_at' => now()->toDateTimeString()]);
            } else {
                $loginAt = Carbon::parse($loginAt);

                $next7AM = $loginAt->copy();
                if ($next7AM->hour >= 7) {
                    $next7AM->addDay()->setTime(7, 0, 0);
                } else {
                    $next7AM->setTime(7, 0, 0);
                }

                if (now()->greaterThanOrEqualTo($next7AM)) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect('/login')->withErrors([
                        'email' => 'Sesi login berakhir. Silakan login ulang.',
                    ]);
                }
            }
        }

        return $next($request);
    }
}