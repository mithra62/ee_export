<?php

namespace Mithra62\Export\Services;

use XMLWriter;

class XmlService extends XMLWriter
{
    /**
     * @var string
     */
    protected string $root_name = '';

    /**
     * @var string
     */
    protected string $xml_version = '1.0';

    /**
     * @var string
     */
    protected string $char_set = 'UTF-8';

    /**
     * @var string
     */
    protected string $indent_string = '    ';

    /**
     * @var string
     */
    protected string $xslt_file_path = '';

    /**
     * @param string $root_name
     * @return $this
     */
    public function setRootName(string $root_name): XmlService
    {
        $this->root_name = $root_name;
        return $this;
    }

    /**
     * @param $version
     * @return $this
     */
    public function setXmlVersion($version): XmlService
    {
        $this->xml_version = $version;
        return $this;
    }

    /**
     * @param $char_set
     * @return $this
     */
    public function setCharSet($char_set): XmlService
    {
        $this->char_set = $char_set;
        return $this;
    }

    /**
     * @param $indentString
     * @return $this
     */
    public function setIndentStr($indentString): XmlService
    {
        $this->indent_string = $indentString;
        return $this;
    }

    /**
     * @param $xslt_file_path
     * @return $this
     */
    public function setXsltFilepath($xslt_file_path): XmlService
    {
        $this->xslt_file_path = $xslt_file_path;
        return $this;
    }

    /**
     * Open an XML file for streaming; rows are written directly to disk.
     * @param string $path
     * @return $this
     */
    public function initiateFile(string $path): XmlService
    {
        $this->openUri($path);
        $this->applySettings();
        return $this;
    }

    /**
     * Close a file-backed XML stream opened with initiateFile().
     */
    public function closeFile(): void
    {
        $this->endElement();
        $this->endDocument();
        $this->flush();
    }

    protected function applySettings(): void
    {
        if ($this->indent_string) {
            $this->setIndent(true);
            $this->setIndentString($this->indent_string);
        }

        $this->startDocument($this->xml_version, $this->char_set);

        if ($this->xslt_file_path) {
            $this->writePi('xml-stylesheet', 'type="text/xsl" href="' . $this->xslt_file_path . '"');
        }

        $this->startElement($this->root_name);
    }

    /**
     * @return $this
     */
    public function initiate(): XmlService
    {
        $this->openMemory();
        $this->applySettings();
        return $this;
    }

    /**
     * @param string $name
     * @param array $attributes
     * @return $this
     */
    public function startBranch(string $name, array $attributes = []): XmlService
    {
        $this->startElement($name);
        $this->addAttributes($attributes);
        return $this;
    }

    /**
     * @return $this
     */
    public function endBranch(): XmlService
    {
        $this->endElement();
        return $this;
    }

    /**
     * @param $name
     * @param $value
     * @param $attributes
     * @param $cdata
     * @return $this
     */
    public function addNode($name, $value, $attributes = [], $cdata = false): XmlService
    {
        $this->startElement($name);
        $this->addAttributes($attributes);
        if ($cdata) {
            $this->writeCdata($value ?? '');
        } else {
            $this->text($value ?? '');
        }
        $this->endElement();

        return $this;
    }

    /**
     * @return string
     */
    public function getXml(): string
    {
        $this->endElement();
        $this->endDocument();
        return $this->outputMemory();
    }

    /**
     * @param array $attributes
     * @return $this
     */
    protected function addAttributes(array $attributes): XmlService
    {
        if (count($attributes) > 0) {
            // We have attributes, let's set them
            foreach ($attributes as $key => $value) {
                $this->writeAttribute($key, $value);
            }
        }

        return $this;
    }

    /**
     * @param $key
     * @param $value
     * @return $this
     */
    public function addXmlNodes($key, $value): XmlService
    {
        $numeric_key = is_int($key) || ctype_digit((string)$key);

        if (!is_array($value)) {
            $wrap = !is_numeric($value);
            $attrs = $numeric_key ? ['index' => (string)$key] : [];
            $name = $numeric_key ? 'item' : $key;
            $this->addNode($name, $value, $attrs, $wrap);
            return $this;
        }

        // Open the branch element, using <item index="N"> for numeric keys
        if ($numeric_key) {
            $this->startElement('item');
            $this->writeAttribute('index', (string)$key);
        } else {
            $this->startBranch($key);
        }

        foreach ($value as $_key => $sub) {
            $this->addXmlNodes($_key, $sub);
        }

        if ($numeric_key) {
            $this->endElement();
        } else {
            $this->endBranch();
        }

        return $this;
    }
}