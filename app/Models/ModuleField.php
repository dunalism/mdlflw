<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class ModuleField extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'module_id',
        'column_name',
        'data_type',
        'is_required',
        'is_unique',
        'options',
        'source_type',
        'related_module_id',
    ];

    protected $touches = ['module'];

    /**
     * Atribut yang harus di-cast ke tipe data tertentu.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_required' => 'boolean',
        'is_unique' => 'boolean',
    ];

    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    public function relatedModule()
    {
        return $this->belongsTo(Module::class, 'related_module_id');
    }
}
