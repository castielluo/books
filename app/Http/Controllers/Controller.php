<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesResources;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Config;
use Qiniu\Storage\UploadManager;

class Controller extends BaseController
{
    use AuthorizesRequests, AuthorizesResources, DispatchesJobs, ValidatesRequests;


    //带参七牛上传
    /**
     * @param $filePath
     * @param $image_name
     * @param null $option
     * @return int|string
     * @throws \Exception
     */
    public function upload($filePath, $image_name, $options = null)
    {
        $bucket = Config::get('qiniu.bucket');
        $access_key = Config::get('qiniu.accessKey');
        $secret_key = Config::get('qiniu.secretKey');
        $domain = Config::get('qiniu.domain');
        $auth = new \Qiniu\Auth($access_key,$secret_key);
        $upload_token = $auth->uploadToken($bucket);

        $option = "";//设置图片规格

        if ($options != null){
            if (is_array($options)){
                $option = "&".implode("/",$options); //$option = array('imageView2',1,'w',200,'h',200);
            }else if (is_string($options)){
                $option = "&".$options; //imageView2/1/w/200/h/200
            }
        }

        if ($filePath){
            $uploadManager = new UploadManager();
            list($ret,$err) = $uploadManager->putFile($upload_token,$image_name,$filePath);
            if ($err != null){
                return 0;
            }else{
                return $domain.$ret['key']."?imageslim".$option;//返回压缩后图片
            }
        }
    }


    /**删除空格
     * @param $str
     * @return mixed
     */
    function trimall($str)
    {
        $qian=array(" ","　","\t","\n","\r");$hou=array("","","","","");
        return str_replace($qian,$hou,$str);
    }
}
