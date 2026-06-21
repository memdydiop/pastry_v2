<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class WhatsAppTemplate extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_templates';

    protected $fillable = ['key', 'label', 'message', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function getMessage(string $key, array $replace = []): ?string
    {
        if (! Schema::hasTable('whatsapp_templates')) {
            return null;
        }

        $template = static::where('key', $key)->where('is_active', true)->first();

        if (! $template) {
            return null;
        }

        $message = $template->message;

        foreach ($replace as $search => $replaceWith) {
            $message = str_replace('{'.$search.'}', $replaceWith, $message);
        }

        return $message;
    }

    public static function generateLink(string $phone, string $key, array $replace = []): ?string
    {
        $message = static::getMessage($key, $replace);

        if (! $message) {
            return null;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

        return 'https://wa.me/'.$cleanPhone.'?text='.urlencode($message);
    }
}
