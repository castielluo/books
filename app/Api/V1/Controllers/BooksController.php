<?php

namespace App\Api\V1\Controllers;

use Config;
use Tymon\JWTAuth\JWTAuth;
/*use App\Http\Controllers\Controller;*/

use App\Api\V1\Transformers\UserTransformer;
use App\Models\Kind;
use App\Models\Book;
use App\Models\User;
use App\Models\Tag;
use App\Models\Book_tag;
use Illuminate\Http\Request;
use Validator;
use Log;

class BooksController extends Controller
{
    public function login(Request $request){
      $url = "https://api.weixin.qq.com/sns/jscode2session?appid=wxe18d6353edc51800&secret=2a9b11e7d51ba98a6402052835238b98&js_code=".$request->code."&grant_type=authorization_code";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        $jsoninfo = json_decode($output, true);
        return $jsoninfo;
    }

    public function kinds()
    {
      $kind=new Kind();
      $kind_banner=['https://www.aliencat.cn/books/public/images/1.jpg','https://www.aliencat.cn/books/public/images/1.jpg'];
      $allkind=$kind->allkinds();
      $data['msg']='success';
      $data['code']=200;
      $data['data']['kinds']=$allkind;
      $data['data']['banner']=$kind_banner;
      return $data;
    }

    public function kindbooks(Request $request){
    	$kind=$request->kind;
    	$allbook=Book::where('belong_kind',$kind)->get();
      foreach ($allbook as $key => $value) {
        $suoyin=Book_tag::where('bookid',$value->id)->pluck('tagid');
        $tags=Tag::whereIn('id',$suoyin)->pluck('name');
        $allbook[$key]['tags']=$tags;
        $theuser=User::where('id',$value->belong_user)->first();
        $allbook[$key]['user_avatar']=$theuser->avatar;
      }
      $data['msg']='success';
      $data['code']=200;
      $data['data']=$allbook;
      return $data;
    }

    public function scanbook(Request $request){
        $url = "https://api.douban.com/v2/book/isbn/:".$request->isbn;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        $jsoninfo = json_decode($output, true);
        return $jsoninfo;
    }
}
