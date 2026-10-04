<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Shared\Infrastructure\Persistence\EloquentAttribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\Feature\Modules\Identity\StudentRosterTest;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

/**
 * The executable form of spec §4's permission matrix (docs/superpowers/specs/
 * 2026-09-17-accounts-roles-classrooms-design.md). Every route added by Tasks
 * 3-11 gets one row here. A new route without a row here is a gap the next
 * reviewer should catch — see the backend CLAUDE.md's Authorization section.
 */
final class AuthorizationMatrixTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    /** @return iterable<string, array{string, string, array<string, int>}> */
    public static function matrix(): iterable
    {
        //                                                                guest student other  teacher admin
        yield 'list subjects' => ['GET', '/subjects', self::row(401, 200, 200, 200, 200)];
        yield 'create topic' => ['POST', '/subjects/{subject}/topics', self::row(401, 403, 403, 201, 201)];
        yield 'update topic' => ['PATCH', '/topics/{topic}', self::row(401, 403, 403, 200, 200)];
        yield 'deactivate topic' => ['POST', '/topics/{topic}/deactivate', self::row(401, 403, 403, 200, 200)];
        yield 'reactivate topic' => ['POST', '/topics/{topic}/reactivate', self::row(401, 403, 403, 200, 200)];
        yield 'reorder topics' => ['PUT', '/subjects/{subject}/topics/order', self::row(401, 403, 403, 200, 200)];
        yield 'question bank' => ['GET', '/topics/{topic}/questions', self::row(401, 403, 403, 200, 200)];
        yield 'create question' => ['POST', '/topics/{topic}/questions', self::row(401, 403, 403, 201, 201)];
        yield 'update question' => ['PUT', '/questions/{question}', self::row(401, 403, 403, 200, 200)];
        yield 'deactivate question' => ['POST', '/questions/{question}/deactivate', self::row(401, 403, 403, 200, 200)];
        yield 'reactivate question' => ['POST', '/questions/{question}/reactivate', self::row(401, 403, 403, 200, 200)];
        yield 'create subject' => ['POST', '/subjects', self::row(401, 403, 403, 403, 201)];
        yield 'rename subject' => ['PATCH', '/subjects/{subject}', self::row(401, 403, 403, 403, 200)];
        yield 'deactivate subject' => ['POST', '/subjects/{subject}/deactivate', self::row(401, 403, 403, 403, 200)];
        yield 'list topics' => ['GET', '/subjects/{subject}/topics', self::row(401, 200, 200, 200, 200)];
        yield 'list teachers' => ['GET', '/teachers', self::row(401, 403, 403, 403, 200)];
        yield 'create teacher' => ['POST', '/teachers', self::row(401, 403, 403, 403, 201)];
        yield 'reset teacher' => ['POST', '/teachers/{teacher}/reset-password', self::row(401, 403, 403, 403, 200)];
        yield 'deactivate teacher' => ['POST', '/teachers/{teacher}/deactivate', self::row(401, 403, 403, 403, 200)];
        yield 'reactivate teacher' => ['POST', '/teachers/{teacher}/reactivate', self::row(401, 403, 403, 403, 200)];
        yield 'list classrooms' => ['GET', '/classrooms', self::row(401, 200, 200, 200, 200)];
        yield 'create classroom' => ['POST', '/classrooms', self::row(401, 403, 403, 403, 201)];
        yield 'update classroom' => ['PATCH', '/classrooms/{classroom}', self::row(401, 403, 403, 403, 200)];
        yield 'assign teachers' => ['PUT', '/classrooms/{classroom}/teachers', self::row(401, 403, 403, 403, 200)];
        yield 'deactivate classroom' => ['POST', '/classrooms/{classroom}/deactivate', self::row(401, 403, 403, 403, 200)];
        yield 'list students' => ['GET', '/classrooms/{classroom}/students', self::row(401, 403, 403, 200, 200)];
        yield 'create students' => ['POST', '/classrooms/{classroom}/students', self::row(401, 403, 403, 201, 201)];
        yield 'enrol student' => ['PUT', '/classrooms/{classroom}/students/{student}', self::row(401, 403, 403, 200, 200)];
        yield 'unenrol student' => ['DELETE', '/classrooms/{classroom}/students/{student}', self::row(401, 403, 403, 200, 200)];
        yield 'reset student' => ['POST', '/students/{student}/reset-password', self::row(401, 403, 403, 200, 200)];
        yield 'deactivate student' => ['POST', '/students/{student}/deactivate', self::row(401, 403, 403, 200, 200)];
        yield 'reactivate student' => ['POST', '/students/{student}/reactivate', self::row(401, 403, 403, 200, 200)];
        yield 'credential slips' => ['GET', '/classrooms/{classroom}/credentials', self::row(401, 403, 403, 200, 200)];
        yield 'search students' => ['GET', '/students?search=Al', self::row(401, 403, 200, 200, 200)];
        // The student's start answers 200: the world already holds their open attempt on that topic.
        yield 'start quiz' => ['POST', '/topics/{topic}/quiz-attempts', self::row(401, 200, 403, 403, 403)];
        yield 'read quiz' => ['GET', '/quiz-attempts/{attempt}', self::row(401, 200, 403, 403, 403)];
        yield 'answer quiz' => ['POST', '/quiz-attempts/{attempt}/answers', self::row(401, 200, 403, 403, 403)];
        yield 'subject quiz progress' => ['GET', '/subjects/{subject}/quiz-progress', self::row(401, 200, 403, 403, 403)];
        yield 'topic quiz history' => ['GET', '/topics/{topic}/quiz-history', self::row(401, 200, 403, 403, 403)];
        yield 'topic wrong questions' => ['GET', '/topics/{topic}/wrong-questions', self::row(401, 200, 403, 403, 403)];
    }

    /** @return array<string, int> */
    private static function row(int $guest, int $student, int $other, int $teacher, int $admin): array
    {
        return ['guest' => $guest, 'student' => $student, 'other_teacher' => $other, 'teacher' => $teacher, 'admin' => $admin];
    }

    /** @param  array<string, int>  $expectedByActor */
    #[DataProvider('matrix')]
    public function test_every_route_enforces_the_permission_matrix(string $method, string $uriTemplate, array $expectedByActor): void
    {
        $world = $this->buildWorld();
        $uri = '/api/v1'.str_replace(
            ['{classroom}', '{student}', '{teacher}', '{subject}', '{topic}', '{question}', '{attempt}'],
            [$world['classroomId'], $world['studentId'], $world['teacherId'], $world['subjectId'], $world['topicId'], $world['questionId'], $world['attemptId']],
            $uriTemplate,
        );
        $body = $this->bodyFor($uriTemplate, $world);
        $case = $this->dataName();

        foreach ($expectedByActor as $actor => $expectedStatus) {
            DB::beginTransaction();

            $token = $world['tokens'][$actor];
            $headers = $token === null ? [] : ['Authorization' => 'Bearer '.$token];

            $response = $this->json($method, $uri, $body, $headers);

            self::assertSame($expectedStatus, $response->getStatusCode(), "$case as $actor");

            DB::rollBack();
            $this->app['auth']->forgetGuards();
        }
    }

    /**
     * The spec rule "a student enrolled in several classrooms is manageable by
     * the teachers of any of them" (§4) is already exercised end-to-end by
     * StudentRosterTest::test_a_student_moved_between_classrooms_can_have_their_password_reset_by_either_teacher
     * — a student created in teacher A's classroom, enrolled into teacher B's,
     * then has their password reset by both. Not duplicated here; this only
     * pins that the covering test still exists.
     */
    public function test_a_student_in_two_classrooms_is_manageable_by_either_teacher(): void
    {
        // Reflection, not method_exists(): a literal method_exists() call is
        // resolved at analysis time by PHPStan and flagged as always true,
        // which would defeat the point of this guard.
        $studentRosterTest = new ReflectionClass(StudentRosterTest::class);

        self::assertTrue(
            $studentRosterTest->hasMethod('test_a_student_moved_between_classrooms_can_have_their_password_reset_by_either_teacher'),
            'The cross-classroom management rule (spec §4) is covered by StudentRosterTest; that test was renamed or removed.',
        );
    }

    /**
     * @return array{
     *     subjectId: string,
     *     topicId: string,
     *     questionId: string,
     *     classroomId: string,
     *     attemptId: string,
     *     attemptQuestionId: string,
     *     optionId: string,
     *     teacherId: string,
     *     studentId: string,
     *     tokens: array<string, string|null>,
     * }
     */
    private function buildWorld(): array
    {
        $subjectId = $this->makeSubject('Química');
        $otherSubjectId = $this->makeSubject('Biologia');

        $admin = $this->makeUser('admin', 'Admin');
        $teacher = $this->makeUser('teacher', 'Teacher');
        $otherTeacher = $this->makeUser('teacher', 'Other Teacher');
        $student = $this->makeUser('student', 'Student');

        $classroomId = $this->makeClassroom($subjectId, teachers: [$teacher], students: [$student], name: 'Química 1');
        $this->makeClassroom($otherSubjectId, teachers: [$otherTeacher], name: 'Biologia 1');
        $topicId = $this->makeTopic($subjectId);
        $quizQuestion = $this->makeQuestionWithOptions($topicId, [['Na', true], ['S', false]]);
        $attempt = $this->makeAttempt(EloquentAttribute::string($student->getKey(), 'users.id'), $topicId, $subjectId, $quizQuestion);

        return [
            'subjectId' => $subjectId,
            'topicId' => $topicId,
            'questionId' => $this->makeQuestion($topicId),
            'classroomId' => $classroomId,
            'attemptId' => $attempt['attemptId'],
            'attemptQuestionId' => $attempt['attemptQuestionId'],
            'optionId' => $quizQuestion['options'][0],
            'teacherId' => EloquentAttribute::string($teacher->getKey(), 'users.id'),
            'studentId' => EloquentAttribute::string($student->getKey(), 'users.id'),
            'tokens' => [
                'guest' => null,
                'student' => $this->tokenFor($student),
                'other_teacher' => $this->tokenFor($otherTeacher),
                'teacher' => $this->tokenFor($teacher),
                'admin' => $this->tokenFor($admin),
            ],
        ];
    }

    /**
     * @param  array{subjectId: string, topicId: string, questionId: string, classroomId: string, attemptId: string, attemptQuestionId: string, optionId: string, teacherId: string, studentId: string, tokens: array<string, string|null>}  $world
     * @return array<string, mixed>
     */
    private function bodyFor(string $uriTemplate, array $world): array
    {
        return match ($uriTemplate) {
            '/subjects' => ['id' => (string) Str::uuid(), 'name' => 'Física '.uniqid()],
            '/subjects/{subject}' => ['name' => 'Física '.uniqid()],
            '/teachers' => ['id' => (string) Str::uuid(), 'name' => 'Novo Professor', 'email' => uniqid().'@escola.br'],
            '/classrooms' => ['id' => (string) Str::uuid(), 'name' => 'Turma '.uniqid(), 'subject_id' => $world['subjectId']],
            '/classrooms/{classroom}' => ['name' => 'Química 1', 'subject_id' => $world['subjectId']],
            '/classrooms/{classroom}/teachers' => ['teacher_ids' => [$world['teacherId']]],
            '/classrooms/{classroom}/students' => ['students' => [['id' => (string) Str::uuid(), 'name' => 'Nova Aluna']]],
            '/subjects/{subject}/topics' => ['id' => (string) Str::uuid(), 'name' => 'Conteúdo '.uniqid(), 'description' => ''],
            '/topics/{topic}' => ['name' => 'Conteúdo '.uniqid()],
            '/subjects/{subject}/topics/order' => ['ids' => [$world['topicId']]],
            '/topics/{topic}/questions' => ['id' => (string) Str::uuid(), 'type' => 'true_false', 'statement' => 'Pergunta '.uniqid(), 'correct' => true],
            '/questions/{question}' => ['version' => 1, 'statement' => 'Editada', 'correct' => false],
            '/topics/{topic}/quiz-attempts' => ['id' => (string) Str::uuid()],
            '/quiz-attempts/{attempt}/answers' => [
                'answer_id' => (string) Str::uuid(), 'question_id' => $world['attemptQuestionId'],
                'option_id' => $world['optionId'], 'answered_at' => now()->toIso8601String(),
            ],
            default => [],
        };
    }
}
