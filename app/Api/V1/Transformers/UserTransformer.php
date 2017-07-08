<?php
namespace App\Api\V1\Transformers;
use App\Models\User;
use League\Fractal\TransformerAbstract;

class UserTransformer extends TransformerAbstract
{
    public function transform(User $user)
    {
        return [
                    'id' => (int)$user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar,
                    'openid' => $user->nick_name,
                    'location' => $user->location,
                    'birthday' => $user->birthday,
                    'tel' => $user->tel,
                    'qrcode' => $user->qrcode
        ];
    }
}
