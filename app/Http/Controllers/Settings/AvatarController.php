<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AvatarStoreRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AvatarController extends Controller
{
    public function store(AvatarStoreRequest $request): RedirectResponse
    {
        // 現在ログインしているユーザーの情報を取得
        $user = $request->user();

        $oldPath = $user->getRawOriginal('avatar_url');

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update([
            'avatar_url' => $path,
        ]);

        return redirect()->route('settings.profile.edit')->with('success', 'アイコン画像を設定しました。');
    }

    public function destroy(Request $request): RedirectResponse
    {
        // 現在ログインしているユーザーの情報を取得
        $user = $request->user();

        if ($user->avatar_url) {
            Storage::disk('public')->delete($user->avatar_url);
        }

        $user->update([
            'avatar_url' => null,
        ]);

        return redirect()->route('settings.profile.edit')->with('success', 'アイコン画像を削除しました。');
    }
}
