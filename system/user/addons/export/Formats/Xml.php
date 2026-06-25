<?php

namespace Mithra62\Export\Formats;

use Mithra62\Export\Plugins\AbstractFormat;
use Mithra62\Export\Plugins\AbstractSource;
use Mithra62\Export\Services\XmlService;

class Xml extends AbstractFormat
{
    protected array $rules = [
        'root_name' => 'required',
        'branch_name' => 'required',
    ];

    protected string $stream_path = '';
    protected ?XmlService $xml = null;

    public function getCpLabel(): ?string
    {
        return 'XML';
    }

    public function getCpFields(array $context = []): array
    {
        return [
            [
                'name' => 'root_name', 'type' => 'text', 'label' => 'export_format_root_name',
                'desc' => 'export_format_root_name_desc', 'default' => 'export',
            ],
            [
                'name' => 'branch_name', 'type' => 'text', 'label' => 'export_format_branch_name',
                'desc' => 'export_format_branch_name_desc', 'default' => 'row',
            ],
        ];
    }

    public function compile(AbstractSource $source): string
    {
        $this->openFile($source->getExportData()[0] ?? []);
        $this->writeChunk($source->getExportData());
        return $this->finalizeFile();
    }

    public function supportsStreaming(): bool
    {
        return true;
    }

    public function openFile(array $first_row = []): void
    {
        $this->stream_path = $this->getCacheDirPath() . $this->getCacheFilename() . '.xml';
        $this->xml = new XmlService();
        $this->xml->setRootName($this->getOption('root_name'));
        $this->xml->initiateFile($this->stream_path);
    }

    public function writeChunk(array $rows): void
    {
        $branch = $this->getOption('branch_name');
        foreach ($rows as $item) {
            $this->xml->startBranch($branch);
            foreach ($item as $key => $value) {
                $this->xml->addXmlNodes($key, $value);
            }
            $this->xml->endBranch();
        }
    }

    public function finalizeFile(): string
    {
        $this->xml->closeFile();
        return $this->stream_path;
    }
}
