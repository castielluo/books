<?php

namespace App\Api\V1\Controllers;

use Config;
use Tymon\JWTAuth\JWTAuth;
/*use App\Http\Controllers\Controller;*/

use App\Api\V1\Transformers\UserTransformer;
use Dingo\Api\Exception\ValidationHttpException;
use App\Models\Kind;
use App\Models\Book;
use App\Models\User;
use App\Models\Comments;
use App\Models\Banner;
use App\Models\Tag;
use App\Models\Book_tag;
use App\Models\Book_star;
use App\Models\Book_borrow_record;
use Illuminate\Http\Request;
use Validator;
use Log;
use Cache;

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
      $kind_banner=Banner::where('used',1)->get();
      $allkind=$kind->allkinds();
      $data['msg']='success';
      $data['code']=200;
      $data['data']['kinds']=$allkind;
      $data['data']['banner']=$kind_banner;
      return $data;
    }

    public function kindbooks(Request $request){
    	$kind=$request->kind;
      if($request->guide==0){
        $allbook=Book::where('belong_kind',$kind)->orderBy('created_at','desc')->take(5)->get();
      }else{
        $allbook=Book::where('belong_kind',$kind)->where('created_at','<',$request->guide)->orderBy('created_at','desc')->take(5)->get();
      }
      foreach ($allbook as $key => $value) {
        $suoyin=Book_tag::where('bookid',$value->id)->pluck('tagid');
        $tags=Tag::whereIn('id',$suoyin)->pluck('name');
        $allbook[$key]['tags']=$tags;
        $theuser=User::where('openid',$value->belong_user)->first();
        if(!empty($theuser)){
          $allbook[$key]['user_avatar']=$theuser->avatar;
        }
      }
      if(count($allbook)<1){
        $data['msg']='fail';
        $data['code']=404;
      }else{
        $data['guide']=$allbook->last();
        $data['msg']='success';
        $data['code']=200;
        $data['data']=$allbook;
      }
      
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

    public function getcity(Request $request){
        $url = "http://api.map.baidu.com/geocoder/v2/?callback=&location=".$request->lat.",".$request->lng."&output=json&pois=1&ak=6yLpFNAYzHDNloZEYotG9q1enTEmF4en";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        $jsoninfo = json_decode($output, true);
        return $output;
    }


    public function thebook(Request $request){
      $id=$request->id;
      $thebook=Book::where('id',$id)->first();
      $comments=Comments::where('bookid',$id)->get();
      $user=User::where('openid',$thebook->belong_user)->first();
      if(!empty($user)){
        $thebook->user=$user->name;
        $thebook->user_avatar=$user->avatar;
      }
      $tagsign=Book_tag::where('bookid',$id)->pluck('tagid');
      $tags=Tag::whereIn('id',$tagsign)->pluck('name');
      $thebook->tags=$tags;
      $ifstar=Book_star::where('bookid',$id)->where('openid',$request->openid)->first();
      if(empty($ifstar)){
        $data['star']['hasstar']=false;
        $data['star']['nowstarnum']=1;
      }else{
        $data['star']['hasstar']=true;
        $data['star']['nowstarnum']=$ifstar->star;
      }

      $data['msg']='success';
      $data['code']=200;
      $data['book']=$thebook;
      $data['comments']=$comments;
      return $data;
    }

    public function addUser(Request $request){
      
      $validator = Validator::make($request->all(), [
          'openid' => 'required'
      ]);
      if ($validator->fails()){
          throw new ValidationHttpException($validator->errors()->all());
      }
      $existuser=User::where('openid',$request->openid)->first();
      if(empty($existuser)){
        $openid=$request->openid;
        $user=new User();
        $user->name=$request->name;
        $user->avatar=$request->avatar;
        $user->openid=$request->openid;
        $user->country=$request->country;
        $user->province=$request->province;
        $user->city=$request->city;
        $user->gender=$request->gender;
        if($user->save()){
          $data['msg']='success';
          $data['code']=200;
        }else{
          $data['msg']='fail';
          $data['code']=202;
        }
      }else{
        $data['msg']='already exist';
        $data['code']=201;
      }
      
      return $data;
    }



    public function addcomment(Request $request){
      $validator = Validator::make($request->all(), [
          'openid' => 'required',
          'comment'=> 'required',
          'bookid'=> 'required'
      ]);
      if ($validator->fails()){
          throw new ValidationHttpException($validator->errors()->all());
      }
      $comment=new Comments();
      $comment->bookid=$request->bookid;
      $comment->avatar=$request->avatar;
      $comment->name=$request->name;
      $comment->comment=$request->comment;
      if($comment->save()){
        $data['msg']='success';
        $data['code']=200;
        $data['comment']=$comment;
      }else{
        $data['msg']='fail';
        $data['code']=500;
      }
      return $data;
    }


    public function addbook(Request $request){
      $validator = Validator::make($request->all(), [
          'openid' => 'required',
          'title'=> 'required'
      ]);
      if ($validator->fails()){
          throw new ValidationHttpException($validator->errors()->all());
      }
      $isthirdbook=Book::where('belong_user',$request->openid)->where('isbn10',$request->isbn10)->count();
      if($isthirdbook<3){
        $book=new Book();
        $book->name=$request->title;
        $book->isbn10=$request->isbn10;
        $book->isbn13=$request->isbn13;
        $book->author=$request->author;
        $book->publish=$request->publish;
        $book->belong_user=$request->openid;
        $book->subtitle=$request->subtitle;
        $book->image_s=$request->images_s;
        $book->image_m=$request->images_m;
        $book->image_l=$request->images_l;
        $book->des_l=$request->summary;
        $book->publishtime=$request->pubdate;
        $book->status=$request->status;
        $book->belong_kind=($request->kind)+1;
        $book->story=$request->story;
        $book->needtoknow=$request->needtoknow;
        $book->cityname=$request->cityname;
        $book->citycode=$request->citycode;
        $book->numRaters=$request->numRaters;
        $book->average=$request->average;
        $book->totalscore=($request->numRaters)*($request->average);
        $alltags=preg_match_all('/name":"(.*?)","title/',$request->tags,$mat);

        if($book->save()){
          foreach ($mat[1] as $key => $value) {
             $istag=Tag::where('name',$value)->first();
             if(empty($istag)){
              $newtag=new Tag();
              $newtag->name=$value;
              $newtag->save();
              $id=$newtag->id;
             }else{
              $id=$istag->id;
             }
             $newtagbind=new Book_tag();
             $newtagbind->bookid=$book->id;
             $newtagbind->tagid=$id;
             $newtagbind->save();
          }
          $owner=User::where('openid',$request->openid)->first();
          $owner->booksnum=$owner->booksnum+1;
          $owner->save();
          $data['msg']='success';
          $data['code']=200;
          $data['book']=$book;
        }else{
          $data['msg']='fail';
          $data['code']=500;
        }
      }else{
        $data['code']=201;
        $data['msg']="allow three times";
      }
      
      return $data;

    }

    public function mybook(Request $request){
      $validator = Validator::make($request->all(), [
          'openid' => 'required'
      ]);
      if ($validator->fails()){
          throw new ValidationHttpException($validator->errors()->all());
      }
      $openid=$request->openid;
      $allbook=Book::where('belong_user',$openid)->get();
      $allbooknum=Book::where('belong_user',$openid)->count();
      foreach ($allbook as $key => $value) {
        $suoyin=Book_tag::where('bookid',$value->id)->pluck('tagid');
        $tags=Tag::whereIn('id',$suoyin)->pluck('name');
        $allbook[$key]['tags']=$tags;
      }
      $data['msg']='success';
      $data['code']=200;
      $data['books']=$allbook;
      $data['num']=$allbooknum;
      return $data;
    }


    public function me(Request $request){
      
      $validator = Validator::make($request->all(), [
          'openid' => 'required'
      ]);
      if ($validator->fails()){
          throw new ValidationHttpException($validator->errors()->all());
      }
      $user=User::where('openid',$request->openid)->first();
      if(empty($user)){
        //
        $data['msg']='no such user';
        $data['code']=201;
      }else{
        $score=0;
        $data['msg']='success';
        $data['code']=200;
        $data['me']['booknum']=$user->booksnum;
        $data['me']['score']=$score;
      }
      
      return $data;
    }


    public function addstar(Request $request){
      $validator = Validator::make($request->all(), [
          'openid' => 'required',
          'star' => 'required',
          'bookid' => 'required'
      ]);
      if ($validator->fails()){
          throw new ValidationHttpException($validator->errors()->all());
      }
      $ori_book=Book::where('id',$request->bookid)->first();
      if(empty($ori_book)){
        $data['code']=404;
        $data['msg']='no such book';
        return $data;
      }
      $ifhas=Book_star::where('openid',$request->openid)->where('bookid',$request->bookid)->first();
      if(empty($ifhas)){
        $bookstar=new Book_star();
        $bookstar->openid=$request->openid;
        $bookstar->star=$request->star;
        $bookstar->bookid=$request->bookid;
        if($bookstar->save()){
          $ori_num=$ori_book->numRaters;
          $ori_total=$ori_book->totalscore;
          $ori_book->totalscore=$ori_total+($request->star);
          $ori_book->numRaters=$ori_num+1;
          $ori_book->average=($ori_book->totalscore)/($ori_book->numRaters);
          $ori_book->save();
          $data['code']=200;
          $data['msg']='success';
        }
      }else{
        $data['code']=201;
        $data['msg']='already star';
      }
      return $data;
    }




    public function otheruser(Request $request){
      
      $validator = Validator::make($request->all(), [
          'openid' => 'required'
      ]);
      if ($validator->fails()){
          throw new ValidationHttpException($validator->errors()->all());
      }
      $user=User::where('openid',$request->openid)->first();
      if(empty($user)){
        $data['msg']='no such user';
        $data['code']=201;
      }else{
        $score=0;
        $data['msg']='success';
        $data['code']=200;
        $data['ta']['avatar']=$user->avatar;
        $data['ta']['name']=$user->name;
        $data['ta']['gender']=$user->gender;
        $data['ta']['booknum']=$user->booksnum;
        $data['ta']['score']=$score;
      }
      
      return $data;
    }


    public function orderusers(Request $request){
      if($request->guide==-1){
        $user=User::orderBy('booksnum','desc')->take(1)->get();
      }else{
        $user=User::where('booksnum','<',$request->guide)->orderBy('booksnum','desc')->take(1)->get();
      }
      if(count($user)<1){
        $data['msg']='fail';
        $data['code']=404;
      }else{
        $data['guide']=$user->last();
        $data['msg']='success';
        $data['code']=200;
        $data['data']=$user;
      }
      return $data;
    }


    public function borrowit(Request $request){
      $validator = Validator::make($request->all(), [
          'openid' => 'required',
          'bookid'=> 'required'
      ]);
      if ($validator->fails()){
          throw new ValidationHttpException($validator->errors()->all());
      }
      $book=Book::where('id',$request->bookid)->first();
      $access_token=$this->getAccessToken();
      if(!empty($access_token)){
        $ch = curl_init();
        $data=[
          "touser"=>$book->belong_user,  
          "template_id"=>"gtf3e3t0Th-wh-buD4m7E7QrnyheKALboPFnZxBYEPI", 
          "page"=>"page/my/handleborrow/index?id=3",          
          "form_id"=>$request->formid,         
          "data"=>[
              "keyword1"=> [
                  "value"=> $request->name
              ], 
              "keyword2"=> [
                  "value"=> $request->citycode?$request->citycode:'未知'
              ], 
              "keyword3"=> [
                  "value"=> $book->name
              ] , 
              "keyword4"=> [
                  "value"=> $request->comment
              ] 
          ]
        ];
        $result = $this->https_curl_json('https://api.weixin.qq.com/cgi-bin/message/wxopen/template/send?access_token='.$access_token,$data,'json');
        if($result['errcode']==0){
          $return_data['code']=200;
          $return_data['msg']='success';
          $borrow_record=new Book_borrow_record();
          $borrow_record->from=$request->openid;
          $borrow_record->bookid=$request->bookid;
          $borrow_record->owner=$book->belong_user;
          $borrow_record->comment=$request->comment;
          $borrow_record->citycode=$request->citycode?$request->citycode:0;
          $borrow_record->save();
        }else{
          $return_data['code']=404;
          $return_data['msg']='send fail';
        }
      }else{
        $return_data['code']=404;
        $return_data['msg']='can not get accesstoken';
      }
      return $return_data;
    }

    public function getAccessToken(){
     
      if(Cache::has('accesstoken')){
        return Cache::get('accesstoken');
      }else{
        $appid='wxe18d6353edc51800';
        $appsecret='2a9b11e7d51ba98a6402052835238b98';
        $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$appid."&secret=".$appsecret;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        $jsoninfo = json_decode($output, true);
        $minutes=floor($jsoninfo['expires_in']/60);
        Cache::add('accesstoken',$jsoninfo['access_token'],$minutes);
        return $jsoninfo['access_token'];
      }
      
    }


    public function  https_curl_json($url,$data,$type){
        if($type=='json'){//json $_POST=json_decode(file_get_contents('php://input'), TRUE);
            $headers = array("Content-type: application/json;charset=UTF-8","Accept: application/json","Cache-Control: no-cache", "Pragma: no-cache");
            $data=json_encode($data);
        }
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS,$data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers );
        $output = curl_exec($curl);
        if (curl_errno($curl)) {
            echo 'Errno'.curl_error($curl);//捕抓异常
        }
        curl_close($curl);
        $jsoninfo = json_decode($output, true);
        return $jsoninfo;
    }


    public function handleborrow(Request $request){
      $validator = Validator::make($request->all(), [
          'id' => 'required'
      ]);
      if ($validator->fails()){
          throw new ValidationHttpException($validator->errors()->all());
      }
      $borrow_record=Book_borrow_record::where('id',$request->id)->first();
      if(!empty($borrow_record)){
        $request_from=User::where('openid',$borrow_record->from)->first();
        $thebook=Book::where('id',$borrow_record->bookid)->first();
        if(!empty($request_from)&&!empty($thebook)){
          $data['code']=200;
          $data['msg']='success';
          $data['from']=$request_from;
          $data['thebook']=$thebook;
          $data['record']=$borrow_record;
        }else{
          $data['code']=404;
          $data['msg']='can not find the book or man';
        }
      }else{
        $data['code']=404;
        $data['msg']='no such record';
      }
      return $data;
    }




}
