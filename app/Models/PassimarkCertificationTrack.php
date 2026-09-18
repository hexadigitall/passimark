<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A certification track = one bundle/variant row within a certification *category*
 * (cert_key). Multiple rows may share a cert_key; `slug` stays globally unique.
 * Seeders, the .psmk importer and the admin CRUD all place bundles through
 * {@see self::placeBundle()} so new content always lands in the right category row.
 */
class PassimarkCertificationTrack extends Model {
    protected $table='passimark_certification_tracks';
    protected $fillable=['slug','title','description','region','advancement','is_active','cert_key','variant_label','source'];
    protected $casts=['is_active'=>'boolean'];
    public function sessions(){ return $this->hasMany(PassimarkSession::class,'certification_track_id'); }

    /** The certification category this row belongs to (falls back to its own slug). */
    public function certKey(): string
    {
        return $this->cert_key ?: $this->slug;
    }

    public function scopeForCert(Builder $query, string $certKey): Builder
    {
        return $query->where(fn ($q) => $q->where('cert_key', $certKey)
            ->orWhere(fn ($fallback) => $fallback->whereNull('cert_key')->where('slug', $certKey)));
    }

    /**
     * Resolve a globally-unique bundle slug: keep the preferred slug when free,
     * otherwise append -v2, -v3, ... deterministically.
     */
    public static function resolveUniqueSlug(string $preferred, ?int $ignoreId = null): string
    {
        $base = Str::slug($preferred) ?: 'cert';
        $candidate = $base;
        $i = 2;
        while (self::where('slug', $candidate)->when($ignoreId, fn ($q, $id) => $q->where('id', '!=', $id))->exists()) {
            $candidate = "{$base}-v{$i}";
            $i++;
        }
        return $candidate;
    }

    /**
     * Create-or-adopt a bundle row and return it.
     *
     *  - Same `source` already present → reuse that row (idempotent re-seed/import).
     *  - A single leftover legacy placeholder (source = 'legacy', older installs where
     *    the row was the only row for this cert) → adopt it so existing enrollments and
     *    URLs keep the familiar slug ('cissp').
     *  - Anything else → new row; the slug is de-conflicted (-v2, -v3, ...) so a second
     *    CISSP bundle never overwrites the first.
     *
     * Callers decide on *sessions* (the CISSP bundle wipes and reseeds on its own row).
     *
     * @param array $attrs slug|title|description|region|advancement|is_active|cert_key|variant_label
     * @return array{track: PassimarkCertificationTrack, created: bool, slug_changed: bool}
     */
    public static function placeBundle(array $attrs, string $source): array
    {
        $preferred = $attrs['slug'] ?? $attrs['cert_key'] ?? $attrs['title'] ?? 'cert';
        $certKey = ($attrs['cert_key'] ?? null) ?: $preferred;

        $existing = self::where('source', $source)
            ->where(fn ($q) => $q->where('cert_key', $certKey)->orWhereNull('cert_key'))
            ->first()
            ?? self::where('slug', $preferred)
                ->where(fn ($q) => $q->whereNull('source')->orWhere('source', 'legacy'))
                ->first();

        if ($existing) {
            $slugChanged = $existing->slug !== $preferred;
            $existing->update([
                'title' => $attrs['title'] ?? $existing->title,
                'description' => $attrs['description'] ?? $existing->description,
                'region' => array_key_exists('region', $attrs) ? $attrs['region'] : $existing->region,
                'advancement' => array_key_exists('advancement', $attrs) ? $attrs['advancement'] : $existing->advancement,
                'is_active' => $attrs['is_active'] ?? $existing->is_active,
                'cert_key' => $certKey,
                'variant_label' => $attrs['variant_label'] ?? $existing->variant_label,
                'source' => $source,
            ]);
            return ['track' => $existing, 'created' => false, 'slug_changed' => $slugChanged];
        }

        $track = self::create([
            'slug' => self::resolveUniqueSlug($preferred),
            'title' => $attrs['title'] ?? $preferred,
            'description' => $attrs['description'] ?? null,
            'region' => $attrs['region'] ?? null,
            'advancement' => $attrs['advancement'] ?? 'approval',
            'is_active' => $attrs['is_active'] ?? true,
            'cert_key' => $certKey,
            'variant_label' => $attrs['variant_label'] ?? null,
            'source' => $source,
        ]);

        return ['track' => $track, 'created' => true, 'slug_changed' => false];
    }
}