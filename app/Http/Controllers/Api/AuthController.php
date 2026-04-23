<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => ['Akun Anda tidak aktif. Hubungi administrator.'],
            ]);
        }

        $user->update(['last_login_at' => now()]);

        $token = $user->createToken('csm-inventory')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => $this->userResource($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $this->userResource($request->user()),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['success' => true, 'message' => 'Logged out']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        $user = $request->user();
        if (!\Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Password lama salah.']]);
        }

        $user->update(['password' => bcrypt($request->password)]);
        return response()->json(['success' => true, 'message' => 'Password berhasil diubah']);
    }

    private function userResource(User $user): array
    {
        $user->load('roles', 'warehouse');
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'position' => $user->position,
            'warehouse_id' => $user->warehouse_id,
            'warehouse' => $user->warehouse ? [
                'id' => $user->warehouse->id,
                'name' => $user->warehouse->name,
                'type' => $user->warehouse->type,
                'code' => $user->warehouse->code,
            ] : null,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'is_superuser' => $user->isSuperuser(),
            'is_admin_ho' => $user->isAdminHO(),
        ];
    }
}
