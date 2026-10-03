<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Modules\Identity\Domain\ValueObject\UserId;
use App\Modules\Identity\Infrastructure\Persistence\UserModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Shared user/token/subject/classroom fixtures for feature tests, used from here on. */
trait ActsAsUsers
{
    protected function makeUser(string $role, string $name = 'User', bool $mustChange = false): UserModel
    {
        $id = UserId::random()->value();
        $short = substr(str_replace('-', '', $id), -10);

        return UserModel::query()->create([
            'id' => $id,
            'name' => $name,
            'role' => $role,
            'email' => $role === 'student' ? null : $short.'@escola.br',
            'username' => $role === 'student' ? 'u'.$short : null,
            'password' => Hash::make('password'),
            'must_change_password' => $mustChange,
        ]);
    }

    protected function tokenFor(UserModel $user): string
    {
        return $user->createToken('api', ['*'], now()->addDay())->plainTextToken;
    }

    protected function makeSubject(string $name = 'Química'): string
    {
        $id = (string) Str::uuid7();
        DB::table('subjects')->insert(['id' => $id, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    /**
     * @param  list<UserModel>  $teachers
     * @param  list<UserModel>  $students
     */
    protected function makeClassroom(string $subjectId, array $teachers = [], array $students = [], string $name = 'Química 1'): string
    {
        $id = (string) Str::uuid7();
        DB::table('classrooms')->insert(['id' => $id, 'name' => $name, 'subject_id' => $subjectId, 'created_at' => now(), 'updated_at' => now()]);
        foreach ($teachers as $t) {
            DB::table('classroom_teachers')->insert(['classroom_id' => $id, 'user_id' => $t->getKey()]);
        }
        foreach ($students as $s) {
            DB::table('classroom_students')->insert(['classroom_id' => $id, 'user_id' => $s->getKey()]);
        }

        return $id;
    }

    protected function makeTopic(string $subjectId, string $name = 'Átomos', int $position = 0): string
    {
        $id = (string) Str::uuid7();
        DB::table('topics')->insert([
            'id' => $id, 'subject_id' => $subjectId, 'name' => $name, 'description' => '',
            'position' => $position, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    protected function makeQuestion(string $topicId): string
    {
        $id = (string) Str::uuid7();
        DB::table('questions')->insert([
            'id' => $id, 'topic_id' => $topicId, 'type' => 'true_false', 'statement' => 'O sódio é um metal.',
            'explanation' => null, 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ([['Verdadeiro', true], ['Falso', false]] as $position => [$text, $correct]) {
            DB::table('question_options')->insert([
                'id' => (string) Str::uuid7(), 'question_id' => $id, 'text' => $text, 'is_correct' => $correct,
                'position' => $position, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $id;
    }

    /**
     * @param  list<array{string, bool}>  $options  text and whether it is the correct one
     * @return array{id: string, options: list<string>} the question id and its option ids in position order
     */
    protected function makeQuestionWithOptions(string $topicId, array $options, string $type = 'multiple_choice', ?string $explanation = 'Explicação.'): array
    {
        $id = (string) Str::uuid7();
        DB::table('questions')->insert([
            'id' => $id, 'topic_id' => $topicId, 'type' => $type, 'statement' => 'Pergunta '.$id,
            'explanation' => $explanation, 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $optionIds = [];

        foreach ($options as $position => [$text, $correct]) {
            $optionIds[] = $optionId = (string) Str::uuid7();
            DB::table('question_options')->insert([
                'id' => $optionId, 'question_id' => $id, 'text' => $text, 'is_correct' => $correct,
                'position' => $position, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return ['id' => $id, 'options' => $optionIds];
    }

    /**
     * An open attempt holding one snapshot of $question (its first option is the snapshot's correct one).
     *
     * @param  array{id: string, options: list<string>}  $question
     * @return array{attemptId: string, attemptQuestionId: string}
     */
    protected function makeAttempt(string $studentId, string $topicId, string $subjectId, array $question): array
    {
        $attemptId = (string) Str::uuid7();
        $attemptQuestionId = (string) Str::uuid7();
        DB::table('quiz_attempts')->insert([
            'id' => $attemptId, 'student_id' => $studentId, 'topic_id' => $topicId, 'subject_id' => $subjectId,
            'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('quiz_attempt_questions')->insert([
            'id' => $attemptQuestionId, 'attempt_id' => $attemptId, 'position' => 0, 'question_id' => $question['id'],
            'type' => 'multiple_choice', 'statement' => 'Pergunta?', 'explanation' => null,
            'options' => json_encode(array_map(static fn (string $id): array => ['id' => $id, 'text' => $id], $question['options']), JSON_THROW_ON_ERROR),
            'correct_option_id' => $question['options'][0], 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['attemptId' => $attemptId, 'attemptQuestionId' => $attemptQuestionId];
    }
}
