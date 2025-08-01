<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // NEW: Import BelongsTo

class Loan extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'loans'; // Explicitly define the table name

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'catalogue_id',
        'borrowed_at',
        'due_date',
        'returned_at',
        'status',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'borrowed_at' => 'datetime',
        'due_date' => 'datetime',
        'returned_at' => 'datetime', // Nullable datetime
    ];

    /**
     * Get the user (borrower) associated with the loan.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the book (catalogue item) associated with the loan.
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Catalogue::class, 'catalogue_id');
    }
}
