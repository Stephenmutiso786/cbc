<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamReportExport extends Model
{
    protected $fillable = ['exam_id', 'requested_by', 'status', 'path', 'error', 'finished_at'];

    protected $casts = ['finished_at' => 'datetime'];

    public function exam() { return $this->belongsTo(Exam::class); }
    public function requester() { return $this->belongsTo(User::class, 'requested_by'); }
}
