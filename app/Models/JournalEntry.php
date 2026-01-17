<?php

namespace App\Models;

use App\Traits\HandlesRetainedEarnings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JournalEntry extends Model
{
    use HasFactory, HandlesRetainedEarnings;

    protected $guarded = [];

    protected static function booted()
    {
        static::created(function ($journalEntry) {
            $journalEntry->createRetainedEarningsJournal();
        });

        static::deleting(function ($journalEntry) {
            $journalEntry->deleteRetainedEarningsJournal();
        });

        static::addGlobalScope('userJournalEntries', function ($builder) {
            if (Auth::check()) {
                $user = User::find(Auth::user()->global_id);
                
                // Skip scope if user doesn't exist in tenant DB yet (e.g., during seeding)
                if (!$user) {
                    return;
                }
                
                $builder->whereHas('journal');
            }
        });
    }

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function retainedEarningsJournal()
    {
        return $this->belongsTo(Journal::class, 'retained_earnings_journal_id');
    }
}
