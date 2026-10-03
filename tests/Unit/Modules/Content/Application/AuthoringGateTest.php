<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Content\Application;

use App\Modules\Content\Application\Service\AuthoringGate;
use App\Modules\Content\Domain\Entity\Subject;
use App\Modules\Content\Domain\Exception\ContentAccessDeniedException;
use App\Modules\Content\Domain\Exception\SubjectInactiveException;
use App\Modules\Content\Domain\Exception\SubjectNotFoundException;
use App\Modules\Content\Domain\Policy\SubjectPolicy;
use App\Modules\Content\Domain\Repository\SubjectRepository;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\SubjectName;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use App\Shared\Domain\Contract\TeachingAssignments;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AuthoringGateTest extends TestCase
{
    private const CHEM = '0190a2b4-0000-7000-8000-00000000c4e1';

    public function test_an_unknown_subject_is_not_found_before_permissions_are_checked(): void
    {
        $this->expectException(SubjectNotFoundException::class);

        $this->gate(subjectExists: false)->assertCanRead(new Actor('s', Role::Student), new SubjectId(self::CHEM));
    }

    public function test_a_teacher_who_does_not_teach_the_subject_is_denied(): void
    {
        $this->expectException(ContentAccessDeniedException::class);

        $this->gate(taught: [])->assertCanRead(new Actor('t', Role::Teacher), new SubjectId(self::CHEM));
    }

    public function test_a_student_is_denied(): void
    {
        $this->expectException(ContentAccessDeniedException::class);

        $this->gate()->assertCanRead(new Actor('s', Role::Student), new SubjectId(self::CHEM));
    }

    public function test_the_teacher_of_the_subject_and_the_admin_may_write(): void
    {
        $this->gate(taught: [self::CHEM])->assertCanWrite(new Actor('t', Role::Teacher), new SubjectId(self::CHEM));
        $this->gate()->assertCanWrite(new Actor('a', Role::Admin), new SubjectId(self::CHEM));

        $this->addToAssertionCount(2);
    }

    public function test_an_inactive_subject_can_be_read_but_not_written(): void
    {
        $subject = Subject::create(new SubjectId(self::CHEM), new SubjectName('Química'));
        $subject->deactivate(new DateTimeImmutable);
        $gate = $this->gate(subject: $subject);

        $gate->assertCanRead(new Actor('a', Role::Admin), new SubjectId(self::CHEM));

        $this->expectException(SubjectInactiveException::class);
        $gate->assertCanWrite(new Actor('a', Role::Admin), new SubjectId(self::CHEM));
    }

    /** @param list<string> $taught */
    private function gate(bool $subjectExists = true, ?Subject $subject = null, array $taught = []): AuthoringGate
    {
        $stored = $subjectExists
            ? ($subject ?? Subject::create(new SubjectId(self::CHEM), new SubjectName('Química')))
            : null;

        $subjects = new class($stored) implements SubjectRepository
        {
            public function __construct(private readonly ?Subject $subject) {}

            public function findById(SubjectId $id): ?Subject
            {
                return $this->subject;
            }

            public function nameTakenByAnother(SubjectName $name, SubjectId $except): bool
            {
                return false;
            }

            public function save(Subject $subject): void {}
        };

        $assignments = new class($taught) implements TeachingAssignments
        {
            /** @param list<string> $taught */
            public function __construct(private readonly array $taught) {}

            public function subjectIdsTaughtBy(string $userId): array
            {
                return $this->taught;
            }

            public function subjectIdsEnrolledBy(string $userId): array
            {
                return [];
            }
        };

        return new AuthoringGate($subjects, new SubjectPolicy($assignments));
    }
}
