<?php

namespace Tests\Feature;

use App\Models\CareCheckResult;
use App\Models\CareToolkitQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CareCheckApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_store_care_check(): void
    {
        $this->postJson('/api/v1/care-checks', [
            'check_type' => 'stress',
            'answers' => [],
        ])->assertUnauthorized();
    }

    public function test_stress_reverse_items_and_low_tier(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $questions = $this->seedStressQuestions();

        $answers = [];
        foreach ($questions as $index => $question) {
            $answers[] = [
                'question_id' => $question->id,
                'value' => in_array($index, [3, 4], true) ? 4 : 0,
            ];
        }

        $response = $this->postJson('/api/v1/care-checks', [
            'check_type' => 'stress',
            'answers' => $answers,
        ])->assertCreated();

        $this->assertSame(0, $response->json('data.raw_score'));
        $this->assertSame('low', $response->json('data.display_tier'));
        $this->assertSame('low', $response->json('data.band'));

        $this->getJson('/api/v1/care-checks/latest?type=stress')
            ->assertOk()
            ->assertJsonPath('data.display_tier', 'low');
    }

    public function test_anxiety_high_tier_and_admin_summary(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $questions = $this->seedAnxietyQuestions();

        $answers = array_map(fn ($q) => [
            'question_id' => $q->id,
            'value' => 3,
        ], $questions);

        $this->postJson('/api/v1/care-checks', [
            'check_type' => 'anxiety',
            'answers' => $answers,
        ])->assertCreated()
            ->assertJsonPath('data.raw_score', 21)
            ->assertJsonPath('data.display_tier', 'high')
            ->assertJsonPath('data.band', 'severe');

        $admin = User::factory()->create(['role' => 'super_admin']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/care-checks')
            ->assertOk()
            ->assertJsonPath('data.data.0.check_type', 'anxiety');

        $this->getJson('/api/v1/admin/care-checks/summary?days=7')
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.by_display_tier.high', 1);

        $id = $user->careCheckResults()->value('id');
        $this->getJson("/api/v1/admin/care-checks/{$id}")
            ->assertOk()
            ->assertJsonPath('data.tier_label', 'Moderate to Severe')
            ->assertJsonPath('data.answers.0.label', 'Nearly every day');
    }

    public function test_incomplete_answers_are_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $questions = $this->seedAnxietyQuestions();

        $this->postJson('/api/v1/care-checks', [
            'check_type' => 'anxiety',
            'answers' => [
                ['question_id' => $questions[0]->id, 'value' => 1],
            ],
        ])->assertUnprocessable();
    }

    public function test_admin_summary_supports_ninety_days_and_previous_period(): void
    {
        $user = User::factory()->create();
        $this->makeResult($user, 'stress', 'low', now()->subDay());
        $high = $this->makeResult($user, 'anxiety', 'high', now()->subDays(40));
        $this->makeResult($user, 'sleep', 'low', now()->subDays(41));

        $admin = User::factory()->create(['role' => 'super_admin']);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/care-checks/summary?days=30')
            ->assertOk()
            ->assertJsonPath('data.days', 30)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.unique_users', 1)
            ->assertJsonPath('data.by_display_tier.low', 1)
            ->assertJsonPath('data.previous.total', 2)
            ->assertJsonPath('data.previous.unique_users', 1)
            ->assertJsonPath('data.previous.by_display_tier.high', 1)
            ->assertJsonPath('data.period_label', 'previous 30 days')
            ->assertJsonPath('data.tier_counts.low', 2)
            ->assertJsonPath('data.tier_counts.high', 1);

        $ninety = $this->getJson('/api/v1/admin/care-checks/summary?days=90')
            ->assertOk()
            ->assertJsonPath('data.days', 90)
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.previous.total', 0);

        $this->assertCount(90, $ninety->json('data.daily'));
        $this->assertNotEmpty($ninety->json('data.updated_at'));

        $this->getJson('/api/v1/admin/care-checks/summary?days=14')
            ->assertOk()
            ->assertJsonPath('data.days', 30);

        $this->getJson('/api/v1/admin/care-checks?display_tier=high')
            ->assertOk()
            ->assertJsonPath('data.data.0.id', $high->id)
            ->assertJsonPath('data.data.0.display_tier', 'high')
            ->assertJsonPath('data.total', 1);
    }

    private function makeResult(User $user, string $type, string $tier, $createdAt): CareCheckResult
    {
        $result = CareCheckResult::query()->create([
            'user_id' => $user->id,
            'check_type' => $type,
            'raw_score' => $tier === 'high' ? 18 : 4,
            'max_score' => 21,
            'percent' => $tier === 'high' ? 86 : 19,
            'band' => $tier === 'high' ? 'high' : 'low',
            'display_tier' => $tier,
            'answers' => [],
        ]);
        $result->created_at = $createdAt;
        $result->save();

        return $result->fresh();
    }

    /**
     * @return array<int, CareToolkitQuestion>
     */
    private function seedStressQuestions(): array
    {
        $rows = [];
        for ($i = 1; $i <= 5; $i++) {
            $rows[] = CareToolkitQuestion::query()->create([
                'toolkit_type' => 'stress',
                'answer_type' => 'likert5',
                'question_en' => "Stress {$i}",
                'question_tl' => "Stress {$i}",
                'sort_order' => $i,
                'is_active' => true,
                'is_reverse' => in_array($i, [4, 5], true),
            ]);
        }

        return $rows;
    }

    /**
     * @return array<int, CareToolkitQuestion>
     */
    private function seedAnxietyQuestions(): array
    {
        $rows = [];
        for ($i = 1; $i <= 7; $i++) {
            $rows[] = CareToolkitQuestion::query()->create([
                'toolkit_type' => 'anxiety',
                'answer_type' => 'likert4',
                'question_en' => "Anxiety {$i}",
                'question_tl' => "Anxiety {$i}",
                'sort_order' => $i,
                'is_active' => true,
                'is_reverse' => false,
            ]);
        }

        return $rows;
    }
}
