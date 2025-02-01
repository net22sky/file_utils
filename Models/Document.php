<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model {
    protected $table = 'documents';
    protected $fillable = ['path', 'title', 'creation_date', 'thumbnail_path'];
}