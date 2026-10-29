<?php

namespace App\Support;

use App\Enums\QuestionType;
use App\Enums\SignupStatus;
use App\Models\Event;
use App\Models\Question;
use App\Models\Signup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes an event's active signups to an Excel file, one row per signup and one column per question.
 */
class SignupExport
{
    public function __construct(private Event $event) {}

    /**
     * Write the spreadsheet to the given path.
     */
    public function writeTo(string $path): void
    {
        $questions = $this->questions();

        $writer = new Writer;
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName($this->sheetName());
        $sheet->setSheetView((new SheetView)->withFreezeRow(2));

        $writer->addRow(Row::fromValuesWithStyle($this->headings($questions), new Style(fontBold: true)));

        $dateStyle = new Style(format: 'yyyy-mm-dd');
        $dateTimeStyle = new Style(format: 'yyyy-mm-dd hh:mm');

        $this->signups()->each(function (Signup $signup) use ($writer, $questions, $dateStyle, $dateTimeStyle): void {
            $writer->addRow(new Row([
                $this->text($signup->name),
                $this->text($signup->email),
                $this->text($signup->status->label()),
                Cell::fromValue($signup->places),
                ...$questions->map(fn (Question $question): Cell => $this->answerCell($signup, $question, $dateStyle)),
                Cell::fromValue($signup->created_at->toDateTimeImmutable(), $dateTimeStyle),
            ]));
        });

        $writer->close();
    }

    /**
     * Get the name the downloaded file should have.
     */
    public function fileName(): string
    {
        $name = Str::slug($this->event->title) ?: 'event';

        return $name.'-'.now()->format('Y-m-d').'.xlsx';
    }

    /**
     * The number of places already has its own column, so the question it comes from is left out.
     *
     * @return Collection<int, Question>
     */
    private function questions(): Collection
    {
        return $this->event->questions()
            ->get()
            ->reject(fn (Question $question): bool => $question->id === $this->event->capacity_question_id)
            ->values();
    }

    /**
     * @param  Collection<int, Question>  $questions
     * @return list<string>
     */
    private function headings(Collection $questions): array
    {
        return [
            __('Name'),
            __('Email'),
            __('Status'),
            __('Places'),
            ...$questions->pluck('label'),
            __('Signed up'),
        ];
    }

    /**
     * Confirmed signups first, then the waiting list in order.
     *
     * @return Collection<int, Signup>
     */
    private function signups(): Collection
    {
        return $this->event->signups()
            ->active()
            ->orderByRaw('case when status = ? then 0 else 1 end', [SignupStatus::Confirmed->value])
            ->orderBy('id')
            ->get();
    }

    private function answerCell(Signup $signup, Question $question, Style $dateStyle): Cell
    {
        $answer = $signup->answerTo($question);

        return match (true) {
            $answer === null || $answer === '' || $answer === [] => Cell::fromValue(null),
            $question->type === QuestionType::Number => Cell::fromValue((int) $answer),
            $question->type === QuestionType::Date => Cell::fromValue(Carbon::parse($answer)->toDateTimeImmutable(), $dateStyle),
            default => $this->text($signup->displayAnswerTo($question)),
        };
    }

    /**
     * Always store participants' text as text, so an answer like "=HYPERLINK(...)" never becomes a formula.
     */
    private function text(string $value): StringCell
    {
        return new StringCell($value);
    }

    /**
     * Excel sheet names are at most 31 characters and cannot contain some characters.
     */
    private function sheetName(): string
    {
        $name = trim(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $this->event->title), " '");

        return Str::limit($name, 31, '') ?: __('Signups');
    }
}
