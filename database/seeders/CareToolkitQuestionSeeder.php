<?php

namespace Database\Seeders;

use App\Models\CareToolkitQuestion;
use Illuminate\Database\Seeder;

class CareToolkitQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // Stress — PSS-style (5 options)
            ['stress', 'likert5', 1, 'In the last month, how often have you been upset because of something that happened unexpectedly?', 'Sa nakaraang buwan, gaano kadalas kang nababagabag dahil sa biglaang nangyari?'],
            ['stress', 'likert5', 2, 'In the last month, how often have you felt that you were unable to control the important things in your life?', 'Sa nakaraang buwan, gaano kadalas mong naramdaman na hindi mo kontrolado ang mahahalagang bagay sa buhay?'],
            ['stress', 'likert5', 3, 'In the last month, how often have you felt nervous and stressed?', 'Sa nakaraang buwan, gaano kadalas kang nakaramdam ng kaba at stress?'],
            ['stress', 'likert5', 4, 'In the last month, how often have you felt confident about your ability to handle your personal problems?', 'Sa nakaraang buwan, gaano kadalas kang may tiwala na kaya mong harapin ang personal na problema?'],
            ['stress', 'likert5', 5, 'In the last month, how often have you felt that things were going your way?', 'Sa nakaraang buwan, gaano kadalas mong naramdaman na pabor sa iyo ang mga nangyayari?'],

            // Anxiety — GAD-7 style (4 options)
            ['anxiety', 'likert4', 1, 'Feeling nervous, anxious, or on edge', 'Pakiramdam ng kaba, anxiety, o pagkabalisa'],
            ['anxiety', 'likert4', 2, 'Not being able to stop or control worrying', 'Hindi mapigil o makontrol ang pag-aalala'],
            ['anxiety', 'likert4', 3, 'Worrying too much about different things', 'Sobrang pag-aalala sa iba\'t ibang bagay'],
            ['anxiety', 'likert4', 4, 'Trouble relaxing', 'Hirap magpahinga o mag-relax'],
            ['anxiety', 'likert4', 5, 'Being so restless that it is hard to sit still', 'Sobrang restless na hirap umupo nang tahimik'],
            ['anxiety', 'likert4', 6, 'Becoming easily annoyed or irritable', 'Madaling mainis o magalit'],
            ['anxiety', 'likert4', 7, 'Feeling afraid as if something awful might happen', 'Takot na parang may masamang mangyayari'],

            // Sleep
            ['sleep', 'time', 1, 'When have you usually gone to bed at night?', 'Anong oras ka karaniwang natutulog sa gabi?'],
            ['sleep', 'time', 2, 'When have you usually gotten up in the morning?', 'Anong oras ka karaniwang gumigising sa umaga?'],
            ['sleep', 'likert5', 3, 'How often do you have trouble falling asleep?', 'Gaano kadalas kang nahihirapang makatulog?'],
            ['sleep', 'likert5', 4, 'How often do you wake up during the night?', 'Gaano kadalas kang gumigising sa gabi?'],
            ['sleep', 'likert5', 5, 'How often do you feel rested after sleep?', 'Gaano kadalas kang gumigising na refreshed?'],
            ['sleep', 'likert5', 6, 'How often does sleepiness interfere with your day?', 'Gaano kadalas nakakaapekto ang antok sa araw mo?'],
        ];

        foreach ($rows as [$type, $answerType, $order, $en, $tl]) {
            CareToolkitQuestion::query()->updateOrCreate(
                [
                    'toolkit_type' => $type,
                    'sort_order'   => $order,
                    'question_en'  => $en,
                ],
                [
                    'answer_type' => $answerType,
                    'question_tl' => $tl,
                    'options_en'  => null,
                    'options_tl'  => null,
                    'is_active'   => true,
                ],
            );
        }
    }
}
