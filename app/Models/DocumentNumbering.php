<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentNumbering extends Model
{
    use HasFactory;

    protected $fillable = [
        'module',
        'module_label',
        'format',
        'starting_number',
        'digit',
        'current_number',
    ];
}
