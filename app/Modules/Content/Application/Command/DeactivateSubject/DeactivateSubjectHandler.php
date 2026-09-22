<?php

declare(strict_types=1);

namespace App\Modules\Content\Application\Command\DeactivateSubject;

use App\Modules\Content\Application\DTO\SubjectView;
use App\Modules\Content\Domain\Exception\ContentAccessDeniedException;
use App\Modules\Content\Domain\Exception\SubjectNotFoundException;
use App\Modules\Content\Domain\Repository\SubjectRepository;
use App\Modules\Content\Domain\ValueObject\SubjectId;
use DateTimeImmutable;

final readonly class DeactivateSubjectHandler
{
    public function __construct(private SubjectRepository $subjects) {}

    public function handle(DeactivateSubjectCommand $command): SubjectView
    {
        if (! $command->actor->isAdmin()) {
            throw new ContentAccessDeniedException;
        }

        $subject = $this->subjects->findById(new SubjectId($command->id)) ?? throw new SubjectNotFoundException;

        $subject->deactivate(new DateTimeImmutable);
        $this->subjects->save($subject);

        return SubjectView::of($subject);
    }
}
