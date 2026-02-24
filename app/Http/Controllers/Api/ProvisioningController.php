<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountProvisioner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProvisioningController extends Controller
{
    public function store(Request $request, AccountProvisioner $accountProvisioner)
    {
        $validator = Validator::make($request->all(), [
            'username' => ['nullable', 'string', 'max:50', 'regex:/^[\p{L}0-9-_]+$/u'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'password' => ['nullable', 'string', 'min:12', 'max:255'],
            'display_name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:500'],
            'links' => ['nullable', 'array', 'max:30'],
            'links.*.title' => ['required_with:links', 'string', 'max:255'],
            'links.*.url' => ['required_with:links', 'url', 'max:2048'],
            'links.*.order' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'avatar' => ['nullable', 'array'],
            'avatar.type' => ['required_with:avatar', Rule::in(['url', 'base64', 'none'])],
            'avatar.value' => ['nullable', 'string'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $links = $request->input('links', []);
            foreach ($links as $index => $link) {
                $url = $link['url'] ?? null;
                $scheme = is_string($url) ? parse_url($url, PHP_URL_SCHEME) : null;
                if (!in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
                    $validator->errors()->add("links.$index.url", 'Only http(s) URLs are allowed.');
                }
            }

            $avatarType = $request->input('avatar.type');
            if ($avatarType === 'url') {
                $avatarValue = $request->input('avatar.value');
                $scheme = is_string($avatarValue) ? parse_url($avatarValue, PHP_URL_SCHEME) : null;
                if (!in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
                    $validator->errors()->add('avatar.value', 'Avatar URL must use http(s).');
                }
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'validation_failed',
                'details' => $validator->errors(),
            ], 422);
        }

        if ($request->filled('username') && User::where('littlelink_name', $request->string('username')->toString())->exists()) {
            return response()->json([
                'ok' => false,
                'error' => 'conflict',
                'details' => ['username' => ['The username is already in use.']],
            ], 409);
        }

        if ($request->filled('email') && User::where('email', $request->string('email')->lower()->toString())->exists()) {
            return response()->json([
                'ok' => false,
                'error' => 'conflict',
                'details' => ['email' => ['The email is already in use.']],
            ], 409);
        }

        try {
            $result = $accountProvisioner->provision($validator->validated());

            Log::info('Provisioning API success', [
                'ip' => $request->ip(),
                'token_hint' => substr((string) $request->bearerToken(), 0, 6),
                'user_id' => $result['user_id'],
            ]);

            return response()->json([
                'ok' => true,
                'user_id' => $result['user_id'],
                'public_url' => $result['public_url'],
            ], 201);
        } catch (ValidationException $exception) {
            Log::warning('Provisioning API validation exception', [
                'ip' => $request->ip(),
                'errors' => $exception->errors(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'validation_failed',
                'details' => $exception->errors(),
            ], 422);
        } catch (\Throwable $exception) {
            Log::error('Provisioning API unexpected failure', [
                'ip' => $request->ip(),
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'error' => 'unexpected_error',
            ], 500);
        }
    }
}
