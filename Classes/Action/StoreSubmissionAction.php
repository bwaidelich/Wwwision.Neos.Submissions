<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Action;

use Neos\Flow\Mvc\ActionResponse;
use Neos\Fusion\Form\Runtime\Action\AbstractAction;
use Webmozart\Assert\Assert;
use Wwwision\Neos\Submissions\Command\AddSubmission;
use Wwwision\Neos\Submissions\Factory\FormSubmissionServiceFactory;
use Wwwision\Neos\Submissions\Model\Preset\PresetId;
use Wwwision\Neos\Submissions\Model\Submission\SubmissionId;

final class StoreSubmissionAction extends AbstractAction
{

    public function __construct(
        private readonly FormSubmissionServiceFactory $submissionServiceFactory,
    ) {
    }

    public function perform(): ?ActionResponse
    {
        Assert::keyExists($this->options, 'preset');
        Assert::string($this->options['preset']);

        $presetId = PresetId::fromString($this->options['preset']);
        $submissionService = $this->submissionServiceFactory->create($presetId);

        Assert::keyExists($this->options, 'formId');
        Assert::string($this->options['formId']);
        $command = AddSubmission::create(
            submissionId: SubmissionId::generate(),
            formId: $this->options['formId'],
            data: $this->options['data'],
        );
        $submissionService->handleAddSubmission($command);
        return null;
    }
}
