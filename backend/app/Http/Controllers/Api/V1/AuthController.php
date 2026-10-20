<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Auth\Actions\CompleteGoogleUser;
use App\Domain\Auth\Actions\LoginUser;
use App\Domain\Auth\Actions\RegisterUser;
use App\Domain\Auth\Actions\SocialiteGoogleGateway;
use App\Domain\Auth\Contracts\GoogleOAuthGateway;
use App\Domain\Auth\Data\OAuthOutcome;
use App\Domain\Auth\Data\UserRole;
use App\Domain\Auth\Rules\TurnstileRule;
use App\Domain\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * API de autenticación (§7 del plan).
 * Errores con formato común: {message, errors?, code} en español.
 */
final class AuthController extends Controller
{
    public function __construct(
        private readonly RegisterUser $registerUser,
        private readonly LoginUser $loginUser,
        private readonly GoogleOAuthGateway $googleGateway,
        private readonly CompleteGoogleUser $completeGoogleUser,
    ) {}

    /** POST /api/v1/auth/register — RF-1, RF-3 */
    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'turnstile_token' => $request->input('turnstile_token') ?? $request->input('cf-turnstile-response'),
        ]);

        $rules = array_merge(RegisterUser::rules(), [
            'turnstile_token' => [new TurnstileRule($request->ip())],
        ]);

        $data = $request->validate($rules);

        $user = ($this->registerUser)(
            name: $data['name'],
            email: $data['email'],
            password: $data['password'],
            role: UserRole::from($data['role']),
        );

        // Teacher pendiente no recibe token todavía (RB-02)
        $payload = [
            'user' => $this->userResource($user),
            'status' => $user->status,
        ];

        if ($user->isStudent()) {
            $payload['token'] = $user->createToken('spa')->plainTextToken;
        }

        return response()->json($payload, 201);
    }

    /** POST /api/v1/auth/login — RF-1; 423 teacher pendiente */
    public function login(Request $request): JsonResponse
    {
        $request->merge([
            'turnstile_token' => $request->input('turnstile_token') ?? $request->input('cf-turnstile-response'),
        ]);

        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'turnstile_token' => [new TurnstileRule($request->ip())],
        ]);

        $user = ($this->loginUser)(email: $data['email'], password: $data['password']);

        return response()->json([
            'user' => $this->userResource($user),
            'token' => $user->createToken('spa')->plainTextToken,
        ]);
    }

    /** GET /api/v1/auth/google/redirect — RF-2 */
    public function googleRedirect()
    {
        return \Laravel\Socialite\Facades\Socialite::driver('google')->redirect();
    }

    /** GET /api/v1/auth/google/callback — RF-2, RB-03, RB-04 */
    public function googleCallback(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $outcome = $this->googleGateway->resolve();

        if ($outcome instanceof \App\Domain\Auth\Data\OAuthLogin) {
            $user = $outcome->user;

            if ($user->status === 'pending_approval') {
                // RB-02: docente pendiente → FE con mensaje claro
                return redirect()->away($this->feUrl('/index.html?google_status=pending'));
            }

            $token = $user->createToken('google-oauth')->plainTextToken;

            return redirect()->away($this->feUrl('/google-landing.html?token='.$token.'&user_id='.$user->id));
        }

        /** @var \App\Domain\Auth\Data\RequireRoleChoice $outcome */
        // Usuario nuevo → FE con formulario posterior de nombre y rol (RF-2)
        $params = http_build_query([
            'google_id' => $outcome->googleId,
            'email' => $outcome->email,
            'name' => $outcome->name ?? '',
            'avatar_url' => $outcome->avatarUrl ?? '',
        ]);

        return redirect()->away($this->feUrl('/google-landing.html?'.$params));
    }

    /** ORIGEN del frontend (para redirects 302 desde callbacks) */
    private function feUrl(string $path): string
    {
        // Solo la variable de entorno; sin cache de config (llevó a un puerto viejo una vez)
        return rtrim(env('FRONTEND_URL', 'http://localhost:5500'), '/').$path;
    }

    /** POST /api/v1/auth/google/complete — RF-2 (formulario posterior nombre+rol) */
    public function googleComplete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'google_id' => ['required', 'string'],
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:120'],
            'avatar_url' => ['nullable', 'url'],
            'role' => ['required', 'in:student,teacher'], // RB-01
        ]);

        // Reconstruir el outcome desde datos ya validados por Google en el callback
        $outcome = \App\Domain\Auth\Data\OAuthOutcome::requireRoleChoice(
            googleId: $data['google_id'],
            email: $data['email'],
            name: $data['name'],
            avatarUrl: $data['avatar_url'] ?? null,
        );

        $user = ($this->completeGoogleUser)(
            outcome: $outcome,
            role: UserRole::from($data['role']),
            name: $data['name'] ?? null,
        );

        $payload = ['user' => $this->userResource($user), 'status' => $user->status];
        if ($user->status === 'active') {
            $payload['token'] = $user->createToken('spa')->plainTextToken;
        }

        return response()->json($payload, 201);
    }

    /** POST /api/v1/auth/logout */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(status: 204);
    }

    /** GET /api/v1/me */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userResource($request->user())]);
    }

    /** PUT /api/v1/me/profile */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'avatar_url' => ['nullable', 'string', 'max:255'],
        ]);

        $user->name = $data['name'];
        if (array_key_exists('avatar_url', $data)) {
            $user->avatar_url = $data['avatar_url'];
        }
        $user->save();

        return response()->json([
            'message' => 'Perfil actualizado correctamente.',
            'user' => $this->userResource($user),
        ]);
    }

    /** PUT /api/v1/me/password */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $hasExistingPassword = ! empty($user->password);

        $rules = [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        if ($hasExistingPassword) {
            $rules['current_password'] = ['required', 'string', 'current_password'];
        }

        $data = $request->validate($rules);

        $user->password = \Illuminate\Support\Facades\Hash::make($data['password']);
        $user->save();

        return response()->json([
            'message' => 'Contraseña actualizada exitosamente.',
        ]);
    }

    /** @return array<string, mixed> */
    private function userResource(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
            'learning_track' => $user->learning_track,
            'avatar_url' => $user->avatar_url,
        ];
    }
}
