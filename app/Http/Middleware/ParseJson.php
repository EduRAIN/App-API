<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ParseJson
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isJson())
        {
            $data = $request->json()->all();
            $request->request->replace(is_array($data) ? $data : []);
        }

        return $next($request);
    }
}
