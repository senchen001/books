<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'year',
        'price'
    ];

    public function book()
    {
        return $this->belongsTo(Book::class, 'product_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
} 