<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BusinessRelation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'name',
        'email',
        'phone',
        'address',
        'tax_id',
        'registration_number',
        'industry',
        'website',
        'status',
    ];

    const TYPES = [
        'supplier' => 'Pemasok',
        'customer' => 'Pelanggan',
        'member' => 'Anggota',
        'partner' => 'Partner',
        'employee' => 'Karyawan',
    ];

    const STATUSES = [
        'active' => 'Aktif',
        'inactive' => 'Tidak Aktif',
        'suspended' => 'Ditangguhkan',
    ];

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'business_relation_company')
            ->withTimestamps();
    }

    public function customFields()
    {
        return $this->hasMany(BusinessRelationCustomField::class);
    }

    public function creditTerms()
    {
        return $this->hasOne(BusinessRelationCreditTerm::class);
    }

    public function tags()
    {
        return $this->hasMany(BusinessRelationTag::class);
    }
} 