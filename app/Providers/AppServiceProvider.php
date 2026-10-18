<?php

declare(strict_types=1);

namespace App\Providers;

use App\View\Composers\EnrollmentSwitcherComposer;
use App\View\Composers\NotificationBadgeComposer;
use App\View\Composers\SectionPageMetaComposer;
use App\View\Composers\SidebarBadgeComposer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use App\Models\EnrollmentGoal;
use Illuminate\Support\ServiceProvider;
use App\Policies\EnrollmentGoalPolicy;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts._partials.sidebar-*', SidebarBadgeComposer::class);
        View::composer('layouts._partials.topbar', NotificationBadgeComposer::class);
        View::composer('components.enrollment-switcher', EnrollmentSwitcherComposer::class);
        View::composer('learning.sections.show', SectionPageMetaComposer::class);

        View::composer(['enrollment-goal.form', 'enrollment-goal._form', '*enrollment-goal*'], function ($view) {
            
            // 1. 親ビューのデータ、またはURLのパラメータ(01m4shvw94pd1szw05bz4he7ar)から大元の受講登録モデルを取得
            $enrollment = $view->getData()['enrollment'] ?? request()->route('enrollment');

            // 2. もしURLパラメータから文字列(ULID)としてしか取れなかった場合の強力なセーフティフォールバック
            if (is_string($enrollment)) {
                $enrollment = \App\Models\Enrollment::find($enrollment);
            }

            // 3. $enrollment が無事にモデルインスタンスとして確定した場合のみデータを流し込む
            if ($enrollment instanceof \App\Models\Enrollment) {
                // 要件⑧「未達成が先、かつ期日順」でソートした目標データを取得
                $goals = $enrollment->goals()
                    ->orderBy('achieved_at', 'asc')
                    ->orderBy('target_date', 'asc')
                    ->get();

                // 💡【最重要】Viewが10行目で「$enrollment->goals」を呼び出した瞬間に
                // データベースへ再見に行くのを防ぎ、このソート済みのコレクションを強制的に掴ませます
                $enrollment->setRelation('goals', $goals);

                // Viewの内部で待っている2つの変数（$enrollment と $goals）を100%確定させて手渡します
                $view->with([
                    'enrollment' => $enrollment,
                    'goals' => $goals,
                ]);
            }
        });
    }
}
