<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class ProfileUpdateRequest extends FormRequest
{
    /** Si querés cortar al primer error, poné true */
    public bool $stopOnFirstFailure = false;

    public function authorize(): bool
    {
        // Sólo usuarios autenticados pueden actualizar su perfil
        return (bool) $this->user();
    }

    /**
     * Normalizo inputs ANTES de validar:
     * - name: trim + colapso de espacios
     * - email: trim + lowercase
     * - username: normalizo (ascii, sin espacios ni símbolos fuera de [a-z0-9._-])
     * - bio: quita HTML y espacios extra
     */
    protected function prepareForValidation(): void
    {
        $name = Str::of((string) $this->input('name', ''))->squish();
        $email = Str::of((string) $this->input('email', ''))->lower()->trim();
        $username = $this->normalizeUsername($this->input('username'));
        $bio = $this->sanitizeBio($this->input('bio'));

        $this->merge([
            'name' => (string) $name,
            'email' => (string) $email,
            'username' => $username,   // null|string
            'bio' => $bio,        // null|string
        ]);
    }

    /**
     * Reglas de validación.
     * Incluye username y bio (opcionales) para unificar validación del perfil.
     */
    public function rules(): array
    {
        $userId = $this->user()->id ?? null;

        return [
            'name' => [
                'required',
                'string',
                'between:2,120',
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email:rfc,dns',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($userId),
            ],
            'username' => [
                'nullable',
                'string',
                'min:3',
                'max:32',
                // empieza y termina con alfanum; admite . _ -
                'regex:/^[a-z0-9](?:[a-z0-9._-]{1,30}[a-z0-9])?$/',
                'not_regex:/^\d+$/', // evita sólo numérico
                Rule::unique(User::class, 'username')->ignore($userId),
                Rule::notIn($this->reservedUsernames()),
            ],
            'bio' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Ingresá tu nombre.',
            'name.between' => 'El nombre debe tener entre :min y :max caracteres.',
            'email.required' => 'Ingresá tu email.',
            'email.email' => 'El email no es válido.',
            'email.unique' => 'Ese email ya está en uso.',
            'username.min' => 'El usuario debe tener al menos :min caracteres.',
            'username.max' => 'El usuario no puede superar :max caracteres.',
            'username.regex' => 'El usuario sólo puede contener letras, números, punto, guion y guion bajo, y no puede empezar/terminar con símbolos.',
            'username.not_regex' => 'El usuario no puede ser sólo numérico.',
            'username.unique' => 'Ese usuario ya está en uso.',
            'username.not_in' => 'Ese usuario está reservado y no puede usarse.',
            'bio.max' => 'La biografía no puede superar :max caracteres.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'email',
            'username' => 'usuario',
            'bio' => 'biografía',
        ];
    }

    /* =========================
     * Helpers privados
     * ========================= */

    /**
     * Normaliza username:
     * - trim, lowercase, ascii (quita tildes/ñ)
     * - espacios -> _
     * - sólo [a-z0-9._-]
     * - colapsa repeticiones del mismo símbolo y quita del inicio/fin
     * - devuelve null si queda vacío
     */
    private function normalizeUsername(mixed $value): ?string
    {
        if ($value === null)
            return null;
        $u = Str::of((string) $value)->trim()->lower();
        $u = Str::of(Str::ascii($u));
        $u = $u->replaceMatches('/\s+/', '_')
            ->replaceMatches('/[^a-z0-9._-]/', '')
            ->replaceMatches('/([._-])\1+/', '$1')
            ->replaceMatches('/^[._-]+|[._-]+$/', '');
        $u = (string) $u;
        return $u !== '' ? $u : null;
    }

    /**
     * Sanitiza bio: sin HTML, espacios normalizados y tope de 2000 chars.
     */
    private function sanitizeBio(mixed $bio): ?string
    {
        if ($bio === null)
            return null;
        $clean = strip_tags((string) $bio);
        $clean = preg_replace('/\s+/', ' ', $clean) ?? '';
        $clean = trim($clean);
        return $clean !== '' ? Str::limit($clean, 2000, '') : null;
    }

    /**
     * Lista de usernames reservados (mergea con config si existe).
     */
    private function reservedUsernames(): array
    {
        $defaults = [
            'admin',
            'root',
            'soporte',
            'support',
            'moderador',
            'moderator',
            'system',
            'api',
            'help',
            'contacto',
            'contact',
            'info',
            'user',
            'usuarios',
            'users',
            'staff',
            'team',
            'mesa',
            'mesas',
            'table',
            'tables',
            'profile',
            'perfil',
            'login',
            'logout',
            'register',
            'password',
            'settings',
            'config',
            'dashboard',
            'home'
        ];

        $extra = (array) config('users.reserved_usernames', []);
        $norm = fn(string $v) => $this->normalizeUsername($v);
        $merged = array_filter(array_map($norm, array_merge($defaults, $extra)));

        return array_values(array_unique($merged));
    }
}
