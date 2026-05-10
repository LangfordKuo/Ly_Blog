<?php

namespace LyBlog\Models;

class Link extends Model
{
    protected static $table = 'links';
    protected static $fillable = ['name', 'url', 'description', 'logo', 'sort_order', 'status'];
}
