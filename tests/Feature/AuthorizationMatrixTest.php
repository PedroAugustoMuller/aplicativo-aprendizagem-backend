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
            ['{classroom}', '{student}', '{teacher}', '{subject}'],
            [$world['classroomId'], $world['studentId'], $world['teacherId'], $world['subjectId']],
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
     *     classroomId: string,
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

        return [
            'subjectId' => $subjectId,
            'classroomId' => $classroomId,
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
     * @param  array{subjectId: string, classroomId: string, teacherId: string, studentId: string, tokens: array<string, string|null>}  $world
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
            default => [],
        };
    }
}
