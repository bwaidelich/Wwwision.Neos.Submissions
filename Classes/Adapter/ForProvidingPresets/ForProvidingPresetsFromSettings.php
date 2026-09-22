<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Adapter\ForProvidingPresets;

use Neos\Flow\ObjectManagement\ObjectManagerInterface;
use Webmozart\Assert\Assert;
use Wwwision\Neos\Submissions\LabelGenerator\FormLabelGenerator\FormLabelGeneratorFactory;
use Wwwision\Neos\Submissions\LabelGenerator\SubmissionLabelGenerator\SubmissionLabelGeneratorFactory;
use Wwwision\Neos\Submissions\Model\Preset\Preset;
use Wwwision\Neos\Submissions\Model\Preset\PresetId;
use Wwwision\Neos\Submissions\Model\Preset\Presets;
use Wwwision\Neos\Submissions\Model\Preset\PresetTitle;
use Wwwision\Neos\Submissions\Ports\ForProvidingPresets;

final readonly class ForProvidingPresetsFromSettings implements ForProvidingPresets
{
    /**
     * @param array<mixed> $settings
     */
    public function __construct(
        private array $settings,
        private ObjectManagerInterface $objectManager,
    ) {}

    public function getPresets(): Presets
    {
        $presets = [];
        foreach ($this->settings as $presetId => $presetSettings) {
            Assert::string($presetId);
            $submissionLabelGeneratorFactory = $this->objectManager->get($presetSettings['submissionLabel']['factory'] ?? SubmissionLabelGeneratorFactory::class);
            Assert::isInstanceOf($submissionLabelGeneratorFactory, SubmissionLabelGeneratorFactory::class);
            $formLabelGeneratorFactory = $this->objectManager->get($presetSettings['formLabel']['factory'] ?? FormLabelGeneratorFactory::class);
            Assert::isInstanceOf($formLabelGeneratorFactory, FormLabelGeneratorFactory::class);
            $presets[] = new Preset(
                PresetId::fromString($presetId),
                PresetTitle::fromString($presetSettings['title'] ?? $presetId),
                $submissionLabelGeneratorFactory->create($presetSettings['submissionLabel']['options'] ?? []),
                $formLabelGeneratorFactory->create($presetSettings['formLabel']['options'] ?? []),
                $presetSettings['options'] ?? [],
            );
        }
        return Presets::fromArray($presets);
    }
}
