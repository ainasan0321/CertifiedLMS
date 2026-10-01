<?php

namespace App\Http\Controllers;

use App\Models\QaThread;
use App\Models\QaReply;
use App\Models\Certification;
use App\Enums\QaThreadStatus;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminQaThreadController extends Controller
{
    public function index(Request $request): View
    {
        // 送られてきた検索条件を取得
        $filters = $request->all();

        // 公開中も非公開も取得する
        $certifications = Certification::all();

        // ステータスの設定
        $publishedStatus = [];

        // 要件：管理者は資格の公開/非公開に関わらずすべての質問を監視・削除できる
        // 全件最新順で取得
        $threads = QaThread::with(['user', 'certification'])

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

            ->latest() // 新着順
            ->paginate(10);

        return view('qa-thread.index', compact('filters', 'certifications', 'publishedStatus', 'threads'));
    }

    public function show(string $thread): View
    {
        $thread = QaThread::with(['user', 'certification', 'replies.user'])->findOrFail($thread);

        $publishedStatus = [];

        return view('qa-thread.show', [
            'thread' => $thread,
            'publishedStatus' => $publishedStatus,
        ]);
    }

    public function destroy(string $thread): RedirectResponse
    {
        // 質問を取得
        $thread = QaThread::findOrFail($thread);

        // 回答の有無に関わらず」いつでも強制削除
        $thread->delete();

        return redirect()->route('admin.qa-board.index')->with('success', '質問を強制削除しました。');
    }

    public function destroyReply(string $thread, string $reply): RedirectResponse
    {
        // 回答を取得
        $reply = QaReply::where('qa_thread_id', $thread)->findOrFail($reply);

        // 回答を強制削除
        $reply->delete();

        return redirect()->route('admin.qa-board.show', $thread)->with('success', '回答を強制削除しました。');
    }
}
