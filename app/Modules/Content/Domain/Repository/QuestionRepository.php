<?php

declare(strict_types=1);

namespace App\Modules\Content\Domain\Repository;

use App\Modules\Content\Domain\Entity\Question;
use App\Modules\Content\Domain\ValueObject\QuestionId;

interface QuestionRepository
{
    public function findById(QuestionId $id): ?Question;

    /**
     * A new question is written whole. For a restored one only what changed:
     * content is written only while the stored version is still the one it was
     * loaded at — otherwise QuestionEditedElsewhereException and nothing is written.
     */
    public function save(Question $question): void;
}
