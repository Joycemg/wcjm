<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HonorEvent extends Model
{
    use HasFactory;

    // Decaimiento mensual por inactividad
    public const R_DECAY = 'decay:inactivity';

    // Asistencia
    public const R_ATTEND_OK = 'attendance:confirmed';
    public const R_ATTEND_UNDO = 'attendance:undo';

    // No show
    public const R_NO_SHOW = 'no_show';

    // Comportamiento
    public const R_BEHAV_GOOD = 'behavior:good';
    public const R_BEHAV_BAD = 'behavior:bad';
    public const R_BEHAV_UNDO_GOOD = 'behavior:undo_good';
    public const R_BEHAV_UNDO_BAD = 'behavior:undo_bad';

    protected $fillable = ['user_id', 'points', 'reason', 'meta', 'slug'];
    protected $casts = ['meta' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
