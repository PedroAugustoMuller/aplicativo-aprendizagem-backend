<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Service;

use App\Modules\Content\Domain\Entity\Subject;
use App\Modules\Content\Domain\Exception\ContentAccessDeniedException;
use App\Modules\Content\Domain\Exception\SubjectInactiveException;
use App\Modules\Content\Domain\Exception\SubjectNotFoundException;
use App\Modules\Content\Domain\Policy\SubjectPolicy;
use App\Modules\Content\Domain\Repository\SubjectRepository;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use App\Shared\Domain\Auth\Actor;

/**
 * The one place topic and question handlers ask "may this actor touch this
 * subject's content?". Order matters: 404, then 403, then 409 for an inactive
 * subject — a prober learns nothing about a subject it may not author.
 */
final readonly class AuthoringGate
{
    public function __construct(private SubjectRepository $subjects, private SubjectPolicy $policy) {}

    /** Reading the question bank: the subject exists and the actor authors it. */
    public function assertCanRead(Actor $actor, SubjectId $subjectId): void
    {
        $this->authoredSubject($actor, $subjectId);
    }

    /** Changing content: as above, and the subject is still active. */
    public function assertCanWrite(Actor $actor, SubjectId $subjectId): void
    {
        if (! $this->authoredSubject($actor, $subjectId)->isActive()) {
            throw new SubjectInactiveException;
        }
    }

    private function authoredSubject(Actor $actor, SubjectId $subjectId): Subject
    {
        $subject = $this->subjects->findById($subjectId) ?? throw new SubjectNotFoundException;

        if (! $this->policy->canAuthor($actor, $subjectId->value())) {
            throw new ContentAccessDeniedException;
        }

        return $subject;
    }
}
