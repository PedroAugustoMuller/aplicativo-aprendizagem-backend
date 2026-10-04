<?php

declare(strict_types=1);

namespace App\Modules\Quiz\Domain\Error;

use App\Shared\Domain\Error\ErrorCode;

enum QuizErrorCode: string implements ErrorCode
{
    case AttemptNotFound = 'quiz.attempt_not_found';
    case TopicNotFound = 'quiz.topic_not_found';
    case TopicUnavailable = 'quiz.topic.unavailable';
    case TopicHasNoQuestions = 'quiz.topic.no_questions';
    case QuestionAlreadyAnswered = 'quiz.question.already_answered';
    case InvalidAnswerOption = 'quiz.answer.invalid_option';
    case ClassroomNotFound = 'quiz.classroom_not_found';
    case StudentNotFound = 'quiz.student_not_found';

    public function code(): string
    {
        return $this->value;
    }
}
