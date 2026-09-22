<?php
namespace App\Models;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
class PastExamDocument extends Model { use BelongsToSchool; protected $fillable = ['school_id','class_id','title','document_type','academic_year','term','file_path','original_name','file_size','uploaded_by']; public function schoolClass() { return $this->belongsTo(SchoolClass::class, 'class_id'); } public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); } }
