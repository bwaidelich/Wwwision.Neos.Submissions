<?php

declare(strict_types=1);

namespace Wwwision\Neos\Submissions\Model\Preset;

use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<Preset>
 */
final readonly class Presets implements IteratorAggregate, Countable
{

    /**
     * @var list<Preset>
     */
    private array $presets;

    private function __construct(
        Preset ...$presets
    )
    {
        $this->presets = array_values($presets);
    }

    /**
     * @param array<Preset> $presets
     */
    public static function fromArray(array $presets): self
    {
        return new self(...array_values($presets));
    }

    public function getIterator(): Traversable
    {
        yield from $this->presets;
    }

    public function get(PresetId $id): Preset|null
    {
        foreach ($this->presets as $preset) {
            if ($preset->id->equals($id)) {
                return $preset;
            }
        }
        return null;
    }

    public function first(): Preset|null
    {
        return $this->presets[0] ?? null;
    }

    public function count(): int
    {
        return count($this->presets);
    }
}
