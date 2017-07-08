<?php
namespace App\Http\Controllers\WeChat;


use App\Http\Controllers\Controller;
use App\Models\Bargainr;
use App\Models\BargainrDetails;
use App\Models\CardType;
use App\Models\CardUser;
use App\Models\User;
use EasyWeChat\Foundation\Application;
use EasyWeChat\Payment\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;


class CutpriceController extends BaseController
{   

    public function test(Request $request)
    {
        $user = session('one');
        //dd($user);
        $helper=[];
        $helper[0]=['name'=>'泽学','avatar'=>'http://tva3.sinaimg.cn/crop.0.0.180.180.180/721b24cdjw1e8qgp5bmzyj2050050aa8.jpg','price'=>2.3];
        $helper[1]=['name'=>'泽学','avatar'=>'http://tva3.sinaimg.cn/crop.0.0.180.180.180/721b24cdjw1e8qgp5bmzyj2050050aa8.jpg','price'=>2.3];
        $pricer=[];
        $pricer[0]=['name'=>'泽学','avatar'=>'http://tva3.sinaimg.cn/crop.0.0.180.180.180/721b24cdjw1e8qgp5bmzyj2050050aa8.jpg','price'=>2.3];
        $pricer[1]=['name'=>'泽学','avatar'=>'http://tva3.sinaimg.cn/crop.0.0.180.180.180/721b24cdjw1e8qgp5bmzyj2050050aa8.jpg','price'=>2.3];
        return view('cutprice.cutprice',['user'=>$user,'barginname'=>'暑期畅玩卡','origin_price'=>'362元','nowprice'=>'215元','bottomprice'=>'125元','is_mine'=>1,'helper'=>$helper,'pricer'=>$pricer]);

    }


    /**
     * @param Request $request
     */
    public function cutprice(Request $request){
        $user= new User();
        //保存用户信息
        $user= $user->saves($request->users,0);
        //判断是否带参
        if(!$request->id){
           //查询是否是砍价用户
            $card_user= CardUser::where('open_id',$user->open_id)->where('type',1)->first();
            if(!$card_user){
                    $card_user= new CardUser();
                    $card_user= $card_user->addCardUser($user,1);
            }
        }else{
            $card_user= CardUser::where('id',$request->id)->first();
            if(!$card_user){
                //参数id错误
                return view('errors.cutprice_error');
            }
        }
        //查出最新的一条卡活动
            $card_active= Bargainr::where('card_type_id',1)->first();
            $app = new Application(config('wechat'));
            $js = $app->js;
            //被砍人信息
            $master_user=User::where('open_id',$card_user->open_id)->first();
            $card_kind=CardType::where('id',$card_user->type)->first();

            if($card_user->open_id == $request->id){
                //当前自己的卡是否过期
               if(time()> $card_user->valid_period && $card_user->valid_period!=0){
                   //过期 重新生成
                   $card_user->current_price=$card_kind->price;
                   $card_user->activation=0;
                   $card_user->valid_period=0;
                   $card_user->save();
               }

                //属于自己的页面
                $bargain=BargainrDetails::where('card_user_id',$card_user->id)->get();
                return view('cutprice.cutprice',['card_user'=>$card_user,'bargains'=>$bargain,'master_user'=>$master_user,'card_kind'=>$card_kind,'is_mine'=>1,'user'=>$master_user,'js'=>$js,'card_active'=>$card_active]);


            }else if($card_user){
               $cardList= CardUser::where('type',$card_kind->type)
                    ->leftJoin('users', 'card_user.open_id', '=', 'users.open_id')
                    ->orderBy('current_price', 'Asc')
                    ->take(10)
                    ->get();

                return view('cutprice.cutprice', ['card_user'=>$card_user, 'cardlist'=>$cardList, 'master_user'=>$master_user, 'card_kind'=>$card_kind, 'is_mine'=>0, 'js'=>$js, 'user'=>$user,"card_active"=>$card_active]);


            }

    }

    /*帮人砍价ajax*/
    public function icut(Request $request){
        //判断post上来的参数有无openid
        $user=User::where('open_id',$request->bargainer_openid)->first();
        if(!$user){
            $code=40001;
            $msg='Openid invalid';
        }else{
            //查询当前砍价信息
         $card_user= CardUser::where('id',$request->id)->first();
            //查出卡种信息
        if($card_user->open_id==$user->open_id){
            return array(
                $code=40001,
                $msg='Openid invalid'
            );
        }

         $card=CardType::where('id',$card_user?$card_user->type:'')->first();
         if(!$card || !$card_user){
             // 'msg'=>'卡种信息 卡用户有误'
             $code=40002;
             $msg='CardKind or CardUser Wrong';
         }else{
             $card_active= Bargainr::where('card_type_id',$card_user->type)->first();
             $price=$card_user->current_price - $card_active->lowest_price;
             if($price<=0){ //最低价
                 $code=40003;
                 $msg='Floor price';
             }else{
                 //查询是否砍过价
                 $bargain=BargainrDetails::where('card_user_id',$request->id)->where('bargainer_openid',$request->bargainer_openid)->first();
                 if($bargain){ //重复砍价
                     $code=40004;
                     $msg='Repetitive operation';
                 }else{
                     $code=200;
                        if($price>2&&$price<=8){
                            $msg= number_format($price/rand(2,8),2);
                        }else if($price<=2){
                            $msg=number_format($price/rand(1,2),2);
                        }else{
                            //随机砍价
                            $msg=$this->computePrice();
                        }

                     $card_user->current_price-=$msg;
                     Log::info($card_user);
                     $card_user->save();
                     Log::info($card_user);
                     $bargain=new BargainrDetails();
                     $bargain->open_id=$request->open_id;
                     $bargain->bargainer_openid=$request->bargainer_openid;
                     $bargain->bargainer_name=$request->bargainer_name;
                     $bargain->bargainer_headimg=$request->bargainer_headimg;
                     $bargain->card_user_id=$request->id;
                     $bargain->bargainmoney=$msg;
                     $bargain->save();
                 }
             }
          }
        }
        return response()->json(['code' => $code,'msg'=>$msg]);



    }


    public function computePrice(){
        $rand=rand(0,99);
        if($rand<5){
            return 0;
        }elseif($rand>=5&&$rand<70){
            // (0,2)
           return $this->randomFloat(0.01,2);
        }elseif($rand>=70&&$rand<90){
            return $this->randomFloat(2.01,5);
        }else{
            return $this->randomFloat(5.01,8);
        }
    }
   public  function randomFloat($min,$max)
    {
        $num = $min + mt_rand() / mt_getrandmax() * ($max - $min);
        return sprintf("%.2f", $num);

    }

}