<?php
namespace App\Http\Middleware;

use Carbon\Carbon;

use Closure;

class ProductionEnvironment
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Update $_SERVER with Remote Client's Actual IP
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP']))
        {
            $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        // Application Monitoring
        // $I = new InstrumentalAgent();
        // $I->setApiKey($_ENV['API_KEY__INSTRUMENTAL']);
        // $I->gauge('app.web.request', 0);

        return $response;
    }
}
