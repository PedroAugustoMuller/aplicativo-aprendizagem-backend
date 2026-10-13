<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Quiz\Application;

use App\Modules\Quiz\Application\Service\ProgressAccess;
use App\Modules\Quiz\Domain\Exception\ClassroomNotFoundException;
use App\Modules\Quiz\Domain\Exception\QuizAccessDeniedException;
use App\Modules\Quiz\Domain\Exception\StudentNotFoundException;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use App\Shared\Domain\Contract\RosterAssignment;
use App\Shared\Domain\Contract\RosterClassroom;
use App\Shared\Domain\Contract\RosterStudent;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Modules\Quiz\Support\FixedRoster;

final class ProgressAccessTest extends TestCase
{
    private const CLASSROOM = '0192f0a0-0000-7000-8000-00000000d001';

    private const SUBJECT = '0192f0a0-0000-7000-8000-00000000d301';

    private const TEACHER = '0192f0a0-0000-7000-8000-00000000d101';

    private const STUDENT = '0192f0a0-0000-7000-8000-00000000d201';

    private ProgressAccess $access;

    protected function setUp(): void
    {
        $this->access = new ProgressAccess(new FixedRoster([
            new RosterClassroom(self::CLASSROOM, [self::SUBJECT], true, [new RosterAssignment(self::TEACHER, self::SUBJECT)], [new RosterStudent(self::STUDENT, 'Carla', 'carla.dias')]),
        ]));
    }

    public function test_a_student_reads_their_own_answers_in_every_subject(): void
    {
        $scope = $this->access->scopeFor(new Actor(self::STUDENT, Role::Student), null, null);

        self::assertSame([self::STUDENT, null], [$scope->studentId, $scope->subjectIds]);
    }

    public function test_a_teacher_of_the_classroom_reads_a_student_within_its_subject(): void
    {
        $scope = $this->access->scopeFor(new Actor(self::TEACHER, Role::Teacher), self::CLASSROOM, self::STUDENT);

        self::assertSame([self::STUDENT, [self::SUBJECT]], [$scope->studentId, $scope->subjectIds]);
    }

    public function test_an_admin_reads_any_classroom(): void
    {
        self::assertSame(self::CLASSROOM, $this->access->classroom(new Actor('0192f0a0-0000-7000-8000-00000000d999', Role::Admin), self::CLASSROOM)->id);
    }

    public function test_a_teacher_of_another_classroom_is_denied(): void
    {
        $this->expectException(QuizAccessDeniedException::class);

        $this->access->classroom(new Actor('0192f0a0-0000-7000-8000-00000000d102', Role::Teacher), self::CLASSROOM);
    }

    public function test_a_student_is_denied_the_staff_views(): void
    {
        $this->expectException(QuizAccessDeniedException::class);

        $this->access->scopeFor(new Actor(self::STUDENT, Role::Student), self::CLASSROOM, self::STUDENT);
    }

    public function test_an_unknown_classroom_is_not_found(): void
    {
        $this->expectException(ClassroomNotFoundException::class);

        $this->access->classroom(new Actor(self::TEACHER, Role::Teacher), '0192f0a0-0000-7000-8000-00000000dead');
    }

    public function test_a_student_outside_the_classroom_is_not_found(): void
    {
        $this->expectException(StudentNotFoundException::class);

        $this->access->scopeFor(new Actor(self::TEACHER, Role::Teacher), self::CLASSROOM, '0192f0a0-0000-7000-8000-00000000d202');
    }

    public function test_a_classroom_without_a_student_id_is_not_found(): void
    {
        $this->expectException(StudentNotFoundException::class);

        $this->access->scopeFor(new Actor(self::TEACHER, Role::Teacher), self::CLASSROOM, null);
    }

    public function test_a_subject_teacher_reads_only_their_subject_of_the_classroom(): void
    {
        $access = new ProgressAccess(new FixedRoster([
            new RosterClassroom('c', ['mat', 'port'], true, [new RosterAssignment('carlos', 'mat'), new RosterAssignment('ana', null)], [new RosterStudent('bia', 'Bia', 'bia')]),
        ]));
        $carlos = new Actor('carlos', Role::Teacher);

        self::assertSame('c', $access->classroomSubject($carlos, 'c', 'mat')->id);
        self::assertSame(['mat'], $access->scopeFor($carlos, 'c', 'bia')->subjectIds);
        self::assertSame(['mat', 'port'], $access->scopeFor(new Actor('ana', Role::Teacher), 'c', 'bia')->subjectIds);

        $this->expectException(QuizAccessDeniedException::class);
        $access->classroomSubject($carlos, 'c', 'port');
    }

    public function test_a_subject_outside_the_classroom_is_not_found_even_for_the_admin(): void
    {
        $access = new ProgressAccess(new FixedRoster([new RosterClassroom('c', ['mat'], true, [], [])]));

        $this->expectException(ClassroomNotFoundException::class);
        $access->classroomSubject(new Actor('root', Role::Admin), 'c', 'hist');
    }
}
