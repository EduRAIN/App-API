<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Redis;

use Carbon\Carbon;

use Lcobucci\JWT\Parser;

use Sentry;

use App\Models\User;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot()
    {
        $this->app['auth']->viaRequest('token', function ($request){
            // Decode JWT
            try
            {
                $token = (new Parser())->parse($request->header('EDUrain-JWT') ? $request->header('EDUrain-JWT') : 'eyJhbGciOiJSUzI1NiIsImtpZCI6Ijc2MjNlMTBhMDQ1MTQwZjFjZmQ0YmUwNDY2Y2Y4MDM1MmI1OWY4MWUiLCJ0eXAiOiJKV1QifQ.eyJpc3MiOiJodHRwczovL3NlY3VyZXRva2VuLmdvb2dsZS5jb20vb3ZlcmNvbXBsaWNhdGVkLXRvZG8tYXBwLWM2ZjJiIiwiYXVkIjoib3ZlcmNvbXBsaWNhdGVkLXRvZG8tYXBwLWM2ZjJiIiwiYXV0aF90aW1lIjoxNTk0MTUyMDg5LCJ1c2VyX2lkIjoiMlBFc0gyN2haSE5kbjlyanlxOHVIWkZCanVJMyIsInN1YiI6IjJQRXNIMjdoWkhOZG45cmp5cTh1SFpGQmp1STMiLCJpYXQiOjE1OTQxNTIwODksImV4cCI6MTU5NDE1NTY4OSwiZW1haWwiOiJib2IyQGdtYWlsLmNvbSIsImVtYWlsX3ZlcmlmaWVkIjpmYWxzZSwiZmlyZWJhc2UiOnsiaWRlbnRpdGllcyI6eyJlbWFpbCI6WyJib2IyQGdtYWlsLmNvbSJdfSwic2lnbl9pbl9wcm92aWRlciI6InBhc3N3b3JkIn19.TS5IT50jS8AuovMHMQVdwzZaQ5OgEvj2CW7igTQKGfJ-FCxuTrH_v4TESprGeG8CGyQ3FQyGBWsMcewlU0ou6SpRfjv4RqnUzqtUW5_uiN-1xa08BkS11ZXzT8zVuMzjufcKmiZIo40xaEm4cgel5gBcW3VRNkj8m0FU4qF--UPI4ItwMPiOqmqjq-oGTXQWRmPO0olli3JB4tv8CaRtw3cBTy9ejUPj4tAHAJlsm2ZE_H4a1ss63-iHTErh9FAtV5bjIwTj_AGzIZ9rfx9oYkwwCq92I9Dvkgz67NFd9XU8_qeykgOoaQmkccSwo0ptieO_fglr-nu2IcY-B76gpw');
            }

            catch (\Exception $e)
            {
                return null;
            }

            // Look for Existing Account
            $user = User::where('firebase_sub', '=', $token->getClaim('sub'))
                        ->first();

            // Create Account on Not Found
            if (is_null($user))
            {
                $user = User::query()
                            ->create([
                                'firebase_sub'  =>  $token->getClaim('sub'),
                                'email'         =>  $token->getClaim('email')
                              ]);
            }

            return $user;
        });
    }
}
