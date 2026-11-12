<?php

namespace App\Console\Commands;

use App\Domain\Content\Data\ComposedSlide;
use App\Domain\Content\Data\SlideInstance;
use App\Domain\Content\Services\DocumentComposer;
use App\Models\Club;
use App\Models\Proposal;
use App\Models\ProposalGroup;
use App\Support\Content\ContentPresenter;
use App\Support\Text;
use Illuminate\Console\Command;

/**
 * Read-only check of the composition of the document of a group of variants (DM-02; D-403…D-405),
 * until the "Documento" step shows it (DM-05): document type, ordered slides with their pages,
 * mandatory and "summary only" slides, warnings. Nothing is written.
 *
 *   php artisan documents:compose 205
 */
final class DocumentsComposeCommand extends Command
{
    protected $signature = 'documents:compose
        {number : Number of a proposal of the group, e.g. 205}';

    protected $description = 'Shows the slides of the document of the group of a proposal (read only)';

    public function handle(DocumentComposer $composer): int
    {
        $number = (string) $this->argument('number');
        $proposal = ctype_digit($number) ? Proposal::query()->where('number', (int) $number)->first() : null;
        $group = $proposal?->proposal_group_id !== null ? ProposalGroup::query()->find($proposal->proposal_group_id) : null;

        if ($proposal === null || $group === null) {
            $this->error(Text::get('content.compose_command.not_found', ['number' => $number]));

            return self::FAILURE;
        }

        $composition = $composer->forGroup($group);
        $clubs = Club::query()->whereIn('id', $composition->clubIds)->pluck('short_name', 'id');

        $this->line(Text::get('content.compose_command.header', [
            'number' => (string) $proposal->number,
            'variants' => (string) $group->proposals()->count(),
            'season' => (string) $group->season?->code,
            'document' => $composition->documentType?->label() ?? '—',
        ]));
        $this->line(Text::get('content.compose_command.clubs', [
            'clubs' => $clubs->isEmpty() ? '—' : $clubs->implode(', '),
        ]));

        if ($composition->slides === []) {
            $this->line(Text::get('content.compose_command.empty'));
        } else {
            $yes = Text::get('content.compose_command.yes');
            $no = Text::get('content.compose_command.no');
            $columns = __('content.compose_command.columns');

            $this->table(
                is_array($columns) ? array_values(array_filter($columns, 'is_string')) : [],
                array_map(fn (ComposedSlide $composed): array => [
                    $composed->section()->name,
                    $composed->custom !== null ? '—' : (string) $composed->slide->sort_order,
                    $composed->title(),
                    $composed->custom !== null ? Text::get('content.compose_command.custom') : ContentPresenter::kindLabel($composed->slide),
                    implode(', ', array_map(fn (SlideInstance $instance): string => match (true) {
                        $instance->variantLetter !== null => Text::get('content.compose_command.variant', ['letter' => $instance->variantLetter]),
                        $instance->clubId !== null => (string) ($clubs[$instance->clubId] ?? $instance->clubId),
                        default => '1',
                    }, $composed->instances)),
                    $composed->mandatory ? $yes : $no,
                    $composed->inSummary ? $yes : $no,
                    $composed->included ? $yes : $no,
                ], $composition->slides),
            );
        }

        $this->line(Text::get('content.compose_command.total', [
            'slides' => (string) count($composition->slides),
            'pages' => (string) $composition->pageCount(),
        ]));
        $this->line(Text::get('content.compose_command.mode', [
            'mode' => $composition->selection->mode->label(),
            'included' => (string) count($composition->includedSlides()),
            'slides' => (string) count($composition->slides),
        ]));

        if ($composition->warnings !== []) {
            $this->line(Text::get('content.compose_command.warnings'));

            foreach ($composition->warnings as $warning) {
                $this->line('- '.$warning);
            }
        }

        return self::SUCCESS;
    }
}
