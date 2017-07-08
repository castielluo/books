<?php

namespace App\Http\Middleware;

use Closure;

use EasyWeChat\Foundation\Application;
use Log;
use Overtrue\Socialite\Config;


class WeChatOAuthAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     * @param string|null              $scopes
     *
     * @return mixed
     */
    public function handle($request, Closure $next, $scopes = null)
    {
        //公众号类型
        $no_public_type = (substr($request->getRequestUri(),1,1));///1/xxxx
        //Log::info("test--->".$no_public_type."  ".$request->getRequestUri());
        if($no_public_type == 1){
            if(empty(session("one"))){
                $app = new Application(config('wechat'));

                $response = $app->oauth->scopes(['snsapi_userinfo'])->redirect("https://app.tpmission.com/save/oauth/".base64_encode($request->getRequestUri()));

                return $response;
            }
        }

        return $next($request);
    }


}
