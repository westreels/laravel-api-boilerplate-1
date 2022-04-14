<?php

namespace App\Models\Slotlayer;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Slotlayer\Gameoptions;
use \Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AccessProviders extends \App\Models\BaseModel
{

   use HasFactory;
   public $timestamps = true;
    protected $dateFormat = 'U';
    public $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'access_providers';
     
    protected $fillable = [
        'id', 'provider', 'price', 'access_profile'
    ];

    protected $hidden = [
        'created_at', 'updated_at'
    ];


    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];



}