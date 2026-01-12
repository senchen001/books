<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $table = 'orders';
    protected $primaryKey = 'id';

    protected $fillable = [
        'userid',
        'productid',
        'ordernum',
        'quantity',
        'year',
        'status',
        'price',
    ];

    public $timestamps = true;

    public function user()
    {
        return $this->belongsTo(User::class, 'userid');
    }

    public function book()
    {
        return $this->belongsTo(Book::class, 'productid');
    }
}
