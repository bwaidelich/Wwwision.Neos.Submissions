<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Controller;

use DateTimeImmutable;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\Utils;
use InvalidArgumentException;
use Neos\Error\Messages\Message;
use Neos\Flow\I18n\Translator;
use Neos\Fusion\View\FusionView;
use Neos\Neos\Controller\Module\AbstractModuleController;
use Psr\Http\Message\StreamInterface;
use Wwwision\Neos\Submissions\Command\ArchiveSubmission;
use Wwwision\Neos\Submissions\Command\ArchiveUnprotectedSubmissions;
use Wwwision\Neos\Submissions\Command\ProtectSubmission;
use Wwwision\Neos\Submissions\Command\UnprotectSubmission;
use Wwwision\Neos\Submissions\Export\SubmissionCsvExporter;
use Wwwision\Neos\Submissions\Factory\FormSubmissionServiceFactory;
use Wwwision\Neos\Submissions\FormSubmissionService;
use Wwwision\Neos\Submissions\Model\Form\FormId;
use Wwwision\Neos\Submissions\Model\Preset\Preset;
use Wwwision\Neos\Submissions\Model\Preset\PresetId;
use Wwwision\Neos\Submissions\Model\Submission\Exception\SubmissionIsProtected;
use Wwwision\Neos\Submissions\Model\Submission\Filter\Pagination;
use Wwwision\Neos\Submissions\Model\Submission\Filter\SearchTerm;
use Wwwision\Neos\Submissions\Model\Submission\Filter\SubmissionFilter;
use Wwwision\Neos\Submissions\Model\Submission\Submission;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionId;

final class SubmissionsModuleController extends AbstractModuleController
{
    protected $defaultViewObjectName = FusionView::class;

    public function __construct(
        private readonly FormSubmissionServiceFactory $formSubmissionServiceFactory,
        private readonly Translator $translator,
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
        $submission = $this->submission($this->formSubmissionServiceFactory->create($presetVo->id), $id);
        $this->view->assignMultiple([
            'preset' => $presetVo,
            'submission' => $submission,
            'submissionData' => $submission->data->toArray(),
            'filter' => $this->submissionFilter(),
        ]);
    }

    /**
     * Archives a single submission (labeled "delete" in the module)
     */
    public function archiveAction(string $preset, string $id): void
    {
        $this->assertPostRequest();
        $presetVo = $this->preset($preset);
        $service = $this->formSubmissionServiceFactory->create($presetVo->id);
        $submission = $this->submission($service, $id);
        try {
            $service->handleArchiveSubmission(ArchiveSubmission::create($submission->id));
        } catch (SubmissionIsProtected) {
            $this->addFlashMessage('LLL:flashMessage.submissionIsProtected', '', Message::SEVERITY_ERROR);
            $this->redirect('show', arguments: ['preset' => $presetVo->id->value, 'id' => $submission->id->value, 'filter' => $this->submissionFilter()->getArray()]);
        }
        $this->addFlashMessage('LLL:flashMessage.submissionArchived');
        $this->redirect('submissions', arguments: ['preset' => $presetVo->id->value, 'filter' => $this->submissionFilter()->getArray()]);
    }

    /**
     * Archives all unprotected submissions matching the current filter (labeled "delete all unprotected" in the module)
     */
    public function archiveUnprotectedAction(string $preset): void
    {
        $this->assertPostRequest();
        $presetVo = $this->preset($preset);
        $service = $this->formSubmissionServiceFactory->create($presetVo->id);
        $filter = $this->submissionFilter();
        $numberOfArchivedSubmissions = $service->handleArchiveUnprotectedSubmissions(ArchiveUnprotectedSubmissions::create($filter));
        $this->addFlashMessage($this->translate('flashMessage.unprotectedSubmissionsArchived', [$numberOfArchivedSubmissions], $numberOfArchivedSubmissions));
        $this->redirect('submissions', arguments: ['preset' => $presetVo->id->value, 'filter' => $filter->getArray()]);
    }

    /**
     * @param string $returnTo name of the action to redirect to afterwards ("show" or "submissions")
     * @param int $page page of the submissions list to redirect to (only relevant if $returnTo is "submissions")
     */
    public function protectAction(string $preset, string $id, string $returnTo = 'submissions', int $page = 1): void
    {
        $this->assertPostRequest();
        $presetVo = $this->preset($preset);
        $service = $this->formSubmissionServiceFactory->create($presetVo->id);
        $submission = $this->submission($service, $id);
        $service->handleProtectSubmission(ProtectSubmission::create($submission->id));
        $this->addFlashMessage('LLL:flashMessage.submissionProtected');
        $this->redirectAfterProtectionChange($presetVo, $submission, $returnTo, $page);
    }

    /**
     * @param string $returnTo name of the action to redirect to afterwards ("show" or "submissions")
     * @param int $page page of the submissions list to redirect to (only relevant if $returnTo is "submissions")
     */
    public function unprotectAction(string $preset, string $id, string $returnTo = 'submissions', int $page = 1): void
    {
        $this->assertPostRequest();
        $presetVo = $this->preset($preset);
        $service = $this->formSubmissionServiceFactory->create($presetVo->id);
        $submission = $this->submission($service, $id);
        $service->handleUnprotectSubmission(UnprotectSubmission::create($submission->id));
        $this->addFlashMessage('LLL:flashMessage.submissionUnprotected');
        $this->redirectAfterProtectionChange($presetVo, $submission, $returnTo, $page);
    }

    private function redirectAfterProtectionChange(Preset $preset, Submission $submission, string $returnTo, int $page): never
    {
        $filterArray = $this->submissionFilter()->getArray();
        if ($returnTo === 'show') {
            $this->redirect('show', arguments: ['preset' => $preset->id->value, 'id' => $submission->id->value, 'filter' => $filterArray]);
        }
        $uri = new Uri($this->uriBuilder->reset()->setCreateAbsoluteUri(true)->uriFor('submissions', ['preset' => $preset->id->value, 'filter' => $filterArray]));
        // the page is read from the plain "page" query parameter (see pagination()) that is not part of the module arguments
        if ($page > 1) {
            $uri = Uri::withQueryValue($uri, 'page', (string) $page);
        }
        $this->redirectToUri($uri);
    }

    /**
     * State changing actions must only be invoked via POST in order to be covered by Flow's CSRF protection
     */
    private function assertPostRequest(): void
    {
        if ($this->request->getHttpRequest()->getMethod() !== 'POST') {
            $this->throwStatus(405, null, 'This action only supports POST requests');
        }
    }

    private function submission(FormSubmissionService $service, string $submissionId): Submission
    {
        try {
            return $service->getSubmission(SubmissionId::fromString($submissionId));
        } catch (InvalidArgumentException) {
            $this->throwStatus(404, null, sprintf('Submission "%s" does not exist', $submissionId));
        }
    }

    /**
     * @param array<int|string, mixed> $arguments
     */
    private function translate(string $id, array $arguments = [], int|null $quantity = null): string
    {
        return $this->translator->translateById($id, $arguments, $quantity, null, 'Modules', 'Wwwision.Neos.Submissions') ?? $id;
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
