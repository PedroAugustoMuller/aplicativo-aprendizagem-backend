<?php

declare(strict_types=1);

namespace Tests\Integration\Modules\Identity;

use App\Shared\Domain\Contract\ClassroomRoster;
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
}
