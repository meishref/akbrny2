<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    public $timestamps = true;

    public function post() {
        return $this->belongsTo('App\Post');
    }



    function user(){
        return $this->belongsTo('App\User');
    }


}
