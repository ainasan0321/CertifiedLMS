<?php

declare(strict_types=1);

namespace App\Enums;

// お知らせ配信用：選択
enum AnnouncementTargetType: string
{
    case AllStudents = 'all';
    case Certification = 'certification';
    case User = 'user';

    // 画面（Blade）の ->label() で使う日本語変換
    public function label(): string
    {
        return match ($this) {
            self::AllStudents => '全受講生',
            self::Certification => '資格指定',
            self::User => 'ユーザー指定',
        };
    }
}