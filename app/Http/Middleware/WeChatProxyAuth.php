<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class WeChatProxyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $gateway = config('wechat.gateway');

        if (!Auth::guard('web')->check()) {
            $ticket = $request->query('ticket');

            if (!$ticket) {
                return redirect($gateway . '/auth/redirect?redirect=' . urlencode($request->fullUrl()));
            }

            $response = Http::get($gateway . '/api/auth/verify', [
                'ticket' => $ticket,
            ]);

            if (!$response->successful()) {
                abort(500, 'OAuth verification failed');
            }

            $data = $response->json();

            if (!isset($data['openid'])) {
                abort(500, 'Invalid OAuth response');
            }

            $user = User::where('openid', $data['openid'])->first();

            if (!$user) {
                $user = User::create([
                    'name' => $data['nickname'] ?? 'User_' . substr($data['openid'], -6),
                    'openid' => $data['openid'],
                    'password' => bcrypt(uniqid()),
                ]);
            }

            Auth::guard('web')->login($user);
            $request->session()->save();

            return redirect()->to($request->path());
        }

        return $next($request);
    }
}
