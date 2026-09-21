<?php
namespace App\Models;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
class Invoice extends Model {
    use BelongsToSchool;
    protected $fillable = ['invoice_number','school_id','package_id','subscription_payment_id','title','notes','currency','subtotal','discount_amount','tax_amount','total_amount','status','rejection_reason','due_date','sent_at','responded_at','paid_at','created_by','responded_by'];
    protected $casts = ['due_date'=>'date','sent_at'=>'datetime','responded_at'=>'datetime','paid_at'=>'datetime','subtotal'=>'decimal:2','discount_amount'=>'decimal:2','tax_amount'=>'decimal:2','total_amount'=>'decimal:2'];
    public function items() { return $this->hasMany(InvoiceItem::class)->orderBy('position'); } public function school() { return $this->belongsTo(School::class); } public function package() { return $this->belongsTo(Package::class); } public function creator() { return $this->belongsTo(User::class, 'created_by'); } public function respondedBy() { return $this->belongsTo(User::class, 'responded_by'); }
    public static function nextNumber(): string { $prefix = 'INV-'.now()->format('Ym').'-'; return DB::transaction(function () use ($prefix) { $last = static::withoutSchoolScope()->where('invoice_number','like',$prefix.'%')->lockForUpdate()->orderByDesc('invoice_number')->value('invoice_number'); return $prefix.str_pad((string) (($last ? (int) Str::afterLast($last, '-') : 0)+1),4,'0',STR_PAD_LEFT); }); }
    public function recalculateTotals(): void { $this->update(['subtotal'=>$this->items()->sum('amount'),'total_amount'=>max(0, $this->items()->sum('amount')-$this->discount_amount+$this->tax_amount)]); }
    public function send(): void { $this->update(['status'=>'sent','sent_at'=>now()]); } public function accept(int $userId): void { $this->update(['status'=>'accepted','responded_at'=>now(),'responded_by'=>$userId,'rejection_reason'=>null]); } public function reject(int $userId,string $reason): void { $this->update(['status'=>'rejected','responded_at'=>now(),'responded_by'=>$userId,'rejection_reason'=>$reason]); } public function markPaid(): void { $this->update(['status'=>'paid','paid_at'=>now()]); }
}
