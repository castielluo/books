<?php
/**
 * Created by PhpStorm.
 * User: jianqi
 * Date: 2017/6/15
 * Time: 17:59
 */

namespace App\Http\Controllers\WeChat;


use App\Http\Controllers\Controller;
use App\Models\Orders;
use EasyWeChat\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Prophecy\Doubler\LazyDouble;

class BaseController extends Controller
{

    //订单号生成
    function generateOrderNum(){
        $year_code = array('A','B','C','D','E','F','G','H','I','J');
        return $year_code[intval(date('Y')) - 2011] . strtoupper(dechex(date('m'))) . date('d') .
        substr(time(), -5) . substr(microtime(), 2, 5) . sprintf('%02d', rand(0, 99));
    }

    //回掉url
    public function notifyUrl(Request $request)
    {
        $wechat = new Application(config('wechat'));
        $response = $wechat->payment->handleNotify(function($notify, $successful){

            $order_arr = json_decode($notify,true);
            $orderNum = $order_arr['out_trade_no'];//订单号
            
            $order = Orders::where("order_num",$orderNum)->first();

            if(!$order){
                return "Order Number not exist";
            }

            if ($successful) {
                $order->status = "paid";
                $order->server_pay = 1;
            }else{
                $order->status = "paid_fail";
            }

            $order->save();
            return true;
        });

        return $response;

    }

}