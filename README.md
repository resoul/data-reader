## Advanced Usage

### Data Validation and Filtering

```php
use DataReader\Config\BaseConfig;

class ValidatedUserConfig extends BaseConfig
{
    public function __construct()
    {
        // Multiple validators
        $this->addValidator(function($item) {
            return isset($item['email']) && filter_var($item['email'], FILTER_VALIDATE_EMAIL);
        });
        
        $this->addValidator(function($item) {
            return isset($item['age']) && $item['age'] >= 18;
        });
        
        // Field mapping
        $this->setFieldMapping([
            0 => 'name',
            1 => 'email', 
            2 => 'age',
            3 => 'country'
        ]);
    }
    
    public function configureItem($item): ?array
    {
        $mapped = $this->mapFields($item);
        
        // Skip invalid items
        if (!$this->validateItem($mapped)) {
            return null;
        }
        
        return [
            'name' => ucwords(strtolower($mapped['name'])),
            'email' => strtolower($mapped['email']),
            'age' => (int)$mapped['age'],
            'country' => strtoupper($mapped['country']),
            'is_adult' => $mapped['age'] >= 18
        ];
    }
    
    public function configureFirstItem($item)
    {
        return false; // Skip headers
    }
}
```

### Chaining Multiple Transformations

```php
class MultiStepConfig implements ConfigInterface
{
    private array $processors = [];
    
    public function addProcessor(callable $processor): self
    {
        $this->processors[] = $processor;
        return $this;
    }
    
    public function configureItem($item): array
    {
        $result = $item;
        
        foreach ($this->processors as $processor) {
            $result = $processor($result);
            if ($result === null) {
                break; // Skip this item
            }
        }
        
        return $result;
    }
    
    public function configureFirstItem($item)
    {
        return false;
    }
}

// Usage
$config = new MultiStepConfig();
$config->addProcessor(function($item) {
    // Step 1: Clean data
    return array_map('trim', $item);
})
->addProcessor(function($item) {
    // Step 2: Validate
    return filter_var($item[1], FILTER_VALIDATE_EMAIL) ? $item : null;
})
->addProcessor(function($item) {
    // Step 3: Transform
    return [
        'name' => $item[0],
        'email' => strtolower($item[1]),
        'created' => date('Y-m-d H:i:s')
    ];
});
```

### Working with Large Datasets

```php
class StreamingConfig extends BaseConfig
{
    private int $processed = 0;
    private int $memoryLimit;
    
    public function __construct(int $memoryLimitMB = 128)
    {
        $this->memoryLimit = $memoryLimitMB * 1024 * 1024;
    }
    
    public function configureItem($item): array
    {
        $this->processed++;
        
        // Memory management
        if ($this->processed % 1000 === 0) {
            $usage = memory_get_usage(true);
            
            if ($usage > $this->memoryLimit) {
                gc_collect_cycles();
                error_log("Memory usage: " . round($usage / 1024 / 1024, 2) . "MB after processing {$this->processed} items");
            }
        }
        
        return $this->processItem($item);
    }
    
    private function processItem($item): array
    {
        // Your processing logic here
        return [
            'id' => $item[0],
            'data' => $item[1],
            'processed_at' => time()
        ];
    }
}
```

### Custom Resource with Pagination

```php
use DataReader\Resource\Resource;
use DataReader\ResourceInterface;
use DataReader\ConfigInterface;

class PaginatedApiResource extends Resource implements ResourceInterface
{
    private string $baseUrl;
    private int $perPage;
    private array $headers;
    
    public function __construct(string $baseUrl, int $perPage = 100, array $headers = [])
    {
        $this->baseUrl = $baseUrl;
        $this->perPage = $perPage;
        $this->headers = $headers;
    }
    
    public function apply(ConfigInterface $config): array
    {
        $allItems = [];
        $page = 1;
        
        do {
            $url = $this->baseUrl . "?page={$page}&per_page={$this->perPage}";
            $response = $this->makeRequest($url);
            $data = json_decode($response, true);
            
            if (empty($data['items'])) {
                break;
            }
            
            foreach ($data['items'] as $item) {
                $processed = $config->configureItem($item);
                if ($processed !== null) {
                    $allItems[] = $processed;
                }
            }
            
            $page++;
        } while (count($data['items']) === $this->perPage);
        
        $this->setData($allItems);
        return $this->getData();
    }
    
    private function makeRequest(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $this->headers)
            ]
        ]);
        
        $result = file_get_contents($url, false, $context);
        
        if ($result === false) {
            throw new ResourceException("Failed to fetch data from: {$url}");
        }
        
        return $result;
    }
    
    public function setData($data): void
    {
        $this->data = $data;
    }
    
    public function getData(): array
    {
        return $this->data ?? [];
    }
}
```

## Testing

### Unit Testing Example

```php
use PHPUnit\Framework\TestCase;
use DataReader\Reader;
use DataReader\Resource\ArrayData;
use DataReader\Output\Json;

class ReaderTest extends TestCase
{
    public function testBasicDataProcessing(): void
    {
        $data = [
            ['John', 'john@example.com', '30'],
            ['Jane', 'jane@example.com', '25']
        ];
        
        $config = new class implements ConfigInterface {
            public function configureItem($item): array {
                return [
                    'name' => $item[0],
                    'email' => $item[1], 
                    'age' => (int)$item[2]
                ];
            }
            
            public function configureFirstItem($item) {
                return $this->configureItem($item);
            }
        };
        
        $reader = new Reader(
            new ArrayData($data),
            new Json(),
            $config
        );
        
        $result = $reader->run();
        $decoded = json_decode($result, true);
        
        $this->assertCount(2, $decoded);
        $this->assertEquals('John', $decoded[0]['name']);
        $this->assertEquals(30, $decoded[0]['age']);
    }
}
```

## Troubleshooting

### Common Issues

**1. Memory Exhaustion with Large Files**
```php
// Solution: Use chunked processing
ini_set('memory_limit', '512M');

class ChunkedFileProcessor
{
    public function processFile(string $filename, int $chunkSize = 1000): void
    {
        $handle = fopen($filename, 'r');
        $chunk = [];
        $count = 0;
        
        while (($line = fgetcsv($handle)) !== false) {
            $chunk[] = $line;
            $count++;
            
            if ($count >= $chunkSize) {
                $this->processChunk($chunk);
                $chunk = [];
                $count = 0;
                gc_collect_cycles();
            }
        }
        
        if (!empty($chunk)) {
            $this->processChunk($chunk);
        }
        
        fclose($handle);
    }
    
    private function processChunk(array $chunk): void
    {
        $reader = new Reader(
            new ArrayData($chunk),
            new Json(),
            new MyConfig()
        );
        
        echo $reader->run();
    }
}
```

**2. Character Encoding Issues**
```php
class EncodingAwareConfig extends BaseConfig
{
    private string $encoding;
    
    public function __construct(string $encoding = 'UTF-8')
    {
        $this->encoding = $encoding;
    }
    
    public function configureItem($item): array
    {
        // Convert encoding
        foreach ($item as $key => $value) {
            if (is_string($value)) {
                $item[$key] = mb_convert_encoding($value, 'UTF-8', $this->encoding);
            }
        }
        
        return $this->processItem($item);
    }
}
```

**3. Invalid Data Handling**
```php
class RobustConfig extends BaseConfig
{
    public function configureItem($item): ?array
    {
        try {
            // Validate required fields
            if (empty($item[0]) || empty($item[1])) {
                return null; // Skip invalid records
            }
            
            return [
                'name' => $this->sanitizeString($item[0]),
                'email' => $this->validateEmail($item[1]),
                'age' => $this->parseAge($item[2] ?? null)
            ];
        } catch (\Exception $e) {
            error_log("Error processing item: " . json_encode($item) . " - " . $e->getMessage());
            return null;
        }
    }
    
    private function sanitizeString(?string $value): string
    {
        return trim(strip_tags($value ?? ''));
    }
    
    private function validateEmail(?string $email): ?string
    {
        $clean = filter_var($email, FILTER_VALIDATE_EMAIL);
        return $clean ?: null;
    }
    
    private function parseAge($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        
        $age = (int)$value;
        return ($age > 0 && $age < 150) ? $age : null;
    }
}
```

## Roadmap

### Version 1.0 (In Progress)
- [x] Complete ArrayData implementation
- [x] Add comprehensive error handling with custom exceptions
- [x] Implement XML and CSV output formats
- [x] Create BaseConfig class with field mapping and validation
- [x] Add factory methods for common use cases
- [ ] Add unit tests with PHPUnit
- [ ] Implement streaming support for large files (>100MB)

### Version 1.1 (Planned)
- [ ] Add Excel file format support (.xlsx, .xls)
- [ ] Implement caching mechanisms for processed data
- [ ] Add batch processing capabilities
- [ ] Create CLI tool for command-line usage
- [ ] Add data transformation pipelines
- [ ] Implement async processing support

### Version 1.2 (Future)
- [ ] Add database resource connectors (MySQL, PostgreSQL, SQLite)
- [ ] Implement API resource connectors (REST, GraphQL)
- [ ] Add data validation rule system
- [ ] Create visual data mapping interface
- [ ] Add support for nested data structures
- [ ] Implement data diff and merge capabilities

### Long-term Goals
- [ ] Plugin system for third-party extensions
- [ ] Web-based data transformation UI
- [ ] Integration with popular frameworks (Laravel, Symfony)
- [ ] Performance optimization for big data processing
- [ ] Machine learning integration for data analysis# Data Reader

A flexible and robust PHP library for reading, processing, and outputting data from various sources with configurable transformation pipelines, validation, and multiple output formats.

## Features

- **Multiple Data Sources**: Support for files (CSV, JSON, XML), arrays, and extensible resource types
- **Configurable Processing**: Transform and validate data during reading with custom configuration classes
- **Multiple Output Formats**: JSON, XML, CSV output with customizable options
- **Robust Error Handling**: Custom exceptions and comprehensive validation
- **Clean Architecture**: Interface-driven design following SOLID principles
- **Type Safety**: Full PHP 7.4+ type hints and strict typing
- **Easy Integration**: Simple fluent API for chaining operations
- **Factory Methods**: Quick setup for common use cases
- **Field Mapping & Validation**: Built-in support for data transformation and validation

## Installation

Install via Composer:

```bash
composer require resoul/data-reader
```

## Quick Start

```php
<?php
require_once 'vendor/autoload.php';

use DataReader\Factory\ReaderFactory;

// Quick setup with factory methods
$reader = ReaderFactory::createCsvReader('data.csv');

// Or manual setup with custom configuration
use DataReader\Reader;
use DataReader\Resource\File;
use DataReader\Resource\File\CSV;
use DataReader\Output\Json;
use DataReader\Config\BaseConfig;

class UserDataConfig extends BaseConfig
{
    public function configureItem($item): array
    {
        return [
            'id' => (int)$item[0],
            'name' => trim($item[1]),
            'email' => strtolower($item[2]),
            'age' => (int)$item[3],
            'created_at' => new DateTime($item[4])
        ];
    }
    
    public function configureFirstItem($item)
    {
        // Skip header row
        return false;
    }
}

$reader = new Reader();
$reader->resource(new File('users.csv', new CSV()));
$reader->config(new UserDataConfig());
$reader->output(new Json(JSON_PRETTY_PRINT));

try {
    $processedData = $reader->run();
    echo $processedData; // JSON output
} catch (\DataReader\Exception\DataReaderException $e) {
    echo "Error: " . $e->getMessage();
}
```

## Architecture

### Core Components

#### Reader
The main orchestrator class that coordinates resource reading, data configuration, and output formatting.

```php
$reader = new Reader($resource, $output, $config);
// or use fluent interface
$reader->resource($resource)
       ->config($config)
       ->output($output)
       ->run();
```

#### Resources
Data sources that implement `ResourceInterface`:

- **File**: Read from files with multiple format handlers (CSV, JSON, XML)
- **ArrayData**: Process in-memory arrays with full configuration support
- **Custom**: Extend `Resource` class for databases, APIs, or other sources

#### File Formats
Built-in support for multiple file formats:

- **CSV**: Comma-separated values with configurable delimiters
- **JSON**: JavaScript Object Notation with error handling
- **XML**: Extensible Markup Language with customizable element mapping

#### Configurations
Transform and validate data using `ConfigInterface` or extend `BaseConfig`:

```php
use DataReader\Config\BaseConfig;

class ProductConfig extends BaseConfig
{
    public function __construct()
    {
        // Set up field mapping
        $this->setFieldMapping([
            0 => 'name',
            1 => 'price', 
            2 => 'category'
        ]);
        
        // Add validators
        $this->addValidator(function($item) {
            return isset($item['price']) && $item['price'] > 0;
        });
    }
    
    public function configureItem($item): array
    {
        $mapped = $this->mapFields($item);
        
        if (!$this->validateItem($mapped)) {
            throw new InvalidArgumentException('Invalid item data');
        }
        
        return [
            'name' => trim($mapped['name']),
            'price' => (float)$mapped['price'],
            'category' => strtoupper($mapped['category']),
            'in_stock' => $mapped['price'] > 0
        ];
    }
    
    public function configureFirstItem($item)
    {
        // Skip header or process first row
        return false;
    }
}
```

#### Output Formats
Multiple output formats with customizable options:

- **Json**: JSON with formatting options
- **XML**: XML with custom root and item elements
- **CSV**: CSV with configurable delimiters and enclosures

## Usage Examples

### Quick Start with Factory Methods

```php
use DataReader\Factory\ReaderFactory;

// CSV with default JSON output
$users = ReaderFactory::createCsvReader('users.csv')
    ->config(new UserConfig())
    ->run();

// JSON file processing  
$products = ReaderFactory::createJsonReader('products.json')
    ->config(new ProductConfig())
    ->run();

// Array data processing
$data = [['name' => 'John', 'age' => 30], ['name' => 'Jane', 'age' => 25]];
$processed = ReaderFactory::createArrayReader($data)
    ->config(new PersonConfig())
    ->run();
```

### Reading Different File Formats

#### CSV Files
```php
use DataReader\Reader;
use DataReader\Resource\File;
use DataReader\Resource\File\CSV;
use DataReader\Output\Json;

class UserConfig implements \DataReader\ConfigInterface
{
    public function configureItem($item): array
    {
        return [
            'id' => (int)$item[0],
            'name' => trim($item[1]),
            'email' => filter_var($item[2], FILTER_VALIDATE_EMAIL),
            'created_at' => new DateTime($item[3])
        ];
    }
    
    public function configureFirstItem($item)
    {
        // Skip header row
        return false;
    }
}

$reader = new Reader(
    new File('users.csv', new CSV()),
    new Json(JSON_PRETTY_PRINT),
    new UserConfig()
);

try {
    $users = $reader->run();
    echo $users; // Pretty-printed JSON
} catch (\DataReader\Exception\ResourceException $e) {
    echo "File error: " . $e->getMessage();
}
```

#### JSON Files
```php
use DataReader\Resource\File\JSON;

$reader = new Reader(
    new File('data.json', new JSON()),
    new Json(),
    new DataConfig()
);

$data = $reader->run();
```

#### XML Files
```php
use DataReader\Resource\File\XML;

// XML with custom item tag
$reader = new Reader(
    new File('products.xml', new XML('product')), // item tag = 'product'
    new Json(),
    new ProductConfig()
);

$products = $reader->run();
```

### Processing Array Data

```php
use DataReader\Resource\ArrayData;
use DataReader\Config\BaseConfig;

class ProductConfig extends BaseConfig
{
    public function __construct()
    {
        // Set up field mapping
        $this->setFieldMapping([
            'product_name' => 'name',
            'price' => 'price',
            'quantity' => 'stock'
        ]);
        
        // Add validation
        $this->addValidator(function($item) {
            return isset($item['price']) && $item['price'] > 0;
        });
    }
    
    public function configureItem($item): array
    {
        $mapped = $this->mapFields($item);
        
        if (!$this->validateItem($mapped)) {
            return null; // Skip invalid items
        }
        
        return [
            'name' => $mapped['name'],
            'price' => (float)$mapped['price'],
            'in_stock' => (int)$mapped['stock'] > 0
        ];
    }
    
    public function configureFirstItem($item)
    {
        return $this->configureItem($item);
    }
}

$rawData = [
    ['product_name' => 'Laptop', 'price' => '999.99', 'quantity' => '5'],
    ['product_name' => 'Mouse', 'price' => '29.99', 'quantity' => '0'],
    ['product_name' => 'Invalid', 'price' => '-10', 'quantity' => '1'] // Will be skipped
];

$reader = new Reader(
    new ArrayData($rawData),
    new Json(),
    new ProductConfig()
);

$products = $reader->run();
```

### Multiple Output Formats

#### XML Output
```php
use DataReader\Output\XML;

$reader = new Reader(
    new File('data.csv', new CSV()),
    new XML('products', 'product'), // root: products, items: product
    new ProductConfig()
);

$xmlOutput = $reader->run();
echo $xmlOutput;
// <products>
//   <product>
//     <name>Laptop</name>
//     <price>999.99</price>
//   </product>
// </products>
```

#### CSV Output
```php
use DataReader\Output\CSV as CsvOutput;

$reader = new Reader(
    new File('data.json', new JSON()),
    new CsvOutput('|', '"'), // Custom delimiter and enclosure
    new DataConfig()
);

$csvOutput = $reader->run();
```

## Extending the Library

### Custom Resource Types

```php
use DataReader\Resource\Resource;
use DataReader\ResourceInterface;
use DataReader\ConfigInterface;
use DataReader\Exception\ResourceException;

class DatabaseResource extends Resource implements ResourceInterface
{
    private \PDO $connection;
    private string $query;
    
    public function __construct(\PDO $connection, string $query)
    {
        $this->connection = $connection;
        $this->query = $query;
    }
    
    public function apply(ConfigInterface $config): array
    {
        try {
            $stmt = $this->connection->prepare($this->query);
            $stmt->execute();
            
            $items = [];
            $isFirst = true;
            
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                if ($isFirst) {
                    $firstItem = $config->configureFirstItem($row);
                    if ($firstItem !== false) {
                        $items[] = $firstItem;
                    }
                    $isFirst = false;
                } else {
                    $items[] = $config->configureItem($row);
                }
            }
            
            $this->setData($items);
            return $this->getData();
        } catch (\PDOException $e) {
            throw new ResourceException('Database error: ' . $e->getMessage());
        }
    }
    
    public function setData($data): void
    {
        $this->data = $data;
    }
    
    public function getData(): array
    {
        return $this->data ?? [];
    }
}

// Usage
$pdo = new PDO($dsn, $user, $pass);
$reader = new Reader(
    new DatabaseResource($pdo, 'SELECT * FROM users'),
    new Json(),
    new UserConfig()
);
```

### Custom File Formats

```php
use DataReader\Resource\FileInterface;
use DataReader\ConfigInterface;
use DataReader\Exception\ResourceException;

class ExcelFormat implements FileInterface
{
    public function read($handle, ConfigInterface $config): array
    {
        // Example with PhpSpreadsheet (requires composer package)
        $content = stream_get_contents($handle);
        $tempFile = tempnam(sys_get_temp_dir(), 'excel');
        file_put_contents($tempFile, $content);
        
        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempFile);
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();
            
            $items = [];
            foreach ($data as $index => $row) {
                if ($index === 0) {
                    $firstItem = $config->configureFirstItem($row);
                    if ($firstItem !== false) {
                        $items[] = $firstItem;
                    }
                } else {
                    $items[] = $config->configureItem($row);
                }
            }
            
            return $items;
        } finally {
            unlink($tempFile);
        }
    }
}
```

### Custom Output Formats

```php
use DataReader\Output\Output;
use DataReader\OutputInterface;

class HTMLOutput extends Output implements OutputInterface
{
    private string $tableClass;
    
    public function __construct(string $tableClass = 'table')
    {
        $this->tableClass = $tableClass;
    }
    
    public function items($items): string
    {
        if (empty($items)) {
            return '<p>No data available</p>';
        }
        
        $html = "<table class=\"{$this->tableClass}\">\n";
        
        // Header
        $headers = array_keys($items[0]);
        $html .= "<thead><tr>\n";
        foreach ($headers as $header) {
            $html .= "<th>" . htmlspecialchars($header) . "</th>\n";
        }
        $html .= "</tr></thead>\n";
        
        // Body
        $html .= "<tbody>\n";
        foreach ($items as $item) {
            $html .= "<tr>\n";
            foreach ($item as $value) {
                $html .= "<td>" . htmlspecialchars((string)$value) . "</td>\n";
            }
            $html .= "</tr>\n";
        }
        $html .= "</tbody>\n</table>";
        
        return $html;
    }
}
```

## API Reference

### Reader Class

**Constructor**: `__construct(?ResourceInterface $resource = null, ?OutputInterface $output = null, ?ConfigInterface $config = null)`

**Methods**:
- `resource(ResourceInterface $resource): self` - Set data source
- `config(ConfigInterface $config): self` - Set data configuration
- `output(OutputInterface $output): self` - Set output format
- `run(): mixed` - Execute the data processing pipeline
- `getItems(): array` - Get processed items without output formatting
- `getTotalItems(): int` - Get count of processed items

### Factory Class

**ReaderFactory Methods**:
- `createCsvReader(string $filename): Reader` - Quick CSV reader setup
- `createJsonReader(string $filename): Reader` - Quick JSON reader setup
- `createArrayReader(array $data): Reader` - Quick array reader setup

### Core Interfaces

#### DataReaderInterface
- `getItems(): array`
- `getTotalItems(): int`

#### ResourceInterface
- `apply(ConfigInterface $config): array`

#### ConfigInterface
- `configureItem($item): mixed` - Transform individual data items
- `configureFirstItem($item): mixed` - Handle first item (headers, etc.)

#### OutputInterface
- `items($items): mixed` - Format processed data for output

#### FileInterface
- `read($handle, ConfigInterface $config): array` - Read from file handle

### Built-in Classes

#### Resources
- `File(string $filename, FileInterface $format)` - File-based data source
- `ArrayData(array $data = [])` - Array-based data source

#### File Formats
- `CSV()` - CSV file reader
- `JSON()` - JSON file reader
- `XML(string $itemTag = 'item')` - XML file reader

#### Output Formats
- `Json(int $options = JSON_PRETTY_PRINT)` - JSON output
- `XML(string $root = 'data', string $item = 'item')` - XML output
- `CSV(string $delimiter = ',', string $enclosure = '"')` - CSV output

#### Configuration Base Classes
- `BaseConfig` - Abstract base with field mapping and validation
    - `setFieldMapping(array $mapping): self`
    - `addValidator(callable $validator): self`
    - `mapFields($item): array` (protected)
    - `validateItem($item): bool` (protected)

### Exception Classes

- `DataReaderException` - Base exception class
- `ResourceException` - Resource-related errors
- `ConfigurationException` - Configuration errors
- `OutputException` - Output formatting errors

## Requirements

- **PHP 8.0 or higher** (uses strict typing and return type declarations)
- **No external dependencies** for core functionality
- **Optional dependencies** for extended functionality:
    - `phpoffice/phpspreadsheet` - for Excel file support
    - `ext-simplexml` - for XML processing (usually included)
    - `ext-json` - for JSON processing (usually included)

## Error Handling

The library provides comprehensive error handling with custom exception types:

```php
use DataReader\Exception\{DataReaderException, ResourceException, ConfigurationException, OutputException};

try {
    $reader = ReaderFactory::createCsvReader('nonexistent.csv');
    $data = $reader->run();
} catch (ResourceException $e) {
    // Handle file/resource errors
    echo "Resource error: " . $e->getMessage();
} catch (ConfigurationException $e) {
    // Handle configuration errors  
    echo "Configuration error: " . $e->getMessage();
} catch (OutputException $e) {
    // Handle output formatting errors
    echo "Output error: " . $e->getMessage();
} catch (DataReaderException $e) {
    // Handle any other data reader errors
    echo "General error: " . $e->getMessage();
}
```

## Performance Considerations

### Memory Usage
- **Large Files**: Consider implementing streaming for files > 100MB
- **Array Processing**: ArrayData loads all data into memory
- **Output Buffering**: JSON and XML outputs build complete strings in memory

### Optimization Tips
```php
// For large datasets, process in chunks
class ChunkedConfig extends BaseConfig 
{
    private int $processed = 0;
    private int $chunkSize;
    
    public function __construct(int $chunkSize = 1000)
    {
        $this->chunkSize = $chunkSize;
    }
    
    public function configureItem($item): ?array
    {
        if ($this->processed++ % $this->chunkSize === 0) {
            // Trigger garbage collection every chunk
            gc_collect_cycles();
        }
        
        return $this->processItem($item);
    }
}
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Roadmap

- [ ] Complete ArrayData implementation
- [ ] Add XML output format
- [ ] Implement data validation features
- [ ] Add streaming support for large files
- [ ] Create additional file format handlers (JSON, XML, Excel)
- [ ] Add caching mechanisms
- [ ] Implement batch processing capabilities

## Support

For support, please open an issue on the GitHub repository.