<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ContentStatus;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Services\LearningProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarize_returns_zeros_when_no_sections_exist(): void
    {
        $enrollment = Enrollment::factory()->create();

        $summary = app(LearningProgressService::class)->summarize($enrollment);

        $this->assertSame(0, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
        $this->assertSame(0.0, $summary->sectionCompletionRatio);
        $this->assertSame(0, $summary->chaptersTotal);
        $this->assertSame(0, $summary->chaptersCompleted);
        $this->assertSame(0.0, $summary->chapterCompletionRatio);
        $this->assertSame(0, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.0, $summary->partCompletionRatio);
        $this->assertSame(0.0, $summary->overallCompletionRatio);
    }

    public function test_summarize_calculates_each_hierarchy_accurately(): void
    {
        $certification = Certification::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'certification_id' => $certification->id,
        ]);

        // Part 1: Chapter 1 (2 Section 全読了), Chapter 2 (1 Section 未読了) -> Part 1 は未完了
        $part1 = Part::factory()->create([
            'certification_id' => $certification->id,
            'status' => ContentStatus::Published,
        ]);
        $chapter1 = Chapter::factory()->create([
            'part_id' => $part1->id,
            'status' => ContentStatus::Published,
        ]);
        $section1 = Section::factory()->create([
            'chapter_id' => $chapter1->id,
            'status' => ContentStatus::Published,
        ]);
        $section2 = Section::factory()->create([
            'chapter_id' => $chapter1->id,
            'status' => ContentStatus::Published,
        ]);

        $chapter2 = Chapter::factory()->create([
            'part_id' => $part1->id,
            'status' => ContentStatus::Published,
        ]);
        Section::factory()->create([
            'chapter_id' => $chapter2->id,
            'status' => ContentStatus::Published,
        ]);

        // Part 2: Chapter 3 (1 Section 全読了) -> Part 2 は完了
        $part2 = Part::factory()->create([
            'certification_id' => $certification->id,
            'status' => ContentStatus::Published,
        ]);
        $chapter3 = Chapter::factory()->create([
            'part_id' => $part2->id,
            'status' => ContentStatus::Published,
        ]);
        $section4 = Section::factory()->create([
            'chapter_id' => $chapter3->id,
            'status' => ContentStatus::Published,
        ]);

        // 読了データ作成 (4 Section 中 3 件読了)
        foreach ([$section1, $section2, $section4] as $sec) {
            SectionProgress::factory()->create([
                'enrollment_id' => $enrollment->id,
                'section_id' => $sec->id,
            ]);
        }

        $summary = app(LearningProgressService::class)->summarize($enrollment);

        $this->assertSame(4, $summary->sectionsTotal);
        $this->assertSame(3, $summary->sectionsCompleted);
        $this->assertSame(0.75, $summary->sectionCompletionRatio);

        $this->assertSame(3, $summary->chaptersTotal);
        $this->assertSame(2, $summary->chaptersCompleted);
        $this->assertSame(round(2 / 3, 4), $summary->chapterCompletionRatio);

        $this->assertSame(2, $summary->partsTotal);
        $this->assertSame(1, $summary->partsCompleted);
        $this->assertSame(0.5, $summary->partCompletionRatio);

        $this->assertSame(0.75, $summary->overallCompletionRatio);
    }

    public function test_summarize_excludes_unpublished_contents(): void
    {
        $certification = Certification::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'certification_id' => $certification->id,
        ]);

        $publishedPart = Part::factory()->create([
            'certification_id' => $certification->id,
            'status' => ContentStatus::Published,
        ]);
        $publishedChapter = Chapter::factory()->create([
            'part_id' => $publishedPart->id,
            'status' => ContentStatus::Published,
        ]);
        $publishedSection = Section::factory()->create([
            'chapter_id' => $publishedChapter->id,
            'status' => ContentStatus::Published,
        ]);

        // 非公開(Draft)のデータ（分母に含まれないはず）
        Section::factory()->create([
            'chapter_id' => $publishedChapter->id,
            'status' => ContentStatus::Draft,
        ]);
        $draftPart = Part::factory()->create([
            'certification_id' => $certification->id,
            'status' => ContentStatus::Draft,
        ]);
        $draftChapter = Chapter::factory()->create([
            'part_id' => $draftPart->id,
            'status' => ContentStatus::Draft,
        ]);
        Section::factory()->create([
            'chapter_id' => $draftChapter->id,
            'status' => ContentStatus::Draft,
        ]);

        SectionProgress::factory()->create([
            'enrollment_id' => $enrollment->id,
            'section_id' => $publishedSection->id,
        ]);

        $summary = app(LearningProgressService::class)->summarize($enrollment);

        // 公開済みの 1 件のみが分母となり、100% 完了になる
        $this->assertSame(1, $summary->sectionsTotal);
        $this->assertSame(1, $summary->sectionsCompleted);
        $this->assertSame(1.0, $summary->sectionCompletionRatio);
        $this->assertSame(1.0, $summary->chapterCompletionRatio);
        $this->assertSame(1.0, $summary->partCompletionRatio);
    }

    public function test_summarize_isolates_enrollments(): void
    {
        $certification = Certification::factory()->create();
        $enrollment1 = Enrollment::factory()->create(['certification_id' => $certification->id]);
        $enrollment2 = Enrollment::factory()->create(['certification_id' => $certification->id]);

        $part = Part::factory()->create([
            'certification_id' => $certification->id,
            'status' => ContentStatus::Published,
        ]);
        $chapter = Chapter::factory()->create([
            'part_id' => $part->id,
            'status' => ContentStatus::Published,
        ]);
        $section = Section::factory()->create([
            'chapter_id' => $chapter->id,
            'status' => ContentStatus::Published,
        ]);

        SectionProgress::factory()->create([
            'enrollment_id' => $enrollment1->id,
            'section_id' => $section->id,
        ]);

        $service = app(LearningProgressService::class);

        $this->assertSame(1.0, $service->summarize($enrollment1)->overallCompletionRatio);
        $this->assertSame(0.0, $service->summarize($enrollment2)->overallCompletionRatio);
    }

    public function test_batch_calculate_ratios_handles_multiple_and_empty_enrollments(): void
    {
        $service = app(LearningProgressService::class);

        // 空コレクション時は空配列
        $this->assertSame([], $service->batchCalculateRatios(collect()));

        $certification = Certification::factory()->create();
        $enrollment1 = Enrollment::factory()->create(['certification_id' => $certification->id]);
        $enrollment2 = Enrollment::factory()->create(['certification_id' => $certification->id]);

        $part = Part::factory()->create([
            'certification_id' => $certification->id,
            'status' => ContentStatus::Published,
        ]);
        $chapter = Chapter::factory()->create([
            'part_id' => $part->id,
            'status' => ContentStatus::Published,
        ]);
        $section1 = Section::factory()->create([
            'chapter_id' => $chapter->id,
            'status' => ContentStatus::Published,
        ]);
        Section::factory()->create([
            'chapter_id' => $chapter->id,
            'status' => ContentStatus::Published,
        ]);

        // enrollment1 は 2 件中 1 件完了 (0.5)、enrollment2 は未着手 (0.0)
        SectionProgress::factory()->create([
            'enrollment_id' => $enrollment1->id,
            'section_id' => $section1->id,
        ]);

        $ratios = $service->batchCalculateRatios(collect([$enrollment1, $enrollment2]));

        $this->assertSame([
            $enrollment1->id => 0.5,
            $enrollment2->id => 0.0,
        ], $ratios);
    }
}
