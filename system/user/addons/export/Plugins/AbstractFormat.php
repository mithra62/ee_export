<?php
namespace Mithra62\Export\Plugins;

abstract class AbstractFormat extends AbstractPlugin
{
    abstract public function compile(AbstractSource $source): string;

    public function supportsStreaming(): bool { return false; }

    public function openFile(array $first_row = []): void {}

    public function writeChunk(array $rows): void {}

    public function finalizeFile(): string { return ''; }
}
