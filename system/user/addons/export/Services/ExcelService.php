<?php

namespace Mithra62\Export\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelService
{
    /**
     * @var Xlsx|null
     */
    protected ?Xlsx $xlsx = null;

    /**
     * @var Spreadsheet|null
     */
    protected ?Spreadsheet $spreadsheet = null;

    /**
     * @var string
     */
    protected string $sheet_title = 'SHEET TITLE';

    /**
     * @var string
     */
    protected string $sheet_name = 'SHEET NAME';

    /**
     * @var array
     */
    protected array $font = [
        'face' => 'Calibri',
        'size' => 10,
    ];

    /**
     * @var array
     */
    protected array $rows;

    /**
     * @var array
     */
    protected array $cols;

    /**
     * @var string
     */
    public string $file_name;

    /**
     * @var bool
     */
    protected bool $bold_first_row = false;

    /**
     * @return $this
     */
    public function reset(): ExcelService
    {
        $this->rows = [];
        $this->cols = [];
        $this->file_name = '';
        $this->sheet_name = 'SHEET NAME';
        $this->sheet_title = 'SHEET TITLE';
        $this->spreadsheet = null;
        $this->xlsx = null;
        return $this;
    }

    /**
     * @param bool $bold
     * @return $this
     */
    public function boldFirstRow(bool $bold = true): ExcelService
    {
        $this->bold_first_row = $bold;
        return $this;
    }

    /**
     * @param array $rows
     * @return $this
     */
    public function setRows(array $rows): ExcelService
    {
        $this->rows = $rows;
        return $this;
    }

    /**
     * @return array
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * @param array $cols
     * @return $this
     */
    public function setCols(array $cols): ExcelService
    {
        $this->cols = $cols;
        return $this;
    }

    /**
     * @return array
     */
    public function getCols(): array
    {
        return $this->cols;
    }

    /**
     * @param string $file_name
     * @return $this
     */
    public function setFileName(string $file_name): ExcelService
    {
        $this->file_name = $file_name . '.xlsx';
        return $this;
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        return $this->file_name;
    }

    /**
     * @return Spreadsheet
     */
    protected function getSpreadsheet(): Spreadsheet
    {
        if (is_null($this->spreadsheet)) {
            $this->spreadsheet = new Spreadsheet();
        }

        return $this->spreadsheet;
    }

    /**
     * @param Spreadsheet $spreadsheet
     * @return Xlsx
     */
    protected function getXlsx(Spreadsheet $spreadsheet): Xlsx
    {
        if (is_null($this->xlsx)) {
            $this->xlsx = new Xlsx($spreadsheet);
        }

        return $this->xlsx;
    }

    /**
     * @return string
     */
    public function getFontFace(): string
    {
        return $this->font['face'];
    }

    /**
     * @param string $fontFace
     * @return $this
     */
    public function setFontFace(string $fontFace): ExcelService
    {
        $this->font['face'] = $fontFace;
        return $this;
    }

    /**
     * @return int
     */
    public function getFontSize(): int
    {
        return $this->font['size'];
    }

    /**
     * @param int $fontSize
     * @return $this
     */
    public function setFontSize(int $fontSize): ExcelService
    {
        $this->font['size'] = $fontSize;
        return $this;
    }

    /**
     * @return Xlsx
     */
    protected function buildXlsx(): Xlsx
    {
        $spreadsheet = $this->getSpreadsheet();
        $spreadsheet->getProperties()->setTitle($this->sheet_title);
        $spreadsheet->getDefaultStyle()->getFont()->setName($this->getFontFace());
        $spreadsheet->getDefaultStyle()->getFont()->setSize($this->getFontSize());
        $xlsx = $this->getXlsx($spreadsheet);

        //	sheet setup
        $spreadsheet->setActiveSheetIndex(0);
        $spreadsheet->getActiveSheet()->setTitle($this->sheet_title);
        $activeSheet = $spreadsheet->getActiveSheet();

        //	sheet header
        $alphas = $this->makeAlphas();

        $j = 0;
        foreach ($this->getCols() as $v) {
            $alpha = $alphas[$j];
            $activeSheet->setCellValue($alpha . '1', $v);
            //$spreadsheet->getActiveSheet()->getColumnDimension($alpha)->setWidth(18);
            $spreadsheet->getActiveSheet()->getColumnDimension($alpha)->setAutoSize(true);
            $j++;
        }

        //	set data rows
        $i = 2;
        foreach ($this->getRows() as $row) {
            $j = 0;
            foreach ($row as $k => $v) {
                $alpha = $alphas[$j] . $i;
                $activeSheet->setCellValue($alpha, $v);
                if ($this->isEmail($v)) {
                    $spreadsheet->getActiveSheet()->getCell($alpha)->getHyperlink()->setUrl("mailto:" . $v);
                }

                $spreadsheet->getActiveSheet()->getStyle($alpha)->getAlignment()->setHorizontal('left');
                $j++;
            }
            $i++;
        }

        if ($this->bold_first_row) {
            $this->forceBoldFirstRow($spreadsheet);
        }

        $spreadsheet->setActiveSheetIndex(0);
        return $xlsx;
    }

    /**
     * @param string|null $email
     * @return bool
     */
    protected function isEmail(?string $email): bool
    {
        $return = false;
        if ($email) {
            $return = filter_var($email, FILTER_VALIDATE_EMAIL);
        }

        return $return;
    }

    /**
     * @param Spreadsheet $spreadsheet
     * @return void
     */
    protected function forceBoldFirstRow(Spreadsheet $spreadsheet): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $highestColumn = $sheet->getHighestColumn();
        $sheet->getStyle('A1:' . $highestColumn . '1')->getFont()->setBold(true);
    }

    /**
     * @return void
     */
    public function download(): void
    {
        $xlsx = $this->buildXlsx();
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename=' . $this->getFileName());
        header('Cache-Control: max-age=0');
        $xlsx->save('php://output');
    }

    /**
     * @param string $path
     * @return void
     */
    public function save(string $path): void
    {
        $xlsx = $this->buildXlsx();
        $xlsx->save($path . DIRECTORY_SEPARATOR . $this->getFileName());
    }

    /**
     * @return array
     */
    protected function makeAlphas(): array
    {
        $alphas = $cells = range('A', 'Z');
        foreach ($alphas as $alpha) {
            foreach ($alphas as $beta) {
                $cells[] = $alpha . $beta;
            }
        }
        return $cells;
    }
}