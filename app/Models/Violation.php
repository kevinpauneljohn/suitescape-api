<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Violation extends Model
{
    // Allow mass assignment for the 'name' field
    protected $fillable = ['name'];
}
