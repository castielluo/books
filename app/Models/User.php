<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $primaryKey = 'id';
    protected $fillable = [
        'name', 'avatar','openid','location','tel','qrcode'
    ];


}
