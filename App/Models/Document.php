<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;


/** 
* App\Models\Model 
* 
* @method static \Illuminate\Database\Eloquent\Builder|Model newModelQuery() 
* @method static \Illuminate\Database\Eloquent\Builder|Model newQuery() 
* @method static \Illuminate\Database\Eloquent\Builder|Model query() 
* @mixin \Eloquent 
*/ 


class Document extends Model {
    protected $table = 'documents';
    protected $fillable = ['path', 'title', 'creation_date', 'thumbnail_path', 'hash']; // Добавляем поле hash
}