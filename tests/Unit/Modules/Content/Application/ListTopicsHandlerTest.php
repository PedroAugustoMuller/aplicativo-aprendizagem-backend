<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Content\Application;

use App\Modules\Content\Application\Query\ListTopics\ListTopicsHandler;
use App\Modules\Content\Application\Query\ListTopics\ListTopicsQuery;
use App\Modules\Content\Application\Query\ListTopics\TopicListItem;
use App\Modules\Content\Application\Query\ListTopics\TopicListReader;
use App\Modules\Content\Domain\Entity\Subject;
use App\Modules\Content\Domain\Exception\ContentAccessDeniedException;
use App\Modules\Content\Domain\Exception\SubjectNotFoundException;
use App\Modules\Content\Domain\Policy\SubjectPolicy;
use App\Modules\Content\Domain\Repository\SubjectRepository;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Modules\Content\Domain\ValueObject\SubjectName;
use App\Shared\Domain\Auth\Actor;
use App\Shared\Domain\Auth\Role;
use App\Shared\Domain\Contract\TeachingAssignments;
use PHPUnit\Framework\TestCase;

final class ListTopicsHandlerTest extends TestCase
{
    private const CHEM = '0190a2b4-0000-7000-8000-00000000c4e1';

    public function test_it_throws_when_the_subject_does_not_exist(): void
    {
        $handler = $this->handler(subjectExists: false, reader: $this->reader([]));

        $this->expectException(SubjectNotFoundException::class);

        $handler->handle(new ListTopicsQuery(new Actor('a', Role::Admin), self::CHEM));
    }

    public function test_it_throws_when_a_student_is_not_enrolled(): void
    {
        $handler = $this->handler(subjectExists: true, reader: $this->reader([]), enrolled: []);

        $this->expectException(ContentAccessDeniedException::class);

        $handler->handle(new ListTopicsQuery(new Actor('s', Role::Student), self::CHEM));
    }

    public function test_an_enrolled_student_receives_the_subjects_topics(): void
    {
        $item = new TopicListItem('0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f', 'Átomos', 'Estrutura atômica.', 2);
        $handler = $this->handler(subjectExists: true, reader: $this->reader([$item], expectedSubjectId: self::CHEM), enrolled: [self::CHEM]);

        $items = $handler->handle(new ListTopicsQuery(new Actor('s', Role::Student), self::CHEM));

        self::assertSame([$item], $items);
    }

    public function test_staff_is_allowed_without_consulting_enrolment(): void
    {
        $item = new TopicListItem('0199c0de-1a2b-7c3d-8e4f-5a6b7c8d9e0f', 'Átomos', 'Estrutura atômica.', 2);
        $assignments = new class implements TeachingAssignments
        {
            public function subjectIdsTaughtBy(string $userId): array
            {
                return [];
            }

            public function subjectIdsEnrolledBy(string $userId): array
            {
                throw new \RuntimeException('Staff must not need enrolment checked.');
            }
        };

        $handler = new ListTopicsHandler(
            $this->reader([$item], expectedSubjectId: self::CHEM),
            $this->subjects(exists: true),
            new SubjectPolicy($assignments),
        );

        $items = $handler->handle(new ListTopicsQuery(new Actor('a', Role::Admin), self::CHEM));

        self::assertSame([$item], $items);
    }

    /** @param list<string>|null $enrolled */
    private function handler(bool $subjectExists, TopicListReader $reader, ?array $enrolled = null): ListTopicsHandler
    {
        $assignments = new class($enrolled ?? []) implements TeachingAssignments
        {
            /** @param list<string> $enrolled */
            public function __construct(private readonly array $enrolled) {}

            public function subjectIdsTaughtBy(string $userId): array
            {
                return [];
            }

            public function subjectIdsEnrolledBy(string $userId): array
            {
                return $this->enrolled;
            }
        };

        return new ListTopicsHandler($reader, $this->subjects($subjectExists), new SubjectPolicy($assignments));
    }

    private function subjects(bool $exists): SubjectRepository
    {
        return new class($exists) implements SubjectRepository
        {
            public function __construct(private readonly bool $exists) {}

            public function findById(SubjectId $id): ?Subject
            {
                return $this->exists ? Subject::create($id, new SubjectName('Química')) : null;
            }

            public function nameTakenByAnother(SubjectName $name, SubjectId $except): bool
            {
                return false;
            }

            public function save(Subject $subject): void {}
        };
    }

    /** @param list<TopicListItem> $items */
    private function reader(array $items, ?string $expectedSubjectId = null): TopicListReader
    {
        return new class($items, $expectedSubjectId) implements TopicListReader
        {
            /** @param list<TopicListItem> $items */
            public function __construct(private readonly array $items, private readonly ?string $expectedSubjectId) {}

            public function forSubject(string $subjectId): array
            {
                if ($this->expectedSubjectId !== null) {
                    TestCase::assertSame($this->expectedSubjectId, $subjectId);
                }

                return $this->items;
            }
        };
    }
}
