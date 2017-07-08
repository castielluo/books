<?php

namespace App\Api\V1\Controllers;

use EasyWeChat\Foundation\Application;
use EasyWeChat\Message\Image;
use EasyWeChat\Message\News;
use EasyWeChat\Message\Text;

use Config;
use App\User;
use Tymon\JWTAuth\JWTAuth;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class WechatController extends Controller
{
    public function checkauth(Request $request)
    {
       
        Log::info('request arrived');
        $wechat = new Application(config('wechat'));
        $wechat->server->setMessageHandler(function($message){
            switch ($message->MsgType) {
                case 'event':
                    switch ($message->Event) {
                        case 'subscribe':
                            return '收到事件消息'.$message['FromUserName'];
                            break;
                        default:
                            return '收到事件消息'.$message['FromUserName'];
                            break;
                    }
                case 'text':
                    if($message->Content=='好'){
                        $news= new News();
                        $news->title='你说好就好啊？我不是很没面？';
                        $news->description='你说好就好啊？我不是很没面？';
                        $news->url='http://mp.weixin.qq.com/s/bkQwSluZoZ484dFAIffmvQ';
                        $news->image='http://ww1.sinaimg.cn/mw690/721b24cdgy1ffxq1bf7wpj20er09d117.jpg';

                        $news1= new News();
                        $news1->title='你说好就好啊？我不是很没面？';
                        $news1->description='你说好就好啊？我不是很没面？';
                        $news1->url='http://mp.weixin.qq.com/s/bkQwSluZoZ484dFAIffmvQ';
                        $news1->image='http://ww1.sinaimg.cn/mw690/721b24cdgy1ffxq1bf7wpj20er09d117.jpg';
                        return [$news,$news1];
                    }else{
                        return '说鸡不说巴，文明你我他';
                    }
                    break;
                case 'image':
                    return new Image(['media_id' => $message['MediaId']]);
                    break;
                case 'voice':
                    return '收到语音消息'.$message['FromUserName'];
                    break;
                case 'video':
                    return '收到视频消息'.$message['FromUserName'];
                    break;
                case 'location':
                    return '收到坐标消息'.$message['FromUserName'];
                    break;
                case 'link':
                    return '收到链接消息'.$message['FromUserName'];
                    break;
                // ... 其它消息
                default:
                    return '收到其它消息'.$message['FromUserName'];
                    break;
            }
        });

        $response = $wechat->server->serve();
        // 将响应输出
        //$response->send(); // Laravel 里请使用：return $response;
        return $response;

    }



}
