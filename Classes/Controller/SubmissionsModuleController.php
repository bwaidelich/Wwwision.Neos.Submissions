<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Controller;

use DateTimeImmutable;
use GuzzleHttp\Psr7\Utils;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Psr\Http\Message\StreamInterface;
use Wwwision\Neos\Submissions\Export\SubmissionCsvExporter;
use Wwwision\Neos\Submissions\Factory\FormSubmissionServiceFactory;
use Wwwision\Neos\Submissions\Model\Preset\PresetId;
use Wwwision\Neos\Submissions\Model\Submission\Filter\Pagination;
use Wwwision\Neos\Submissions\Model\Submission\Filter\SubmissionFilter;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionId;

final class SubmissionsModuleController extends AbstractModuleController
{
    protected $defaultViewObjectName = FusionView::class;

    public function __construct(
        private readonly FormSubmissionServiceFactory $formSubmissionServiceFactory,
    ) {}

    public function indexAction(): void
    {
        $presets = $this->formSubmissionServiceFactory->presets();
        if (count($presets) === 1) {
            $this->redirect('submissions', arguments: ['preset' => $presets->first()->id->value]);
        }
        $this->view->assign('presets', $presets);
    }

    public function submissionsAction(string $preset = ''): void
    {
        if ($preset === '') {
            $this->redirect('index');
        }
        $presetId = PresetId::fromString($preset);
        $presetVo = $this->formSubmissionServiceFactory->presets()->get($presetId);
        $service = $this->formSubmissionServiceFactory->create($presetId);
        $filter = $this->submissionFilter();
        $pagination = $this->pagination();
        $this->view->assignMultiple([
            'presets' => $this->formSubmissionServiceFactory->presets(),
            'preset' => $presetVo,
            'forms' => $service->findForms(),
            'filter' => $filter,
            'pagination' => $pagination,
            'submissions' => $service->findSubmissions($filter, $pagination),
        ]);
    }

    public function downloadAction(string $preset): StreamInterface
    {
        $service = $this->formSubmissionServiceFactory->create(PresetId::fromString($preset));
        $filterResult = $service->findSubmissions($this->submissionFilter());
        $csv = (new SubmissionCsvExporter())->export($filterResult->items);

        $filename = sprintf('submissions-%s-%s.csv', $preset, (new DateTimeImmutable())->format('Ymd-His'));
        $this->response->setContentType('text/csv');
        $this->response->setHttpHeader('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
        return Utils::streamFor($csv);
    }

    public function showAction(string $preset, string $id): void
    {
        $presetId = PresetId::fromString($preset);
        $presetVo = $this->formSubmissionServiceFactory->presets()->get($presetId);
        $service = $this->formSubmissionServiceFactory->create(PresetId::fromString($preset));
        $submission = $service->getSubmission(SubmissionId::fromString($id));
        $this->view->assignMultiple([
            'preset' => $presetVo,
            'submission' => $submission,
            'submissionData' => $submission->data->toArray(),
            'filter' => $this->submissionFilter(),
        ]);
    }

    private function submissionFilter(): SubmissionFilter
    {
        if ($this->request->hasArgument('filter')) {
            $filterValues = array_filter($this->request->getArgument('filter'), static fn ($value) => !empty($value));
            return SubmissionFilter::create(...$filterValues);
        }
        return SubmissionFilter::default();
    }

    private function pagination(): Pagination
    {
        return Pagination::forPage((int)($this->request->getHttpRequest()->getQueryParams()['page'] ?? 1));
    }

    protected function getErrorFlashMessage(): false
    {
        return false;
    }
}
