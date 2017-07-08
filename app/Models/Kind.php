<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kind extends Model
{
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = [
        'name', 'cn_name'
    ];

    public function allkinds()
    {
    	$allkind=Kind::all();
    	return $allkind;
    }
}
