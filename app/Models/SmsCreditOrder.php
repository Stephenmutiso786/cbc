<?php
namespace App\Models;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
class SmsCreditOrder extends Model {
    use BelongsToSchool;
    protected $fillable = ['school_id','units','amount','phone','status','checkout_request_id','merchant_request_id','mpesa_receipt_number','failure_reason','initiated_by','confirmed_at'];
    protected $casts = ['amount'=>'decimal:2','confirmed_at'=>'datetime'];
    public function school() { return $this->belongsTo(School::class); }
}
