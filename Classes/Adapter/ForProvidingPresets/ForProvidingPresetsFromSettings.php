<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Adapter\ForProvidingPresets;

use InvalidArgumentException;
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
     * @param array<mixed>|null $settings
     */
    public function __construct(
        private array|null $settings,
        private ObjectManagerInterface $objectManager,
    ) {}

    public function getPresets(): Presets
    {
        $presets = [];
        foreach ($this->settings ?? [] as $presetId => $presetSettings) {
            // a preset can be disabled by setting it to `~` (null) in Settings.yaml
            if ($presetSettings === null) {
                continue;
            }
            if (!is_string($presetId)) {
                throw new InvalidArgumentException(sprintf('Preset ids must be strings, got %s', get_debug_type($presetId)), 1789995009);
            }
            if (!is_array($presetSettings)) {
                throw new InvalidArgumentException(sprintf('Settings of preset "%s" must be an array, got %s', $presetId, get_debug_type($presetSettings)), 1789995010);
            }
            $submissionLabelSettings = self::arrayEntry($presetSettings, 'submissionLabel', $presetId);
            $formLabelSettings = self::arrayEntry($presetSettings, 'formLabel', $presetId);

            $submissionLabelGeneratorFactory = $this->objectManager->get(self::stringEntry($submissionLabelSettings, 'factory', $presetId) ?? SubmissionLabelGeneratorFactory::class);
            Assert::isInstanceOf($submissionLabelGeneratorFactory, SubmissionLabelGeneratorFactory::class);
            $formLabelGeneratorFactory = $this->objectManager->get(self::stringEntry($formLabelSettings, 'factory', $presetId) ?? FormLabelGeneratorFactory::class);
            Assert::isInstanceOf($formLabelGeneratorFactory, FormLabelGeneratorFactory::class);
            $presets[] = new Preset(
                PresetId::fromString($presetId),
                PresetTitle::fromString(self::stringEntry($presetSettings, 'title', $presetId) ?? $presetId),
                $submissionLabelGeneratorFactory->create(self::arrayEntry($submissionLabelSettings, 'options', $presetId)),
                $formLabelGeneratorFactory->create(self::arrayEntry($formLabelSettings, 'options', $presetId)),
                self::arrayEntry($presetSettings, 'options', $presetId),
            );
        }
        return Presets::fromArray($presets);
    }

    /**
     * @param array<mixed> $settings
     * @return array<mixed>
     */
    private static function arrayEntry(array $settings, string $key, string $presetId): array
    {
        $value = $settings[$key] ?? [];
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('Setting "%s" of preset "%s" must be an array, got %s', $key, $presetId, get_debug_type($value)), 1789995011);
        }
        return $value;
    }

    /**
     * @param array<mixed> $settings
     */
    private static function stringEntry(array $settings, string $key, string $presetId): string|null
    {
        $value = $settings[$key] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new InvalidArgumentException(sprintf('Setting "%s" of preset "%s" must be a string, got %s', $key, $presetId, get_debug_type($value)), 1789995012);
        }
        return $value;
    }
}
