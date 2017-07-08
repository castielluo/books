<?php
namespace App\Http\Controllers\WeChat;


use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Business;
use App\Models\SalesPersion;
use App\Models\SalesPersionDetail;
use App\Models\Salespersons;
use App\Models\User;
use Config;
use EasyWeChat\Foundation\Application;
use EasyWeChat\Message\Image;
use EasyWeChat\Message\News;
use EasyWeChat\Message\Raw;
use EasyWeChat\Message\Text;
use EasyWeChat\Support\Log;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Psy\Util\Json;


class WeChatController extends Controller
{   

    public function checkauth(Request $request)
    {
       
        //Log::info(Config('wechat'));
        $wechat = new Application(Config('wechat'));
        $server = $wechat->server;
        //Log::info($wechat->server);
        $server->setMessageHandler(function($message){
            //Log::info(json_decode($message));//表情默认是文字
            switch ($message->MsgType) {
                case 'event':
                    switch ($message->Event) {
                        case 'subscribe':
                            $user=new User();
                            $user=$user->saves($message,1);
                            
                            $news= new News();
                            $news->title='快上车！一卡玩遍惠州！';
                            $news->description='你说好就好啊？我不是很没面？';
                            $news->url='https://wx.tpmission.com/1/cutprice/cutprice';
                            $news->image='http://ww1.sinaimg.cn/mw690/721b24cdgy1ffxq1bf7wpj20er09d117.jpg';
                            $news1= new News();
                            $news1->title='【星球通缉令】我抓到你咯！'.$user->username;
                            $news1->description='星球通缉令，不由你不来！';
                            $news1->url='http://mp.weixin.qq.com/s/bkQwSluZoZ484dFAIffmvQ';
                            $news1->image=$user->head_img;
                            return [$news,$news1];
                            break;
                        case 'SCAN':
                            //return json_decode($message);

                            $wechat = new Application(Config('wechat'));
                            $userService = $wechat->user;
                            $notice = $wechat->notice;
                            $key = explode('|',$message->EventKey);
                            if($key[0] == 1){
                                //核销员扫码设置
                                $belongsto = $key[2];
                                $wid = $key[1];
                                $salesperson = Salespersons::where('wid',$wid)->where('belongsto',$belongsto)->first();
                                if($salesperson->ifchecked == 0){
                                    $user = $userService->get($message->FromUserName);
                                    $ifexist = Salespersons::where('belongsto',$belongsto)->where('openid',$message->FromUserName)->first();
                                    if(!$ifexist){
                                        $salesperson->headimg = $user['headimgurl'];
                                        $salesperson->username = $user['nickname'];
                                        $salesperson->openid = $message->FromUserName;
                                        $salesperson->ifchecked = 1;
                                        $salesperson->save();
                                    }
                                    //return $user['nickname'];
                                    return '您已成为核销员！';
                                }else{
                                    return '该二维码已失效！';
                                }
                            }elseif($key[0] == 2){
                                //核销员进行扫码核销操作
                                $salesperson = new SalesPersionDetail();
                                //是否过期
                                Log::info("id---->".$key[5]);
                                if($salesperson->isExpired($key[5])) return "卡已过期,请续费";

                                $key[6] = 1;//核销
                                if($salesrecord = $salesperson->WriteOff($message->FromUserName,$key[4],$key[5])){
                                    
                                    if(!$salesrecord instanceof SalesPersionDetail) return "已核销";

                                    $activityname = Activity::where('id',$key[2])->value('activity_name');
                                    $user1 = $userService->get($salesrecord->open_id);
                                    $replystr = $salesperson->username.'在'.date('Y-m-d H:i:s').'核销了'.$user1['nickname'].'的'.$activityname;
                                    $message = new Text(['content' => '您的活动'.$activityname.'已成功核销']);
                                    $wechat->staff->message($message)->to($salesrecord->open_id)->send();
                                    return $replystr;
                                }else{
                                    return "你不是核销员";
                                }

                            }

                            break;
                        default:
                            return '收到事件消息'.$message['FromUserName'];
                            break;
                    }
                    break;
                case 'text':
                    $user=new User();
                    $user=  $user->saves($message,1);
                    if($message->Content=='好'){
                        $news= new News();
                        $news->title='快上车！一卡玩遍惠州！';
                        $news->description='你说好就好啊？我不是很没面？';
                        $news->url='https://wx.tpmission.com/1/cutprice/cutprice';
                        $news->image='http://ww1.sinaimg.cn/mw690/721b24cdgy1ffxq1bf7wpj20er09d117.jpg';
                        $news1= new News();
                        $news1->title='你说好就好啊？我不是很没面？';
                        $news1->description='你说好就好啊？我不是很没面？';
                        $news1->url='http://mp.weixin.qq.com/s/bkQwSluZoZ484dFAIffmvQ';
                        $news1->image='http://ww1.sinaimg.cn/mw690/721b24cdgy1ffxq1bf7wpj20er09d117.jpg';
                        return [$news,$news1];
                    }else{
                        return '简单点，说话的方式简单点~';
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

        $response = $server->serve();
        return $response;

    }

    /**
     *
     */
    public function index(Request $request)
    {
        $wechat = new Application(Config('wechat'));
        $server = $wechat->server;
        //Log::info($wechat->server);
        $server->setMessageHandler(function($message){
            switch ($message->MsgType) {
                case 'text':
                    return json_decode($message);
                    break;
            }
        });
        $response = $server->serve;
        return $response;
    }

    public function  test(){
       $user= new User();
        return  $user->access_token();
    }

    public function  menu(){
        $app = new Application(config('wechat'));
        $menu=$app->menu;
        $buttons = [
            [
                "type" => "view",
                "name" => "全民砍价",
                "url"  => "https://wx.tpmission.com/1/cutprice/cutprice"
            ],
            [
                "type" => "view",
                "name" => "畅玩卡",
                "url"  => "https://wx.tpmission.com/1/card/changwan"
            ]

        ];
        $menu->add($buttons);
    }

    public function serve(Request $request)
    {
        return config('wechat');
    }
    /** 保存session
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function oauth(Request $request)
    {
        $app = new Application(config('wechat'));
        Log::info('133331');
        $action = session("target_url");
        if(!$action){
            return view('errors.cutprice_error');
        }
        Log::info($action);
        $no_public_type = (substr($action,1,1));
        //$no_public_type = explode('/', $action);
        if($no_public_type == 1){
            Log::info('11111');
            session(['one'=>$app->oauth->user()]);
            Log::info('22222');
        }else{
            //TODO 多个公众号
            Log::info('3333');
        }

        return redirect()->to($action);

    }

    public function user(Request $request)
    {
        $no_public_type = (substr($request->getRequestUri(),1,1));
        $user = "";
        if($no_public_type == 1){
            $user = session('one');

        }else{
        //TODO 多个公众号
        }

        return $user;

    }


    //后台view
    public function wechatBackStage()
    {
        $admin = Auth::guard('admin')->user();
        $official_accounts = OfficialAccounts::orderBy('created_at','desc')->get();

        return view('wechat.wechatBackStage',['admin'=>$admin,'accounts'=>$official_accounts]);
    }

    //添加公众号
    public function addOfficialAccounts(Request $request)
    {
        $validator = Validator::make($request->all(),[
            'no_name' => 'required',
            'no_app_id' => 'required',
            'no_secret' => 'required',
            'no_token' => 'required'
        ]);

        if($validator->fails()){
            return response()->view('errors.403',['exception'=>'请检查好参数在提交']);
        }

        $official_accounts = new OfficialAccounts();

        if($official_accounts->addOfficialAccounts($request->no_name,$request->no_app_id,$request->no_secret,$request->no_token,0)){
            return redirect()->to('wechat/backstage')->with(['success"=>"添加成功']);
        }else{
            return redirect()->to('wechat/backstage')->with(['fails"=>"添加失败']);
        }
        
    }

    public function clearSession(){
        session(['one'=>'']);
    }


}