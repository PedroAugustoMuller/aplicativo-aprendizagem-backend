<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Identity;

use App\Shared\Domain\Contract\ClassroomRoster;
use App\Shared\Domain\Contract\RosterClassroom;
use App\Shared\Domain\Contract\RosterStudent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Support\ActsAsUsers;
use Tests\TestCase;

final class EloquentClassroomRosterTest extends TestCase
{
    use ActsAsUsers;
    use RefreshDatabase;

    public function test_it_returns_subject_teachers_and_students_sorted_by_name(): void
    {
        $subject = $this->makeSubject();
        $teacher = $this->makeUser('teacher', 'Bruno');
        $zoe = $this->makeUser('student', 'Zoe');
        $ana = $this->makeUser('student', 'Ana');
        $id = $this->makeClassroom($subject, teachers: [$teacher], students: [$zoe, $ana]);

        $classroom = $this->app->make(ClassroomRoster::class)->find($id);

        self::assertNotNull($classroom);
        self::assertSame($subject, $classroom->subjectId);
        self::assertTrue($classroom->active);
        self::assertSame([$teacher->getKey()], $classroom->teacherIds);
        self::assertSame(['Ana', 'Zoe'], array_map(static fn (RosterStudent $s): string => $s->name, $classroom->students));
        self::assertSame($ana->getAttribute('username'), $classroom->students[0]->username);
    }

    public function test_a_deactivated_classroom_is_found_as_inactive_and_an_unknown_one_is_null(): void
    {
        $id = $this->makeClassroom($this->makeSubject());
        DB::table('classrooms')->where('id', $id)->update(['deactivated_at' => now()]);
        $roster = $this->app->make(ClassroomRoster::class);

        self::assertFalse($roster->find($id)?->active);
        self::assertNull($roster->find('0192f0a0-0000-7000-8000-00000000dead'));
    }

    public function test_for_subject_returns_every_classroom_of_the_subject_with_teachers_and_students(): void
    {
        $chemistry = $this->makeSubject();
        $biology = $this->makeSubject('Biologia');
        $teacher = $this->makeUser('teacher', 'Bruno');
        $carla = $this->makeUser('student', 'Carla');
        $ana = $this->makeUser('student', 'Ana');
        $first = $this->makeClassroom($chemistry, teachers: [$teacher], students: [$carla, $ana], name: '9º A');
        $second = $this->makeClassroom($chemistry, students: [$carla], name: '9º B');
        $this->makeClassroom($biology, teachers: [$teacher], students: [$ana], name: '9º A Bio');
        DB::table('classrooms')->where('id', $second)->update(['deactivated_at' => now()]);
        $roster = $this->app->make(ClassroomRoster::class);

        $classrooms = $roster->forSubject($chemistry);

        self::assertSame([$first, $second], array_map(static fn (RosterClassroom $c): string => $c->id, $classrooms));
        self::assertSame([true, false], array_map(static fn (RosterClassroom $c): bool => $c->active, $classrooms));
        self::assertSame([$chemistry, $chemistry], array_map(static fn (RosterClassroom $c): string => $c->subjectId, $classrooms));
        self::assertSame([$teacher->getKey()], $classrooms[0]->teacherIds);
        self::assertSame([], $classrooms[1]->teacherIds);
        self::assertSame(['Ana', 'Carla'], array_map(static fn (RosterStudent $s): string => $s->name, $classrooms[0]->students));
        self::assertSame(['Carla'], array_map(static fn (RosterStudent $s): string => $s->name, $classrooms[1]->students));
        self::assertSame([], $roster->forSubject('0192f0a0-0000-7000-8000-00000000dead'));
    }

    public function test_for_subject_runs_a_fixed_number_of_queries(): void
    {
        $subject = $this->makeSubject();
        $roster = $this->app->make(ClassroomRoster::class);
        $count = static function () use ($roster, $subject): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $roster->forSubject($subject);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $this->makeClassroom($subject, teachers: [$this->makeUser('teacher', 'T')], students: [$this->makeUser('student', 'S')]);
        $one = $count();

        for ($i = 0; $i < 3; $i++) {
            $this->makeClassroom($subject, teachers: [$this->makeUser('teacher', "T$i")], students: [$this->makeUser('student', "S$i")], name: "Turma $i");
        }

        self::assertSame($one, $count());
    }
}
