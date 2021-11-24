<?php

namespace App\Http\Middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\token;
use MongoDB\Client as Mongo;

use Closure;
use Illuminate\Http\Request;

class AuthMiddleware
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
        $curr_token = $request->bearerToken();
        if (empty($curr_token)) {
            return response([
                'message' => 'Please Enter Token',
            ]);
        } else {
            $collection = (new Mongo())->social_app->users;
            if ($collection->findOne(['token' => ['token_id' => $curr_token]])) {
                return $next($request);
            } else {
                return response([
                    'message' => 'Unauthenticated',
                ]);
            }
        }
    }
}
