<?php

namespace App\Http\Controllers;

use App\Models\QaThread;
use App\Models\QaReply;
use App\Models\Certification;
use App\Enums\CertificationStatus;
use App\Enums\QaThreadStatus;
use App\Http\Requests\QaBoard\StoreQaThreadRequest;
use App\Http\Requests\QaBoard\StoreQaReplyRequest;
use App\Http\Requests\QaBoard\UpdateQaThreadRequest;
use App\Http\Requests\QaBoard\UpdateQaReplyRequest;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserQaThreadController extends Controller
{
    public function index(Request $request): View
    {
        // 画面から送られてきた検索条件を丸ごと取得
        $filters = $request->all();
        $viewer = auth()->user();

        // 検索用の「資格一覧」を取得（受講生やコーチが選べるようにするため）
        // 要件「公開停止中の資格は受講生・コーチには見えない」を考慮し、status が published のものだけに絞り込みます
        $certifications = Certification::Published()
            ->when($viewer->role === 'coach', function ($query) use ($viewer) {
                $query->whereHas('coachAssignments', function ($q) use ($viewer) {
                    return $query->assignedTo($viewer);
                });
            })
            ->get();

        // 画面で使う公開ステータスの設定
        $publishedStatus = [];

        // 掲示板のスレッド一覧を効率的に取得
        $threads = QaThread::with(['user', 'certification'])
            // 要件「公開停止中の資格のスレッドは受講生・コーチには見えない」
            ->whereHas('certification', function($query) {
                $query->published();
            })
            // コーチは担当資格のスレッドのみ閲覧できる
            ->when($viewer->role === 'coach', function ($query) use ($viewer) {
                $query->whereHas('certification', function ($q) use ($viewer) {
                    $q->assignedTo($viewer);
                });
            })
            // キーワード検索
            ->when($request->filled('keyword'), function ($query) use ($request) {
                $keyword = $request->input('keyword');
                $query->where(function ($q) use ($keyword) {
                    $q->where('title', 'like', "%{$keyword}%")
                        ->orWhere('body', 'like', "%{$keyword}%");
                });
            })
            // 資格での絞り込み
            ->when($request->filled('certification_id'), function($query) use ($request) {
                $query->where('certification_id', $request->input('certification_id'));
            })
            //解決状態での絞り込み('unresolved' => '未解決', 'resolved' => '解決済',)
            ->when($request->filled('status'), function ($query) use ($request) {
                $status = $request->input('status');
                if($status === 'resolved') {
                    $query->where('status', QaThreadStatus::Resolved->value);
                } elseif($status === 'unresolved') {
                    $query->where('status', QaThreadStatus::Unresolved->value);
                }
            })
        ->latest() //新着順
        ->paginate(20);

        return view('qa-thread.index', compact('filters', 'certifications', 'publishedStatus', 'threads'));
    }

    public function create(): View
    {
        // 要件：受講生は公開済資格すべてのスレッドに投稿できる
        // 公開中の資格一覧を取得する
        $certifications = Certification::published()->get();

        return view('qa-thread.create', compact('certifications'));
    }

    public function show(string $thread): View
    {
        // 要件：件数が増えても遅くならないように回答一覧とその投稿者をまとめて取得
        $thread = QaThread::with(['user', 'certification', 'replies.user'])->findOrFail($thread);

        // アクセス制御：公開停止中は受講生・コーチには見せない
        if ($thread->certification->status->value !== CertificationStatus::Published->value)
            {
                abort(404);
            }

        $viewer = auth()->user();
        $canReply = false;

        if ($viewer) {
            if ($viewer->role->value === 'student') {
                $canReply = true;
            }elseif ($viewer->role->value === 'coach') {
                $canReply = $viewer->assignedCertifications()
                ->where('certifications.id', $thread->certification_id)
                ->exists();
            }
        }

        $publishedStatus = [];

        return view('qa-thread.show', [
            'thread' => $thread,
            'publishedStatus' => $publishedStatus,
        ]);
    }

    public function store(StoreQaThreadRequest $request): RedirectResponse
    {
        // バリデーションチェック
        $validated = $request->validated();

        // データベースに安全に保存
        // 初期状態は必ず未解決
        QaThread::create([
            'certification_id' => $validated['certification_id'],
            'user_id' => $request->user()->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'status' => \App\Enums\QaThreadStatus::Unresolved->value,
        ]);

        return redirect()->route('qa-board.index')->with('success', '質問を投稿しました。');
    }

    public function storeReply(StoreQaReplyRequest $request, string $thread): RedirectResponse
    {
        // バリデーションチェック
        $validated = $request->validated();

        // 該当する質問が存在するかチェック
        $thread = QaThread::findOrFail($thread);

        // データベースに安全に保存
        QaReply::create([
            'qa_thread_id' => $thread->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return redirect()->route('qa-board.show', $thread->id)->with('success', '回答を投稿しました。');
    }

    public function edit(string $thread): View
    {
        // 編集の質問を取得
        $thread = QaThread::findOrFail($thread);

        // 認可チェック
        $this->authorize('update',$thread);

        // 選択肢用の資格一覧を取得
        $certifications = Certification::published()->get();

        return view('qa-thread.edit', [
            'thread' => $thread,
            'certifications' => $certifications,
        ]);
    }

    public function update(UpdateQaThreadRequest $request, string $thread): RedirectResponse
    {
        // 編集の質問を取得
        $thread = QaThread::findOrFail($thread);

        // 認可チェック
        $this->authorize('update', $thread);

        // バリデーションチェック
        $validated = $request->validated();

        // データベースに安全に保存
        $thread->update([
            'title' => $validated['title'],
            'body' => $validated['body'],
        ]);

        return redirect()->route('qa-board.show', $thread->id)->with('success', '質問を更新しました。');
    }

    public function destroy(string $thread): RedirectResponse
    {
        //編集の質問を取得
        $thread = QaThread::findOrFail($thread);

        // 認可チェック
        $this->authorize('delete', $thread);

        // 要件 「回答がすでに付いている質問は削除できない」というルールがあるか確認
        if ($thread->replies()->exists()) {
            return redirect()
            ->route('qa-board.show', $thread->id)->with('error', 'すでに回答がついている質問は削除できません。');
        }

        // 質問を削除
        $thread->delete();

        return redirect()->route('qa-board.index')->with('success', '質問を削除しました。');
    }

    public function resolve(string $thread): RedirectResponse
    {
        // 質問を取得
        $thread = QaThread::findOrFail($thread);

        // 認可チェック
        if (auth()->id() !== $thread->user_id) {
            abort(403, '権限がありません。');
        }

        // 解決済に更新
        $thread->update([
            'status' => QaThreadStatus::Resolved->value,
        ]);

        return redirect()->route('qa-board.show', $thread->id)->with('success', '質問を解決済にしました。');
    }

    public function unresolve(string $thread): RedirectResponse
    {

        // 質問を取得
        $thread = QaThread::findOrFail($thread);

        // 認可チェック
        if (auth()->id() !== $thread->user_id) {
            abort(403, '権限がありません。');
        }

        // 解決済に更新
        $thread->update([
            'status' => QaThreadStatus::Unresolved->value,
        ]);

        return redirect()->route('qa-board.show', $thread->id)->with('success', '質問を未解決に戻しました。');
    }

    public function editReply(string $thread, string $reply): View
    {
        // 質問を取得
        $thread = QaThread::findOrFail($thread);

        // 回答を取得
        $reply = QaReply::where('qa_thread_id', $thread->id)->findOrFail($reply);

        // 認可チェック
        if (auth()->id() !== $reply->user_id) {
            abort(403, '権限がありません。');
        }

        return view('qa-thread.reply-edit', [
            'thread' => $thread,
            'reply' => $reply,
        ]);
    }

    public function updateReply(UpdateQaReplyRequest $request, string $thread, string $reply): RedirectResponse
    {
        // 回答を取得
        $reply = QaReply::where('qa_thread_id', $thread)->findOrFail($reply);

        // 認可チェック
        if (auth()->id() !== $reply->user_id) {
            abort(403, '権限がありません。');
        }

        // バリデーションチェック
        $validated = $request->validated();

        // データベースに安全に保存
        $reply->update([
            'body' =>$validated['body'],
        ]);

        return redirect()->route('qa-board.show', $thread)->with('success', '回答を更新しました。');
    }

    public function destroyReply(string $thread, string $reply): RedirectResponse
    {
        // 回答を取得
        $reply = QaReply::where('qa_thread_id', $thread)->findOrFail($reply);

        // 認可チェック
        if (auth()->id() !== $reply->user_id) {
            abort(403, '権限がありません。');
        }

        $reply->delete();

        return redirect()->route('qa-board.show', $thread)->with('success', '回答を削除しました。');
    }
}
