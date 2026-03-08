<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class StoreSettingsController extends Controller
{
    // GET /api/store/settings
    public function show(): JsonResponse
    {
        $store = Auth::user()->store;

        return response()->json([
            'id'            => $store->id,
            'name'          => $store->name,
            'phone'         => $store->phone,
            'address'       => $store->address,
            'slug'          => $store->slug,
            'logo_url'      => $store->logo_path ? asset('storage/' . $store->logo_path) : null,
            'print_header'  => $store->print_header,
            'print_phone'   => $store->print_phone,
            'print_address' => $store->print_address,
        ]);
    }

    // PUT /api/store/settings
    public function update(Request $request): JsonResponse
    {
        $store = Auth::user()->store;

        $validated = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:20'],
            'address'       => ['nullable', 'string'],
            'slug'          => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9\-]+$/',
                "unique:stores,slug,{$store->id}",
            ],
            'print_header'  => ['nullable', 'string', 'max:255'],
            'print_phone'   => ['nullable', 'string', 'max:20'],
            'print_address' => ['nullable', 'string'],
        ], [
            'slug.regex'  => 'الـ Slug يجب أن يحتوي على أحرف إنجليزية صغيرة وأرقام وشرطات فقط.',
            'slug.unique' => 'هذا الـ Slug مستخدم بالفعل.',
        ]);

        $store->update($validated);

        return response()->json([
            'message' => 'تم تحديث إعدادات المتجر بنجاح.',
            'store'   => [
                'id'            => $store->id,
                'name'          => $store->name,
                'phone'         => $store->phone,
                'address'       => $store->address,
                'slug'          => $store->slug,
                'logo_url'      => $store->logo_path ? asset('storage/' . $store->logo_path) : null,
                'print_header'  => $store->print_header,
                'print_phone'   => $store->print_phone,
                'print_address' => $store->print_address,
            ],
        ]);
    }

    // POST /api/store/settings/logo
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'file', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ], [
            'logo.required' => 'يرجى اختيار صورة.',
            'logo.image'    => 'الملف يجب أن يكون صورة.',
            'logo.max'      => 'حجم الصورة يتجاوز 2 ميجابايت.',
            'logo.mimes'    => 'الصيغة غير مدعومة. المسموح: JPG, PNG, WEBP.',
        ]);

        $store = Auth::user()->store;

        if ($store->logo_path) {
            Storage::disk('public')->delete($store->logo_path);
        }

        $path = $request->file('logo')->store("store-logos/{$store->id}", 'public');

        $store->update(['logo_path' => $path]);

        return response()->json([
            'message'  => 'تم رفع الشعار بنجاح.',
            'logo_url' => asset('storage/' . $path),
        ]);
    }

    // DELETE /api/store/settings/logo
    public function deleteLogo(): JsonResponse
    {
        $store = Auth::user()->store;

        if ($store->logo_path) {
            Storage::disk('public')->delete($store->logo_path);
            $store->update(['logo_path' => null]);
        }

        return response()->json(['message' => 'تم حذف الشعار.']);
    }

    // PUT /api/store/settings/password
    public function changePassword(Request $request): JsonResponse
    {
        $user = Auth::user();

        $request->validate([
            'current_password' => ['required'],
            'new_password'     => ['required', 'min:8', 'confirmed'],
        ], [
            'new_password.min'       => 'كلمة المرور الجديدة 8 أحرف على الأقل.',
            'new_password.confirmed' => 'تأكيد كلمة المرور غير متطابق.',
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'كلمة المرور الحالية غير صحيحة.',
            ]);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        $currentTokenId = $user->currentAccessToken()?->id;
        if ($currentTokenId) {
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        }

        return response()->json(['message' => 'تم تغيير كلمة المرور بنجاح.']);
    }
}
