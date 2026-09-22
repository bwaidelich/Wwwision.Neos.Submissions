<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Command;

use Neos\Flow\Cli\CommandController;
use Wwwision\Neos\Submissions\Export\SubmissionCsvExporter;
use Wwwision\Neos\Submissions\Factory\FormSubmissionServiceFactory;
use Wwwision\Neos\Submissions\Model\Form\FormId;
use Wwwision\Neos\Submissions\Model\Preset\PresetId;
use Wwwision\Neos\Submissions\Model\Submission\Filter\SubmissionFilter;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionId;

final class SubmissionsCommandController extends CommandController
{

    public function __construct(
        private readonly FormSubmissionServiceFactory $formSubmissionServiceFactory,
    ) {
        parent::__construct();
    }

    /**
     * Setup all submission service instances, creating required database tables etc
     */
    public function setupCommand(): void
    {
        $this->formSubmissionServiceFactory->setupAll();
        $this->outputLine('<success>Success</success>');
    }

    public function testCommand(string $preset): void
    {
        $service = $this->formSubmissionServiceFactory->create(PresetId::fromString($preset));
        $service->handleAddSubmission(AddSubmission::create(
            SubmissionId::generate(),
            FormId::fromString('test'),
            ['title' => 'Some Title', 'foo' => ['bar' => 'baz']],
        ));
        $this->outputLine('<success>Success</success>');
    }

    public function regenerateLabelsCommand(string $preset, string|null $form = null): void
    {
        $submissionService = $this->formSubmissionServiceFactory->create(PresetId::fromString($preset));
        $this->output->progressStart();
        $submissionService->handleRegenerateLabels(RegenerateLabels::create($form), fn () => $this->output->progressAdvance());
        $this->output->progressFinish();
        $this->outputLine();
        $this->outputLine('<success>Success</success>');
    }

    /**
     * Export submissions matching the given filter to a CSV file
     *
     * @param string $preset The preset to export submissions for
     * @param string $outputFile Path of the CSV file to write
     * @param string|null $formId Only export submissions for this form
     * @param string|null $searchTerm Only export submissions matching this search term
     */
    public function exportCommand(string $preset, string $outputFile, ?string $formId = null, ?string $searchTerm = null): void
    {
        $service = $this->formSubmissionServiceFactory->create(PresetId::fromString($preset));
        $filter = SubmissionFilter::create(searchTerm: $searchTerm, formId: $formId);
        $submissions = $service->findSubmissions($filter);
        $csv = (new SubmissionCsvExporter())->export($submissions->items);

        $target = fopen($outputFile, 'wb');
        if ($target === false) {
            $this->outputLine('<error>Failed to open "%s" for writing</error>', [$outputFile]);
            $this->quit(1);
        }
        stream_copy_to_stream($csv, $target);
        fclose($csv);
        fclose($target);
        $this->outputLine('<success>Exported submissions to %s</success>', [$outputFile]);
    }
}
