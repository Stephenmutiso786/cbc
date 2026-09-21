<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InvoiceItem extends Model { protected $fillable = ['description','quantity','unit_price','amount','position']; protected $casts = ['quantity'=>'decimal:2','unit_price'=>'decimal:2','amount'=>'decimal:2']; public function invoice() { return $this->belongsTo(Invoice::class); } }
