<?php
namespace DataReader\Output;

use DataReader\OutputInterface;

class CSV extends Output implements OutputInterface
{
    private string $delimiter;
    private string $enclosure;

    public function __construct(string $delimiter = ',', string $enclosure = '"')
    {
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
    }

    public function items($items): string
    {
        if (empty($items)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');

        // Write header
        $headers = array_keys($items[0]);
        fputcsv($output, $headers, $this->delimiter, $this->enclosure);

        // Write data
        foreach ($items as $item) {
            fputcsv($output, $item, $this->delimiter, $this->enclosure);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}