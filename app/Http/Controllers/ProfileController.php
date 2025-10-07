<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class ProfileController extends Controller
{
    /** Reglas del avatar (pensadas para hosting compartido) */
    private const AVATAR_MIN_SIDE = 128;     // px
    private const AVATAR_MAX_SIDE = 4096;    // px
    private const AVATAR_MAX_KB = 4096;    // 4 MB
    private const AVATAR_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];
    private const AVATAR_EXTS = ['jpeg', 'jpg', 'png', 'webp', 'avif'];

    public function show(User $user): ViewContract
    {
        return view('profile.show', compact('user'));
    }

    public function edit(Request $request): ViewContract
    {
        $auth = $this->requireUser($request);
        return view('profile.edit', ['user' => $auth]);
    }

    /** Actualiza MI perfil (nombre, username, bio y avatar) */
    public function update(Request $request): RedirectResponse
    {
        $user = $this->requireUser($request);

        // --- Normalizaciones previas ---
        $usernameInput = $this->normalizeUsername($request->input('username'));
        $bioInput = $this->sanitizeBio($request->input('bio'));

        // Autogenerar username si falta
        if ($usernameInput === null && (bool) config('users.autogenerate_username', true)) {
            $rawBase = $request->input('username')
                ?: $request->input('name')
                ?: (str_contains((string) $user->email, '@') ? Str::before((string) $user->email, '@') : null)
                ?: ('user' . $user->id);
            $usernameInput = $this->generateUsername((string) $rawBase, (int) $user->id);
        }

        // --- Validación (incluye avatar_url) ---
        $reserved = $this->reservedUsernames();
        /** @var UploadedFile|null $avatarFile */
        $avatarFile = $request->file('avatar');

        $validator = Validator::make(
            [
                'name' => trim((string) $request->input('name')),
                'username' => $usernameInput,
                'bio' => $bioInput,
                'avatar' => $avatarFile,
                'avatar_url' => trim((string) $request->input('avatar_url')),
                'remove_avatar' => $request->boolean('remove_avatar'),
            ],
            [
                'name' => ['required', 'string', 'max:120'],
                'username' => [
                    'nullable',
                    'string',
                    'min:3',
                    'max:32',
                    'regex:/^[a-z0-9](?:[a-z0-9._-]{1,30}[a-z0-9])?$/',
                    'not_regex:/^\d+$/',
                    Rule::unique('users', 'username')->ignore($user->id),
                    Rule::notIn($reserved),
                ],
                'bio' => ['nullable', 'string', 'max:2000'],
                'avatar' => [
                    'nullable',
                    'file',
                    'mimetypes:' . implode(',', self::AVATAR_MIMES),
                    'mimes:' . implode(',', self::AVATAR_EXTS),
                    'max:' . self::AVATAR_MAX_KB, // KB
                ],
                'avatar_url' => [
                    'nullable',
                    'string',
                    'max:2048',
                    'url',
                    'starts_with:https://,http://',
                ],
                'remove_avatar' => ['boolean'],
            ],
            [
                'username.regex' => 'El usuario sólo puede contener letras, números, punto, guion y guion bajo, y no puede empezar/terminar con símbolos.',
                'username.not_regex' => 'El usuario no puede ser sólo numérico.',
                'avatar.mimetypes' => 'El avatar debe ser JPEG, PNG, WEBP o AVIF.',
                'avatar.mimes' => 'El avatar debe tener extensión JPEG, PNG, WEBP o AVIF.',
                'avatar.max' => 'El avatar no puede pesar más de 4 MB.',
                'avatar_url.url' => 'El link del avatar debe ser una URL válida.',
                'avatar_url.starts_with' => 'El link del avatar debe iniciar con http:// o https://',
            ]
        );

        // Validaciones extra (MIME real + dimensiones) solo para upload de archivo
        $validator->after(function ($v) use ($avatarFile) {
            if (!$avatarFile)
                return;

            try {
                if (class_exists(\finfo::class)) {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mime = (string) ($finfo->file($avatarFile->getPathname()) ?: '');
                    if (!in_array($mime, self::AVATAR_MIMES, true)) {
                        $v->errors()->add('avatar', 'El archivo no parece ser una imagen válida (MIME no permitido).');
                        return;
                    }
                }

                [$w, $h] = @getimagesize($avatarFile->getPathname()) ?: [0, 0];
                if ($w < self::AVATAR_MIN_SIDE || $h < self::AVATAR_MIN_SIDE) {
                    $v->errors()->add('avatar', 'La imagen debe ser de al menos ' . self::AVATAR_MIN_SIDE . '×' . self::AVATAR_MIN_SIDE . ' píxeles.');
                }
                if ($w > self::AVATAR_MAX_SIDE || $h > self::AVATAR_MAX_SIDE) {
                    $v->errors()->add('avatar', 'La imagen no puede exceder ' . self::AVATAR_MAX_SIDE . '×' . self::AVATAR_MAX_SIDE . ' píxeles.');
                }
            } catch (\Throwable) {
                $v->errors()->add('avatar', 'No se pudo leer la imagen subida.');
            }
        });

        $data = $validator->validate();

        // --- Manejo de avatar (remove / upload / URL) ---
        if (!empty($data['remove_avatar'])) {
            $this->deleteAvatarIfAny($user);
            $user->avatar_path = null;

        } elseif ($avatarFile) {
            // Subida directa
            $newPath = $this->storeAvatar((int) $user->id, $avatarFile);
            if ($newPath !== '') {
                $this->deleteAvatarIfAny($user);
                $user->avatar_path = $newPath;
            }

        } elseif (!empty($data['avatar_url']) && (bool) config('users.allow_remote_avatar', true)) {
            // Desde URL (solo si NO hubo archivo)
            try {
                // Chequeo preliminar de tamaño por Content-Length (si existe)
                $this->assertRemoteFileSize($data['avatar_url'], self::AVATAR_MAX_KB * 1024);

                $tmpUploaded = $this->downloadRemoteImageAsUploadedFile($data['avatar_url']);
            } catch (\Throwable $e) {
                return back()->withErrors(['avatar_url' => $e->getMessage()])->withInput();
            }

            // Validar dimensiones/MIME del archivo temporal igual que upload
            try {
                if (class_exists(\finfo::class)) {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    $mime = (string) ($finfo->file($tmpUploaded->getPathname()) ?: '');
                    if (!in_array($mime, self::AVATAR_MIMES, true)) {
                        @unlink($tmpUploaded->getPathname());
                        return back()->withErrors(['avatar_url' => 'El link no apunta a una imagen válida.'])->withInput();
                    }
                }
                [$w, $h] = @getimagesize($tmpUploaded->getPathname()) ?: [0, 0];
                if ($w < self::AVATAR_MIN_SIDE || $h < self::AVATAR_MIN_SIDE) {
                    @unlink($tmpUploaded->getPathname());
                    return back()->withErrors(['avatar_url' => 'La imagen del link es demasiado pequeña.'])->withInput();
                }
                if ($w > self::AVATAR_MAX_SIDE || $h > self::AVATAR_MAX_SIDE) {
                    @unlink($tmpUploaded->getPathname());
                    return back()->withErrors(['avatar_url' => 'La imagen del link excede el tamaño máximo permitido.'])->withInput();
                }
            } catch (\Throwable) {
                @unlink($tmpUploaded->getPathname());
                return back()->withErrors(['avatar_url' => 'No se pudo procesar la imagen del link.'])->withInput();
            }

            // Guardar definitivamente
            $newPath = $this->storeAvatar((int) $user->id, $tmpUploaded);
            @unlink($tmpUploaded->getPathname()); // limpiar tmp
            if ($newPath !== '') {
                $this->deleteAvatarIfAny($user);
                $user->avatar_path = $newPath;
            }
        }

        // --- Guardar cambios solo si hay modificaciones ---
        $user->fill([
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'bio' => $data['bio'] ?? null,
        ]);

        if ($user->isDirty(['name', 'username', 'bio', 'avatar_path'])) {
            $user->save();
        }

        return redirect()->route('profile.edit')->with('ok', 'Perfil actualizado');
    }

    /* =========================
     * Helpers privados
     * ========================= */

    private function avatarDisk(): string
    {
        return (string) config('users.avatar_disk', config('filesystems.default', 'public'));
    }

    private function avatarDir(int $userId): string
    {
        return "avatars/{$userId}";
    }

    private function storeAvatar(int $userId, UploadedFile $file): string
    {
        $disk = $this->avatarDisk();
        $dir = $this->avatarDir($userId);

        $ext = strtolower((string) ($file->guessExtension() ?: $file->extension() ?: 'jpg'));
        if (!in_array($ext, self::AVATAR_EXTS, true)) {
            $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif'];
            $ext = $map[$file->getMimeType()] ?? 'jpg';
        }

        try {
            $rand = bin2hex(random_bytes(4));
        } catch (\Throwable) {
            $rand = bin2hex((string) mt_rand());
        }

        $name = sprintf('avatar_%d_%s.%s', time(), $rand, $ext);
        return (string) $file->storeAs($dir, $name, $disk);
    }

    private function deleteAvatarIfAny(User $user): void
    {
        $path = (string) ($user->avatar_path ?? '');
        if ($path === '')
            return;

        $ownDir = $this->avatarDir((int) $user->id);
        if (!Str::startsWith($path, $ownDir))
            return;

        try {
            $disk = $this->avatarDisk();
            Storage::disk($disk)->delete($path);
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            $base = substr($path, 0, -(strlen($ext) + 1));
            foreach ((array) config('users.avatar_thumb_sizes', [512, 256, 128]) as $w) {
                Storage::disk($disk)->delete("{$base}_w{$w}.{$ext}");
            }
        } catch (\Throwable) { /* no romper en hosting compartido */
        }
    }

    private function generateUsername(string $rawBase, int $ignoreUserId = 0): string
    {
        $reserved = $this->reservedUsernames();
        $maxLen = 32;
        $maxAttempts = (int) config('users.autogenerate_username_max_attempts', 50);

        $base = $this->normalizeUsername($rawBase)
            ?? $this->normalizeUsername('user' . $ignoreUserId)
            ?? 'user';

        if (Str::length($base) < 3)
            $base = str_pad($base, 3, '0');

        $isValid = function (string $c) use ($reserved): bool {
            if ($c === '' || strlen($c) < 3)
                return false;
            if (preg_match('/^\d+$/', $c))
                return false;
            if (in_array($c, $reserved, true))
                return false;
            return true;
        };
        $exists = function (string $c) use ($ignoreUserId): bool {
            return User::query()
                ->where('username', $c)
                ->when($ignoreUserId > 0, fn($q) => $q->where('id', '!=', $ignoreUserId))
                ->exists();
        };
        $mk = function (string $b, string $suf = '') use ($maxLen): string {
            $room = $maxLen - strlen($suf);
            $b = substr($b, 0, max(1, $room));
            return rtrim($b, '._-') . $suf;
        };

        $candidate = $mk($base);
        if ($isValid($candidate) && !$exists($candidate))
            return $candidate;

        $att = 0;
        for ($i = 1; $i <= 99 && $att < $maxAttempts; $i++, $att++) {
            $c = $mk($base, "-$i");
            if ($isValid($c) && !$exists($c))
                return $c;
        }
        for ($i = 100; $i <= 9999 && $att < $maxAttempts; $i += random_int(1, 97), $att++) {
            $c = $mk($base, "-$i");
            if ($isValid($c) && !$exists($c))
                return $c;
        }
        for (; $att < $maxAttempts; $att++) {
            $c = $mk($base, '-' . bin2hex(random_bytes(2)));
            if ($isValid($c) && !$exists($c))
                return $c;
        }
        return $mk('user', '-u' . ($ignoreUserId ?: random_int(1000, 9999)));
    }

    private function normalizeUsername(?string $value): ?string
    {
        if ($value === null)
            return null;
        $u = Str::of($value)->trim()->lower();
        $u = Str::of(Str::ascii($u));
        $u = $u->replaceMatches('/\s+/', '_')
            ->replaceMatches('/[^a-z0-9._-]/', '')
            ->replaceMatches('/([._-])\1+/', '$1')
            ->replaceMatches('/^[._-]+|[._-]+$/', '');
        $u = (string) $u;
        return $u === '' ? null : $u;
    }

    private function sanitizeBio(?string $bio): ?string
    {
        if ($bio === null)
            return null;
        $clean = strip_tags($bio);
        $clean = preg_replace('/\s+/', ' ', $clean) ?? '';
        $clean = trim($clean);
        return $clean === '' ? null : Str::limit($clean, 2000, '');
    }

    private function reservedUsernames(): array
    {
        static $cache = null;
        if ($cache !== null)
            return $cache;

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
            'home',
        ];
        $fromConfig = (array) config('users.reserved_usernames', []);
        $norm = fn(string $v) => $this->normalizeUsername($v);
        $merged = array_filter(array_map($norm, array_merge($defaults, $fromConfig)));
        return $cache = array_values(array_unique($merged));
    }

    /* ====== Helpers para avatar por URL (optimizados para hosting compartido) ====== */

    /** Lanza si Content-Length > $maxBytes (si el header existe). No descarga el cuerpo. */
    // Reemplazá tu método por este
    private function assertRemoteFileSize(string $url, int $maxBytes): void
    {
        $url = trim($url);
        if ($url === '' || !$this->isUrlAllowed($url)) {
            throw new \RuntimeException('El link no es permitido.');
        }

        // Si get_headers no está disponible o está deshabilitado, seguimos sin chequear (lo validaremos al descargar)
        if (!function_exists('get_headers')) {
            return;
        }

        // FIX: segundo parámetro como bool (PHP 8+) en lugar de 1
        $headers = @get_headers($url, true);
        if ($headers === false) {
            return; // sin headers → validaremos durante la descarga por stream/cURL
        }

        // Normalizar a mapa en minúsculas (ignorando líneas de estado)
        $map = [];
        foreach ($headers as $k => $v) {
            if (is_int($k)) {
                continue;
            }
            $map[strtolower((string) $k)] = $v;
        }

        $len = $map['content-length'] ?? null;
        if ($len !== null) {
            $bytes = is_array($len) ? (int) end($len) : (int) $len;
            if ($bytes > $maxBytes) {
                throw new \RuntimeException('La imagen del link supera 4 MB.');
            }
        }
    }


    /** Descarga segura de imagen remota a archivo temporal y devuelve UploadedFile listo para store() */
    private function downloadRemoteImageAsUploadedFile(string $url): UploadedFile
    {
        $url = trim($url);
        if ($url === '' || !$this->isUrlAllowed($url)) {
            throw new \RuntimeException('El link no es permitido.');
        }

        $maxBytes = self::AVATAR_MAX_KB * 1024;
        $tmp = tempnam(sys_get_temp_dir(), 'avatar_');
        if ($tmp === false) {
            throw new \RuntimeException('No se pudo crear un archivo temporal.');
        }

        $ok = $this->streamDownload($url, $tmp, $maxBytes);
        if (!$ok) {
            @unlink($tmp);
            throw new \RuntimeException('No se pudo descargar la imagen del link.');
        }

        // MIME real → nombre original y extensión
        $mime = 'application/octet-stream';
        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string) ($finfo->file($tmp) ?: $mime);
        }
        if (!in_array($mime, self::AVATAR_MIMES, true)) {
            @unlink($tmp);
            throw new \RuntimeException('El link no apunta a una imagen válida.');
        }

        $extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/avif' => 'avif'];
        $ext = $extMap[$mime] ?? 'jpg';

        // UploadedFile de test (no moverá el archivo al final)
        return new UploadedFile($tmp, 'remote_avatar.' . $ext, $mime, null, true);
    }

    /** Intenta descargar por cURL y si no, por stream (fopen). Limita por tamaño. */
    private function streamDownload(string $url, string $destPath, int $maxBytes): bool
    {
        // 1) cURL si está disponible (común en Hostinger)
        if (function_exists('curl_init')) {
            $fh = @fopen($destPath, 'wb');
            if (!$fh)
                return false;

            $read = 0;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => 20,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'AvatarFetcher/1.0',
                CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$read, $maxBytes, $fh) {
                    $len = strlen($data);
                    $read += $len;
                    if ($read > $maxBytes)
                        return 0; // aborta
                    return fwrite($fh, $data);
                },
            ]);
            $ok = curl_exec($ch) === true;
            $err = curl_error($ch);
            curl_close($ch);
            fclose($fh);

            if (!$ok || $err) {
                @unlink($destPath);
                return false;
            }
            return true;
        }

        // 2) Streams (allow_url_fopen debe estar habilitado)
        $read = 0;
        $chunk = 64 * 1024;
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 20,
                'follow_location' => 1,
                'max_redirects' => 3,
                'header' => "User-Agent: AvatarFetcher/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $in = @fopen($url, 'rb', false, $ctx);
        $out = @fopen($destPath, 'wb');
        if (!$in || !$out) {
            @is_resource($in) && fclose($in);
            @is_resource($out) && fclose($out);
            return false;
        }

        try {
            while (!feof($in)) {
                $buf = fread($in, $chunk);
                if ($buf === false) {
                    return false;
                }
                $read += strlen($buf);
                if ($read > $maxBytes) {
                    return false;
                }
                if (fwrite($out, $buf) === false) {
                    return false;
                }
            }
        } finally {
            fclose($in);
            fclose($out);
        }

        return true;
    }

    /** Guardas anti-SSRF básicas (http/https, sin localhost/IP privada) */
    private function isUrlAllowed(string $url): bool
    {
        $p = @parse_url($url);
        if (!$p || !isset($p['scheme'], $p['host']))
            return false;

        $scheme = strtolower($p['scheme']);
        if (!in_array($scheme, ['http', 'https'], true))
            return false;

        $host = strtolower($p['host']);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true))
            return false;

        // Si es IP literal, bloquea privadas/reservadas
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }

        // Resolver A (básico) y bloquear privadas
        $ips = @gethostbynamel($host) ?: [];
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return true;
    }
}
