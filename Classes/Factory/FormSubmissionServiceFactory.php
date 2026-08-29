<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Factory;

use InvalidArgumentException;
use Psr\Clock\ClockInterface;
use Wwwision\Neos\Submissions\FormSubmissionService;
use Wwwision\Neos\Submissions\Model\Preset\PresetId;
use Wwwision\Neos\Submissions\Model\Preset\Presets;
use Wwwision\Neos\Submissions\Ports\ForProvidingPresets;

final readonly class FormSubmissionServiceFactory
{
    public function __construct(
        private ForProvidingPresets $forProvidingPresets,
        private ForStoringSubmissionsFactory $forStoringSubmissionsFactory,
        private ClockInterface $clock,
    ) {}

    public function create(PresetId $presetId): FormSubmissionService
    {
        $preset = $this->forProvidingPresets->getPresets()->get($presetId);
        if ($preset === null) {
            throw new InvalidArgumentException(sprintf('Preset "%s" does not exist', $presetId->value), 1787993314);
        }
        $forStoringSubmissions = $this->forStoringSubmissionsFactory->create($preset);
        return new FormSubmissionService(
            $preset,
            $forStoringSubmissions,
            $this->clock,
        );
    }

    public function presets(): Presets
    {
        return $this->forProvidingPresets->getPresets();
    }

    public function setupAll(): void
    {
        foreach ($this->forProvidingPresets->getPresets() as $preset) {
            $forStoringSubmissions = $this->forStoringSubmissionsFactory->create($preset);
            $forStoringSubmissions->setup();
        }
    }

}
