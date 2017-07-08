<?php
namespace App\Http\Controllers\WeChat;


use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Book;
use App\Models\Business;
use App\Models\CardType;
use App\Models\CardUser;
use App\Models\Orders;
use App\Models\SalesPersionDetail;
use App\Models\User;
use Config;
use EasyWeChat\Foundation\Application;
use EasyWeChat\Payment\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;



class CardController extends BaseController
{

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */

    //个人主页
    public function my_changwan_card(Request $request)
    {
        $user= new User();
        $user= $user->saves($request->users,0);
        $card_user=CardUser::where('type',1)->where('open_id',$user->open_id)->first();
        if(!$card_user){
           $card_user= new CardUser();
           $card_user= $card_user->addCardUser($user,1);
        }else{
            if(time()>$card_user->valid_period){
                $card_user->activation=0;
                $card_user->valid_period=0;
                $card_user->save();
            }
        }
        $card_user->valid_period=date('Y-m-d',$card_user->valid_period);
        $actives=Activity::where('is_shelveed',1)->get();
//            ->leftJoin('businesses','activities.belongsto','=','businesses.id')
        $weekarr=['周日','周一','周二','周三','周四','周五','周六'];

        $num=0;
        foreach($actives as $active){
            $active->bussinesId=$active->bussines->id;
            $active->logo=$active->bussines->logo;
            $active->tel=$active->bussines->mobile_phone;
             $start= unserialize($active->start_end_time);
            $total='';
          foreach($start as $time){

              $hour=''; $week=''; $num++;
              $arr=explode(' - ',$time);
              for ($x=0; $x<=1; $x++) {

                  //2017/06/12 2:00
                  $t=substr($arr[$x],0,-2);
                  $t=str_replace('/','-',$t);
                //时间戳
                 $tsc=strtotime($t);
                if($x==0){
                    $week=$weekarr[date('w',$tsc)];
                }else if($week!=$weekarr[date('w',$tsc)]){
                    $week=$week.'至'.$weekarr[date('w',$tsc)];
                }
                  $APM=substr($arr[$x],-2,2);
                  if($APM=='AM'){
                      if($hour){
                          $hour=$hour.'-'.'上午'.substr($t,11);
                      }else{
                          $hour= '上午'.substr($t,11);
                      }

                  }else{
                      if($hour){
                          $hour=$hour.'-'.'下午'.substr($t,11);
                      }else{
                          $hour= '下午'.substr($t,11);
                      }
                  }

              }
              if($num%2==0){
                  $total=$total.$hour;
              }else{
                  $total=$total.$week.$hour;
              }

          }
           $active->total= $total;
           $sales=SalesPersionDetail::where('open_id',$user->open_id)->where('isOverdue',1)->where('activity_id',$active->id)->first();
           $active->sales=$sales?1:0;
        }
        $app = New Application(config('wechat'));
        $js = $app->js;
        dd($actives);
        return view('card.changwan',['actives'=>$actives,'user'=>$user,'card_user'=>$card_user,'activation'=>$card_user->activation,'js'=>$js]);

    }


    public function changwan_actdetail(Request $request){
        $active=Activity::where('id',$request->id)->first();

        $active->bussinesId=$active->bussines->id;
        $active->logo=$active->bussines->logo;
        $active->tel=$active->bussines->mobile_phone;
        
        $user= new User();
        $user= $user->saves($request->users,0);
        $card_user=CardUser::where('type',1)->where('open_id',$user->open_id)->first();
        if(!$card_user){
           $card_user= new CardUser();
           $card_user= $card_user->addCardUser($user,1);
        }
        //dd($user);
        $sales=SalesPersionDetail::where('open_id',$user->open_id)->where('isOverdue',1)->where('activity_id',$active->id)->first();
        if(empty($sales)){
            $active->sales=0;
        }else{
            $active->sales=1;
        }

    	return view('card.cwdetail',['act'=>$active,'card_user'=>$card_user]);
    }


    public function changwan_book(Request $request){
        $active=Activity::where('id',$request->id)->first();
        if(!$active){
            return view('errors.cutprice_error');
        }
        $group_time=unserialize($active->group_start_time);
        $count=0;
        $act_time=[];
        Log::info($group_time);
        foreach($group_time as $time){
            $arr=explode(' - ',$time);
            $start='';
            $end='';
            $num=0;
            foreach ($arr as $v){
               $str= str_replace("/","-",$v);
                if($num%2==0){
                    $start=date('Y-m-d H:i', strtotime($str));
                }else{
                    $end=date('Y-m-d H:i', strtotime($str));
                }
                $num++;
            }
            if(time()<(strtotime($start)-$active->group_end_time*3600)){
               $num=Book::where('serid',$count)->count();
                $act_time[$count]=array(
                    'text'=>$start.'至'.$end,
                    'num'=>$num,
                    'value'=>$count
                );
            }

            $count++;
        }


//    	$act_time[0]=['text'=>'2017-5-6 12:00至2017-5-6 14:00','value'=>'123456789'];
//    	$act_time[1]=['text'=>'2017-5-7 12:00至2017-5-7 14:00','value'=>'12345678'];
//    	$act_time[2]=['text'=>'2017-5-7 15:00至2017-5-7 17:30','value'=>'1245678'];
//    	$act_time[3]=['text'=>'2017-5-9 12:00至2017-5-9 14:00','value'=>'12450678'];

    	return view('card.cwbook',['act_time'=>$act_time,'active'=>$active,'open_id'=>$request->users->id]);
    }


    /**
     * @param Request $request
     * @return array
     */
    public  function  cwbookAdd(Request $request){
        $active=Activity::where('id',$request->id)->first();
        if($active->if_group!=2){
            return array(
                'code'=>40001,
                'msg'=>'Not Group'
            );
        }elseif($active->is_shelveed!=1) {
            return array(
                'code'=>40001,
                'msg'=>'Shelveed!'
            );
        }else{
                $bookCount= Book::where('serid',$request->serid)->where('activityid',$active->id)->count();
                if($bookCount>=$active->attendnum){
                    return array(
                        'code'=>40002,
                        'msg'=>'Outnumber'
                    );
                }else{
                    $book= Book::where('open_id',$request->open_id)->where('activityid',$active->id)->first();
                    if($book){
                        return array(
                            'code'=>40003,
                            'msg'=>'Repetitive operation'
                        );
                    }else{
                       $book= new Book();
                        $book->activityid=$request->id;
                        $book->open_id=$request->open_id;
                        $book->mobile_phone=$request->phone;
                        $book->serid=$request->serid;
                        $book->ip_address=$request->getClientIp();
                       $arr= unserialize($active->group_start_time);
                        $time=$arr[$request->serid];
                        $time=explode('-',$time);
                        $book->starttime=$time[0];
                        $book->endtime=$time[1];
                        $book->save();
                        $user=User::where('open_id',$request->open_id)->first();
                        if(empty($user->mobile_phone)){
                            $user->mobile_phone=$request->phone;
                            $user->save();
                        }
                    
                        return array(
                            'code'=>200,
                            'msg'=>'Success'
                        );
                    }

                }

            }
        }



    public function showmap(Request $request){

        $business=Business::where('id',$request->id)->first();
    	return view('card.showmap',['business'=>$business]);
    }

    public function checkact(Request $request){
    	return view('card.check_act',['act_title'=>'海底世界奇妙周']);
    }

    /**
     * @param Request $request
     * @return $this|\Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function paycard(Request $request,$id){

        $wechat = new Application(Config('wechat'));
        $payment = $wechat->payment;
        $orderNum = $this->generateOrderNum();
        $cardUser = CardUser::find($id);
        //dd($cardUser);
        if($cardUser->current_price == 0){

            //TODO 免费活动
        }else{
            $attributes = [
                'trade_type' => 'JSAPI', // JSAPI，NATIVE，APP...
                'body'       => $cardUser->card->cardname,
                'detail'     => $cardUser->card->cardname,
                'out_trade_no' => $orderNum,
                'total_fee' => $cardUser->current_price * 100,
                'notify_url' => 'https://wx.tpmission.com/paySuccessNotify', // 支付结果通知网址，如果不设置则会使用配置里的默认地址，我就没有在这里配，因为在.env内已经配置了。
                'openid' => $request->users->id

            ];
    // 创建订单
            //dd($attributes);
            $order = new Order($attributes);
            //dd($order);
            $result = $payment->prepare($order);
            //dd($result->return_code);
            if ($result->return_code == 'SUCCESS' && $result->result_code == 'SUCCESS')
            {

                $attributes['card_id'] = $cardUser->id;
                $attributes['copies'] = 1;
                $attributes['prepay_id'] = $result->prepay_id;

                $cardUser->order_num = $orderNum;
                $cardUser->desription = $cardUser->card->activities()->where("is_shelveed",1)->pluck("activity_name")->toArray();

                $orders = new Orders();
                $orders->createOrder($attributes);

                $prepayId = $attributes['prepay_id'];
                $config = $payment->configForJSSDKPayment($prepayId);
                return view("card.card_pay",['config' =>$config,'js'=>$wechat->js,"card"=>$cardUser]);

            }else{
                //TODO
            }
        }
    }
    
    
    //ajax client pay
    /**
     * @param $param ordernum
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function clentPay($param)
    {
        $orderNum = urldecode($param);
        $cardUser = new CardUser();

        $orders = new Orders();

        //已支付
        Log::info("客户端支付");
        $payMessage = "支付失败";
        if($pay = $orders->clientPay($param)){
            Log::info("完成支付");
            $payMessage = "支付成功";
            $cardUser->activation($pay->card_id);
            $salesrecords = SalesPersionDetail::where('open_id',$cardUser->open_id)
                ->where('isOverdue','neq',0)
                ->get();
            if($salesrecords){
                foreach ($salesrecords as $salesrecord){
                    $salesrecord->isOverdue = 0;
                    $salesrecord->save();
                }
            }

        }else{
            $payMessage = "支付失败";
        }

        return $payMessage;

    }


    public function getUserCode(Request $request){
        $salesrecord = SalesPersionDetail::where('user_id',$request->user_id)
                                         ->where('activity_id',$request->activity_id)
                                         ->where('open_id',$request->open_id)
                                         ->whereNull('isOverdue')
                                         ->first();
        if($salesrecord){
            $url = $salesrecord->qrcode;
        }else{
            $wechat = new Application(Config::get('wechat'));
            $qrcode = $wechat->qrcode;

            $str = '2'.'|'.$request->user_id.'|'.$request->activity_id.'|'.$request->open_id.'|'.$request->belongsto;
            $key = explode("|",$str);
           /* $salesrecord = New SalesPersionDetail();
            $salesrecord->user_id = $request->user_id;
            $salesrecord->activity_id = $request->activity_id;
            $salesrecord->open_id = $request->open_id;
            $salesrecord->card_user_id = $request->card_user_id
            $salesrecord->qrcode = $url;
            $salesrecord->save();*/
            array_push($key,$request->card_user_id,0);
            $salesPersionDesl = new SalesPersionDetail();
            $results = $salesPersionDesl->addOrUpdateRecord($key,$request->open_id);
            
            $str = $str.'|'.$results->id;
            $result = $qrcode->forever($str);// 或者 $qrcode->forever("foo");
            $ticket = $result->ticket;// 或者 $result['ticket']
            $url = $qrcode->url($ticket);

            $results->qrcode = $url;
            $results->save();
            
        }
        return $url;
    }


    function editphone(Request $request){
    	$user=User::where('id',$request->id)->first();
    	$user->mobile_phone=$request->phone;
        $user->save();
        return array(
            'code'=>200,
            'msg'=>'Success'
        );
    }


}