<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Module extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['name', 'table_name', 'icon'];

    public function fields()
    {
        return $this->hasMany(ModuleField::class)->orderBy('id', 'asc');
    }
}
