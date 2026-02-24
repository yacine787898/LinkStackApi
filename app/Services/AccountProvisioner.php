<?php

namespace App\Services;

use App\Models\Button;
use App\Models\Link;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountProvisioner
{
    private const MAX_AVATAR_BYTES = 2097152; // 2MB

    public function provision(array $payload): array
    {
        return DB::transaction(function () use ($payload) {
            $username = $this->resolveUsername($payload['username'] ?? null, $payload['display_name']);
            $email = $this->resolveEmail($payload['email'] ?? null, $username);
            $password = $payload['password'] ?? Str::password(32);

            $user = User::create([
                'name' => $payload['display_name'],
                'email' => $email,
                'password' => Hash::make($password),
                'littlelink_name' => $username,
                'littlelink_description' => $payload['bio'] ?? null,
                'role' => 'user',
                'block' => 'no',
            ]);

            $this->storeAvatar($user->id, $payload['avatar'] ?? ['type' => 'none']);
            $this->storeLinks($user->id, $payload['links'] ?? []);

            return [
                'user_id' => $user->id,
                'public_url' => url('/@' . $username),
            ];
        });
    }

    private function resolveUsername(?string $requestedUsername, string $displayName): string
    {
        $candidate = $requestedUsername ? Str::lower(trim($requestedUsername)) : Str::slug($displayName, '-');
        $candidate = preg_replace('/[^\p{L}0-9-_]/u', '', $candidate ?? '') ?: 'user';
        $base = Str::limit($candidate, 50, '');

        $username = $base;
        while (User::where('littlelink_name', $username)->exists()) {
            $suffix = '-' . Str::lower(Str::random(6));
            $username = Str::limit($base, 50 - strlen($suffix), '') . $suffix;
        }

        return $username;
    }

    private function resolveEmail(?string $requestedEmail, string $username): string
    {
        if ($requestedEmail) {
            return Str::lower(trim($requestedEmail));
        }

        $email = $username . '@local.invalid';
        while (User::where('email', $email)->exists()) {
            $email = $username . '+' . Str::lower(Str::random(6)) . '@local.invalid';
        }

        return $email;
    }

    private function storeLinks(int $userId, array $links): void
    {
        $defaultButtonId = Button::query()->value('id') ?? 1;

        foreach ($links as $index => $linkData) {
            Link::create([
                'user_id' => $userId,
                'title' => $linkData['title'],
                'link' => $linkData['url'],
                'order' => $linkData['order'] ?? ($index + 1),
                'button_id' => $defaultButtonId,
            ]);
        }
    }

    private function storeAvatar(int $userId, array $avatar): void
    {
        $type = $avatar['type'] ?? 'none';
        if ($type === 'none') {
            return;
        }

        $binary = match ($type) {
            'url' => $this->downloadAvatar($avatar['value'] ?? null),
            'base64' => $this->decodeAvatar($avatar['value'] ?? null),
            default => throw ValidationException::withMessages(['avatar.type' => 'Unsupported avatar type.']),
        };

        if (strlen($binary) > self::MAX_AVATAR_BYTES) {
            throw ValidationException::withMessages(['avatar.value' => 'Avatar exceeds maximum allowed size (2MB).']);
        }

        $mime = finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $binary);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            throw ValidationException::withMessages(['avatar.value' => 'Avatar must be a valid image type (jpeg, png, gif, webp).']);
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'png',
        };

        $filename = $userId . '_' . time() . '.' . $extension;
        Storage::disk('public')->put('provisioning-avatars/' . $filename, $binary);

        $legacyPath = base_path('assets/img/' . $filename);
        if (!is_dir(dirname($legacyPath))) {
            mkdir(dirname($legacyPath), 0755, true);
        }
        file_put_contents($legacyPath, $binary);
    }

    private function downloadAvatar(?string $value): string
    {
        if (!$value) {
            throw ValidationException::withMessages(['avatar.value' => 'Avatar URL is required.']);
        }

        $response = Http::timeout(8)->connectTimeout(3)->accept('image/*')->get($value);
        if (!$response->successful()) {
            throw ValidationException::withMessages(['avatar.value' => 'Unable to download avatar URL.']);
        }

        $contentType = strtolower($response->header('Content-Type', ''));
        if (!Str::startsWith($contentType, 'image/')) {
            throw ValidationException::withMessages(['avatar.value' => 'Avatar URL must point to an image.']);
        }

        return $response->body();
    }

    private function decodeAvatar(?string $value): string
    {
        if (!$value) {
            throw ValidationException::withMessages(['avatar.value' => 'Avatar base64 value is required.']);
        }

        $raw = preg_replace('/^data:image\/[a-zA-Z0-9.+-]+;base64,/', '', $value);
        $decoded = base64_decode((string) $raw, true);

        if ($decoded === false) {
            throw ValidationException::withMessages(['avatar.value' => 'Avatar base64 payload is invalid.']);
        }

        return $decoded;
    }
}
