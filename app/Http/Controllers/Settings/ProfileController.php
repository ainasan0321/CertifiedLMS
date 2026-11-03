<?php

namespace App\Http\Controllers\Settings;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('settings.profile', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        // 現在ログイン中のユーザー情報をまるごと持ってくる
        $user = $request->user();
        // バリデーションを突破した問題がないデータを抜き出してほしい
        $validated = $request->validated();

        // コーチ以外のロールの場合は、meeting_url を完全に除外して不正更新を防ぐ
        if ($user->role !== UserRole::Coach) {
            unset($validated['meeting_url']);
        }

        $user->update($validated);

        return redirect()->route('settings.profile.edit')->with('success', 'プロフィールを更新しました。');
    }
}
