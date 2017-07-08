<?php

namespace App\Api\V1\Controllers;

use Config;
use Tymon\JWTAuth\JWTAuth;
/*use App\Http\Controllers\Controller as Controller;*/

use App\Api\V1\Transformers\UserTransformer;
use App\Models\User;
use Illuminate\Http\Request;
use Validator;
use Log;

class UsersController extends Controller
{

    public function index()
    {
      $user = User::find(1);
      return $user;
    }
}
