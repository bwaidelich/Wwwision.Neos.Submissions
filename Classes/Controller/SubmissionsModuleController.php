<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Controller;

use DateTimeImmutable;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Psr\Http\Message\StreamInterface;
use Wwwision\Neos\Submissions\Export\SubmissionCsvExporter;
use Wwwision\Neos\Submissions\Factory\FormSubmissionServiceFactory;
use Wwwision\Neos\Submissions\Model\Form\FormId;
use Wwwision\Neos\Submissions\Model\Preset\Preset;
use Wwwision\Neos\Submissions\Model\Preset\PresetId;
use Wwwision\Neos\Submissions\Model\Submission\Filter\Pagination;
use Wwwision\Neos\Submissions\Model\Submission\Filter\SearchTerm;
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
        $onlyPreset = $presets->first();
        if ($onlyPreset !== null && count($presets) === 1) {
            $this->redirect('submissions', arguments: ['preset' => $onlyPreset->id->value]);
        }
        $this->view->assign('presets', $presets);
    }

    public function submissionsAction(string $preset = ''): void
    {
        if ($preset === '') {
            $this->redirect('index');
        }
        $presetVo = $this->preset($preset);
        $service = $this->formSubmissionServiceFactory->create($presetVo->id);
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
        $presetVo = $this->preset($preset);
        $service = $this->formSubmissionServiceFactory->create($presetVo->id);
        $filterResult = $service->findSubmissions($this->submissionFilter());
        $csv = (new SubmissionCsvExporter())->export($filterResult->items);

        $filename = sprintf('submissions-%s-%s.csv', $presetVo->id->value, (new DateTimeImmutable())->format('Ymd-His'));
        $this->response->setContentType('text/csv');
        $this->response->setHttpHeader('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
        return Utils::streamFor($csv);
    }

    public function showAction(string $preset, string $id): void
    {
        $presetVo = $this->preset($preset);
        $service = $this->formSubmissionServiceFactory->create($presetVo->id);
        try {
            $submission = $service->getSubmission(SubmissionId::fromString($id));
        } catch (InvalidArgumentException) {
            $this->throwStatus(404, null, sprintf('Submission "%s" does not exist', $id));
        }
        $this->view->assignMultiple([
            'preset' => $presetVo,
            'submission' => $submission,
            'submissionData' => $submission->data->toArray(),
            'filter' => $this->submissionFilter(),
        ]);
    }

    private function preset(string $presetId): Preset
    {
        try {
            $preset = $this->formSubmissionServiceFactory->presets()->get(PresetId::fromString($presetId));
        } catch (InvalidArgumentException) {
            $preset = null;
        }
        if ($preset === null) {
            $this->throwStatus(404, null, sprintf('Preset "%s" does not exist', $presetId));
        }
        return $preset;
    }

    /**
     * Builds the filter from the (untrusted) "filter" request argument.
     * Only the known keys are considered; values that are not usable are ignored rather than causing an error.
     */
    private function submissionFilter(): SubmissionFilter
    {
        $filterValues = $this->request->hasArgument('filter') ? $this->request->getArgument('filter') : [];
        if (!is_array($filterValues)) {
            return SubmissionFilter::default();
        }
        return SubmissionFilter::create(
            searchTerm: self::stringFilterValue($filterValues['searchTerm'] ?? null, SearchTerm::MAX_LENGTH),
            formId: self::stringFilterValue($filterValues['formId'] ?? null, FormId::MAX_LENGTH),
        );
    }

    private static function stringFilterValue(mixed $value, int $maxLength): string|null
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return mb_substr($value, 0, $maxLength);
    }

    private function pagination(): Pagination
    {
        $page = $this->request->getHttpRequest()->getQueryParams()['page'] ?? 1;
        $page = is_scalar($page) ? (int) $page : 1;
        return Pagination::forPage(max(1, $page));
    }

    protected function getErrorFlashMessage(): false // @phpstan-ignore method.childReturnType
    {
        return false;
    }
}
