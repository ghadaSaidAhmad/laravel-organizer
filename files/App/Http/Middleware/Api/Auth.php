<?php
namespace App\Http\Middleware\Api;

use Closure;
use Auth as BaseAuth;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class Auth 
{
    /**
     * Application key
     * 
     * @var string
     */
    protected $apiKey;

    /**
     * Application type
     * 
     * @var string
     */
    protected $appType;

    /**
     * Routes that does not have permissions in admin app
     * 
     * @var array
     */
    protected $ignoredAdminRoutes = ["/api/admin/login", '/api/admin/logout'];

    /**
     * Routes that does not have permissions in site app
     * 
     * @var array
     */
    protected $ignoredSiteRoutes = ['/api/login', '/api/register', '/api/logout'];

    /**
     * {@inheritDoc}
     */
    public function handle(Request $request, Closure $next)
    {
        $this->apiKey = config('app.api-key');

        // set default auth
        if (Str::contains($request->uri(), '/admin')) {
            $this->appType = 'admin';
        } else {
            $this->appType = 'site';
        }

        $guardInfo = config('auth.guards.' . $this->appType);
        
        config([
            'auth.defaults.guard' => $this->appType, 
            'app.type' => $this->appType,
            'app.user-repo' => $guardInfo['repository'] ?? 'users',
            'app.user-type' => $guardInfo['repository'] ?? 'users',
        ]);

        return $this->middleware($request, $next); 
    }

    /**
     * {@inheritDoc}
     */
    protected function middleware(Request $request, Closure $next)
    {   
        $ignoredRoutes = $this->appType == 'admin' ? $this->ignoredAdminRoutes : $this->ignoredSiteRoutes;

        if (in_array($request->uri(), $ignoredRoutes)) {
            if ($request->authorizationValue() !== $this->apiKey) {
                return response('Invalid Request I', 400);
            }

            return $next($request);
        } else {
            // validate if and only if the authorization access token is sent
            list($tokenType, $accessToken) = $request->authorization();
            
            if ($tokenType == 'Bearer') {
                $user = repo(config('app.user-repo'))->getByAccessToken($accessToken);

                if ($user) {
                    BaseAuth::login($user);
    
                    return $next($request);
                } else {
                    return response('Invalid Request II', 400);
                }
            } else {
                if ($accessToken != $this->apiKey) {
                    return response('Invalid Request III', 400);
                }
                return $next($request);
            }
        }
    }
}